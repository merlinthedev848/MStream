<?php

declare(strict_types=1);

namespace App\Radio;

use App\Container\EntityManagerAwareTrait;
use App\Container\EnvironmentAwareTrait;
use App\Entity\Enums\PlaylistOrders;
use App\Entity\Enums\PlaylistSources;
use App\Entity\Enums\PlaylistTypes;
use App\Entity\Station;
use App\Entity\StationPlaylist;
use App\Entity\StationPlaylistFolder;
use App\Exception;
use App\Radio\AutoDJ\PlaylistQueueResetter;
use App\Radio\Enums\BackendAdapters;
use App\Radio\Enums\CrossfadeModes;
use App\Radio\Enums\FrontendAdapters;
use RuntimeException;
use Supervisor\Exception\SupervisorException;
use Supervisor\SupervisorInterface;

/**
 * Station lifecycle helpers: AutoDJ-ready defaults, ascending port blocks, supervisor config.
 *
 * Port allocation (ascending):
 * - Blocks are chosen from AUTO_ASSIGN_PORT_MIN..AUTO_ASSIGN_PORT_MAX (defaults
 *   {@see self::DEFAULT_PORT_MIN}–{@see self::DEFAULT_PORT_MAX}) in steps of
 *   {@see self::PORT_RANGE_STEP}, always the lowest free block (no random gaps).
 * - Within a block for one station:
 *   - +0  frontend listen port (Icecast / SHOUTcast / RSAS)
 *   - +4  Liquidsoap telnet
 *   - +5  DJ harbor (+6 also reserved for harbor adjacency)
 */
final class Configuration
{
    use EntityManagerAwareTrait;
    use EnvironmentAwareTrait;

    public const int DEFAULT_PORT_MIN = 8000;
    public const int DEFAULT_PORT_MAX = 8499;

    /** Size of each auto-assigned station port block. */
    public const int PORT_RANGE_STEP = 10;

    /** Offset from the block base port for Liquidsoap telnet. */
    public const int PORT_OFFSET_TELNET = 4;

    /** Offset from the block base port for the DJ harbor. */
    public const int PORT_OFFSET_DJ = 5;

    public const string PER_STATION_SUPERVISOR_CONF = 'supervisord.conf';

    public const string DEFAULT_PLAYLIST_NAME = 'default';

    /** Media-root playlist folder path (empty string after filterPath). */
    public const string DEFAULT_MEDIA_FOLDER_PATH = '';

    public const array PROTECTED_PORTS = [
        3306, // MariaDB
        6010, // Nginx internal
        6379, // Redis
        8080, // Common debug port
        80,   // HTTP
        443,  // HTTPS
        2022, // SFTP
    ];

    /**
     * Request-scoped map of reserved ports → station reference.
     * Updated when ports are assigned so sequential creates in one process stay ascending.
     *
     * @var array<int, array{id: int|null, name: string, object_id?: int}>|null
     */
    private ?array $usedPorts = null;

    public function __construct(
        private readonly Adapters $adapters,
        private readonly SupervisorInterface $supervisor,
        private readonly PlaylistQueueResetter $playlistQueueResetter,
    ) {
    }

    public function initializeConfiguration(Station $station): void
    {
        // Ensure port configuration exists
        $this->assignRadioPorts($station);

        // Clear station caches and generate API adapter key if none exists.
        if (empty($station->adapter_api_key)) {
            $station->generateAdapterApiKey();
        }

        // Ensure all directories exist (including media storage path).
        $station->ensureDirectoriesExist();

        // Persist AutoDJ-ready backend + default playlist (admin create and billing provision).
        $this->ensureAutoDjReadyDefaults($station);

        $this->em->persist($station);
        foreach ($station->getAllStorageLocations() as $storageLocation) {
            $this->em->persist($storageLocation);
        }

        $this->em->flush();

        $this->playlistQueueResetter->resetAllQueues($station);
    }

    /**
     * Ensure a new (or incomplete) station can AutoDJ after media upload only:
     * Liquidsoap-friendly backend defaults, enabled default playlist, media-root folder link.
     */
    public function ensureAutoDjReadyDefaults(Station $station): void
    {
        if ($station->backend_type->isEnabled()) {
            $backendConfig = $station->backend_config;

            // Prefer Liquidsoap AutoDJ (not manual cueing) with a sensible crossfade.
            $backendConfig->use_manual_autodj = false;

            if ('' === $backendConfig->crossfade_type) {
                $backendConfig->crossfade_type = CrossfadeModes::default()->value;
            }

            if ($backendConfig->crossfade <= 0) {
                $backendConfig->crossfade = 2.0;
            }

            if ($backendConfig->autodj_queue_length < 1) {
                $backendConfig->autodj_queue_length = 3;
            }

            $station->backend_config = $backendConfig;
        }

        $defaultPlaylist = $this->findEnabledDefaultPlaylist($station);

        if (null === $defaultPlaylist) {
            $defaultPlaylist = new StationPlaylist($station);
            $defaultPlaylist->name = self::DEFAULT_PLAYLIST_NAME;
            $defaultPlaylist->type = PlaylistTypes::default();
            $defaultPlaylist->source = PlaylistSources::Songs;
            $defaultPlaylist->order = PlaylistOrders::Shuffle;
            $defaultPlaylist->weight = StationPlaylist::DEFAULT_WEIGHT;
            $defaultPlaylist->is_enabled = true;
            $defaultPlaylist->include_in_requests = true;
            $station->playlists->add($defaultPlaylist);
            $this->em->persist($defaultPlaylist);
        } else {
            // Re-enable / re-weight only — do not clobber operator playlist source/order on edits.
            $defaultPlaylist->is_enabled = true;
            if ($defaultPlaylist->weight < 1) {
                $defaultPlaylist->weight = StationPlaylist::DEFAULT_WEIGHT;
            }
            $this->em->persist($defaultPlaylist);
        }

        $this->ensureDefaultPlaylistMediaFolder($station, $defaultPlaylist);
    }

    private function findEnabledDefaultPlaylist(Station $station): ?StationPlaylist
    {
        foreach ($station->playlists as $playlist) {
            if ($playlist->is_enabled && PlaylistTypes::default() === $playlist->type) {
                return $playlist;
            }
        }

        // Also accept a disabled playlist literally named "default" so we can re-enable it.
        foreach ($station->playlists as $playlist) {
            if (self::DEFAULT_PLAYLIST_NAME === $playlist->name) {
                return $playlist;
            }
        }

        return null;
    }

    /**
     * Wire the station media root to the default playlist so root uploads join AutoDJ.
     */
    private function ensureDefaultPlaylistMediaFolder(
        Station $station,
        StationPlaylist $defaultPlaylist
    ): void {
        foreach ($defaultPlaylist->folders as $folder) {
            if (self::DEFAULT_MEDIA_FOLDER_PATH === $folder->path) {
                return;
            }
        }

        $folder = new StationPlaylistFolder(
            $station,
            $defaultPlaylist,
            self::DEFAULT_MEDIA_FOLDER_PATH
        );
        $defaultPlaylist->folders->add($folder);
        $this->em->persist($folder);
    }

    /**
     * Write all configuration changes to the filesystem and reload supervisord.
     */
    public function writeConfiguration(
        Station $station,
        bool $reloadSupervisor = true,
        bool $forceRestart = false,
        bool $attemptReload = true
    ): void {
        if ($this->environment->isTesting()) {
            return;
        }

        $this->initializeConfiguration($station);

        // Initialize adapters.
        $supervisorConfig = [];
        $supervisorConfigFile = self::getSupervisorConfPath($station);

        $frontendEnum = $station->frontend_type;
        $backendEnum = $station->backend_type;

        $frontend = $this->adapters->getFrontendAdapter($station);
        $backend = $this->adapters->getBackendAdapter($station);

        // If no processes need to be managed, remove any existing config.
        if (
            (null === $frontend || !$frontend->hasCommand($station))
            && (null === $backend || !$backend->hasCommand($station))
        ) {
            $this->removeLocalServices($station, $reloadSupervisor, true);
            throw new RuntimeException('Station has no local services.');
        }

        if (!$station->has_started) {
            $this->removeLocalServices($station, $reloadSupervisor);
            throw new RuntimeException('Station has not started yet.');
        }

        if (!$station->is_enabled) {
            $this->removeLocalServices($station, $reloadSupervisor);
            throw new RuntimeException('Station is disabled.');
        }

        // Write group section of config
        $programNames = [];
        $programs = [];

        if (null !== $backend && $backend->hasCommand($station)) {
            $programName = $backend->getSupervisorProgramName($station);

            $programs[$programName] = $backend;
            $programNames[] = $programName;
        }

        if (null !== $frontend && $frontend->hasCommand($station)) {
            $programName = $frontend->getSupervisorProgramName($station);

            $programs[$programName] = $frontend;
            $programNames[] = $programName;
        }

        $stationGroup = self::getSupervisorGroupName($station);

        $supervisorConfig[] = '[group:' . $stationGroup . ']';
        $supervisorConfig[] = 'programs=' . implode(',', $programNames);
        $supervisorConfig[] = '';

        foreach ($programs as $programName => $adapter) {
            $configLines = [
                'user' => 'mstream',
                'priority' => 990,
                'startsecs' => 10,
                'startretries' => 5,
                'stopasgroup' => 'true',
                'killasgroup' => 'true',
                'command' => $adapter->getCommand($station),
                'directory' => $station->getRadioConfigDir(),
                'environment' => self::buildEnvironment([
                    'TZ' => $station->timezone,
                    ...$adapter->getEnvironmentVariables($station),
                ]),
                'autostart' => 'true',
                'autorestart' => 'true',
                'stdout_logfile' => $adapter->getLogPath($station),
                'stdout_logfile_maxbytes' => '5MB',
                'stdout_logfile_backups' => '5',
                'redirect_stderr' => 'true',
                'stdout_events_enabled' => 'true',
                'stderr_events_enabled' => 'true',
            ];

            $supervisorConfig[] = '[program:' . $programName . ']';
            foreach ($configLines as $configKey => $configValue) {
                $supervisorConfig[] = $configKey . '=' . $configValue;
            }
            $supervisorConfig[] = '';
        }

        // Write config contents
        $supervisorConfigData = implode("\n", $supervisorConfig);
        file_put_contents($supervisorConfigFile, $supervisorConfigData);

        // Write supporting configurations.
        $frontend?->write($station);
        $backend?->write($station);

        $this->markAsStarted($station);

        // Reload Supervisord and process groups
        if ($reloadSupervisor) {
            $affectedGroups = $this->reloadSupervisor();
            $wasRestarted = in_array($stationGroup, $affectedGroups, true);

            if (!$wasRestarted && $forceRestart) {
                try {
                    if ($attemptReload && ($backendEnum->isEnabled() || $frontendEnum->supportsReload())) {
                        $backend?->reload($station);
                        $frontend?->reload($station);
                    } else {
                        $this->supervisor->stopProcessGroup($stationGroup);
                        $this->supervisor->startProcessGroup($stationGroup);
                    }
                } catch (SupervisorException) {
                }
            }
        }
    }

    private function removeLocalServices(
        Station $station,
        bool $reloadSupervisor = true,
        bool $isRemoteOnly = false
    ): void {
        $station->has_started = $isRemoteOnly;
        $station->needs_restart = false;
        $station->current_streamer = null;
        $station->current_song = null;

        $this->em->persist($station);
        $this->em->flush();

        $this->removeConfiguration($station, $reloadSupervisor);
    }

    private function markAsStarted(Station $station): void
    {
        $station->has_started = true;
        $station->needs_restart = false;
        $station->current_streamer = null;
        $station->current_song = null;

        $this->em->persist($station);
        $this->em->flush();
    }

    /**
     * Trigger a supervisord reload and restart all relevant services.
     */
    private function reloadSupervisor(): array
    {
        return $this->supervisor->reloadAndApplyConfig()->getAffected();
    }

    /**
     * Assign the next ascending free port block to this station, or ensure offsets are filled.
     *
     * Base port selection walks AUTO_ASSIGN_PORT_MIN → MAX in {@see self::PORT_RANGE_STEP}
     * increments and always picks the lowest unused block (ascending, not random).
     */
    public function assignRadioPorts(Station $station, bool $force = false): void
    {
        if (
            $station->frontend_type->isEnabled()
            || $station->backend_type->isEnabled()
        ) {
            $frontendConfig = $station->frontend_config;
            $backendConfig = $station->backend_config;

            $basePort = $frontendConfig->port;
            if ($force || null === $basePort) {
                $basePort = $this->getFirstAvailableRadioPort($station);

                $frontendConfig->port = $basePort;
                $station->frontend_config = $frontendConfig;
            }

            $djPort = $backendConfig->dj_port;
            if ($force || null === $djPort) {
                $backendConfig->dj_port = $basePort + self::PORT_OFFSET_DJ;
                $station->backend_config = $backendConfig;
            }

            $telnetPort = $backendConfig->telnet_port;
            if ($force || null === $telnetPort) {
                $backendConfig->telnet_port = $basePort + self::PORT_OFFSET_TELNET;
                $station->backend_config = $backendConfig;
            }

            $this->em->persist($station);
            $this->rememberStationPorts($station);
        }
    }

    /**
     * Determine the lowest available PORT_RANGE_STEP-sized block with no stations occupying it.
     */
    public function getFirstAvailableRadioPort(?Station $station = null): int
    {
        $usedPorts = $this->getUsedPorts($station);

        // Ascending walk: first free block wins (no random / scattered assignment).
        $protectedPorts = self::PROTECTED_PORTS;

        $portMin = $this->environment->getAutoAssignPortMin();
        $portMax = $this->environment->getAutoAssignPortMax();

        for ($port = $portMin; $port <= $portMax; $port += self::PORT_RANGE_STEP) {
            if (in_array($port, $protectedPorts, true)) {
                continue;
            }

            $rangeInUse = false;
            for ($i = $port; $i < $port + self::PORT_RANGE_STEP; $i++) {
                if (isset($usedPorts[$i])) {
                    $rangeInUse = true;
                    break;
                }
            }

            if (!$rangeInUse) {
                return $port;
            }
        }

        throw new Exception('This installation has no available ports for new radio stations.');
    }

    /**
     * Drop the request-scoped used-port cache (tests / long-running workers after external changes).
     */
    public function clearUsedPortsCache(): void
    {
        $this->usedPorts = null;
    }

    /**
     * Get an array of all used ports across the system, except the ones used by the station specified (if specified).
     *
     * @return array<int, array{
     *   id: int|null,
     *   name: string,
     *   object_id?: int
     * }>
     */
    public function getUsedPorts(?Station $exceptStation = null): array
    {
        if (null === $this->usedPorts) {
            $this->usedPorts = $this->loadUsedPortsFromDatabase();
        }

        if (null === $exceptStation) {
            return $this->usedPorts;
        }

        $exceptId = $exceptStation->id ?? null;
        $exceptObjectId = spl_object_id($exceptStation);

        return array_filter(
            $this->usedPorts,
            static function (array $stationReference) use ($exceptId, $exceptObjectId): bool {
                if (null !== $exceptId && ($stationReference['id'] ?? null) === $exceptId) {
                    return false;
                }

                return ($stationReference['object_id'] ?? null) !== $exceptObjectId;
            }
        );
    }

    /**
     * @return array<int, array{id: int|null, name: string, object_id?: int}>
     */
    private function loadUsedPortsFromDatabase(): array
    {
        $usedPorts = [];

        /** @var list<array{
         *   id: int,
         *   name: string,
         *   frontend_type: string,
         *   frontend_config: array<string, mixed>|null,
         *   backend_type: string,
         *   backend_config: array<string, mixed>|null
         * }> $stationConfigs */
        $stationConfigs = $this->em->createQuery(
            <<<'DQL'
                SELECT s.id, s.name, s.frontend_type, s.frontend_config_raw AS frontend_config,
                    s.backend_type, s.backend_config_raw AS backend_config
                FROM App\Entity\Station s
            DQL
        )->getArrayResult();

        foreach ($stationConfigs as $row) {
            $stationReference = ['id' => $row['id'], 'name' => $row['name']];
            $this->mergeStationPortsIntoMap(
                $usedPorts,
                $stationReference,
                $row['frontend_type'],
                (array)$row['frontend_config'],
                $row['backend_type'],
                (array)$row['backend_config'],
            );
        }

        return $usedPorts;
    }

    /**
     * Reserve this station's ports in the request-scoped map so the next assign stays ascending.
     */
    private function rememberStationPorts(Station $station): void
    {
        // Force cache load so we merge into a complete map.
        $this->getUsedPorts();

        $exceptId = $station->id ?? null;
        $exceptObjectId = spl_object_id($station);

        // Drop any previously reserved ports for this station (force-reassign / offset fill).
        $this->usedPorts = array_filter(
            $this->usedPorts ?? [],
            static function (array $stationReference) use ($exceptId, $exceptObjectId): bool {
                if (null !== $exceptId && ($stationReference['id'] ?? null) === $exceptId) {
                    return false;
                }

                return ($stationReference['object_id'] ?? null) !== $exceptObjectId;
            }
        );

        $stationReference = [
            'id' => $exceptId,
            'name' => $station->name,
            'object_id' => $exceptObjectId,
        ];

        $this->mergeStationPortsIntoMap(
            $this->usedPorts,
            $stationReference,
            $station->frontend_type->value,
            $station->frontend_config->toArray(true) ?? [],
            $station->backend_type->value,
            $station->backend_config->toArray(true) ?? [],
        );
    }

    /**
     * @param array<int, array{id: int|null, name: string, object_id?: int}> $usedPorts
     * @param array{id: int|null, name: string, object_id?: int} $stationReference
     * @param array<array-key, mixed> $frontendConfig
     * @param array<array-key, mixed> $backendConfig
     */
    private function mergeStationPortsIntoMap(
        array &$usedPorts,
        array $stationReference,
        string $frontendType,
        array $frontendConfig,
        string $backendType,
        array $backendConfig,
    ): void {
        if ($frontendType !== FrontendAdapters::Remote->value) {
            if (!empty($frontendConfig['port'])) {
                $port = (int)$frontendConfig['port'];
                $usedPorts[$port] = $stationReference;
            }
        }

        if ($backendType !== BackendAdapters::None->value) {
            // For DJ port, consider both the assigned port and port+1 to be reserved and in-use.
            if (!empty($backendConfig['dj_port'])) {
                $port = (int)$backendConfig['dj_port'];
                $usedPorts[$port] = $stationReference;
                $usedPorts[$port + 1] = $stationReference;
            }
            if (!empty($backendConfig['telnet_port'])) {
                $port = (int)$backendConfig['telnet_port'];
                $usedPorts[$port] = $stationReference;
            }
        }
    }

    /**
     * Remove configuration (i.e. prior to station removal) and trigger a Supervisor refresh.
     *
     * @param Station $station
     */
    public function removeConfiguration(
        Station $station,
        bool $reloadSupervisor = true
    ): void {
        if ($this->environment->isTesting()) {
            return;
        }

        $stationGroup = 'station_' . $station->id;

        // Try forcing the group to stop, but don't hard-fail if it doesn't.
        if ($reloadSupervisor) {
            try {
                $this->supervisor->stopProcessGroup($stationGroup);
                $this->supervisor->removeProcessGroup($stationGroup);
            } catch (SupervisorException) {
            }
        }

        $supervisorConfigPath = self::getSupervisorConfPath($station);
        @unlink($supervisorConfigPath);

        if ($reloadSupervisor) {
            $this->reloadSupervisor();
        }
    }

    protected static function buildEnvironment(array $values): string
    {
        return implode(
            ',',
            array_map(
                static fn(string $k, mixed $v) => sprintf(
                    '%s="%s"',
                    $k,
                    str_replace(["\r", "\n", '%'], ['', '', '%%'], $v)
                ),
                array_keys($values),
                array_values($values)
            )
        );
    }

    public static function getSupervisorConfPath(
        Station|string $configDir
    ): string {
        if ($configDir instanceof Station) {
            $configDir = $configDir->getRadioConfigDir();
        }

        return $configDir . '/' . self::PER_STATION_SUPERVISOR_CONF;
    }

    /**
     * @return int[]
     */
    public static function enumerateDefaultPorts(
        int $rangeMin = self::DEFAULT_PORT_MIN,
        int $rangeMax = self::DEFAULT_PORT_MAX,
    ): array {
        $defaultPorts = [];

        for ($i = $rangeMin; $i < $rangeMax; $i += self::PORT_RANGE_STEP) {
            if (in_array($i, self::PROTECTED_PORTS, true)) {
                continue;
            }

            $defaultPorts[] = $i;
            $defaultPorts[] = $i + self::PORT_OFFSET_DJ;
            $defaultPorts[] = $i + self::PORT_OFFSET_DJ + 1;
        }

        return $defaultPorts;
    }

    public static function getSupervisorGroupName(Station $station): string
    {
        return 'station_' . $station->id;
    }

    public static function getSupervisorProgramName(Station $station, string $category): string
    {
        return 'station_' . $station->id . '_' . $category;
    }
}
