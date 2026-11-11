<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\Billing;

use App\Container\EntityManagerAwareTrait;
use App\Container\LoggerAwareTrait;
use App\Entity\Api\Status;
use App\Entity\Repository\StationRepository;
use App\Entity\Repository\StorageLocationRepository;
use App\Entity\Station;
use App\Http\Response;
use App\Http\ServerRequest;
use App\Nginx\Nginx;
use App\OpenApi;
use App\Radio\Configuration;
use App\Radio\Enums\BackendAdapters;
use App\Radio\Enums\FrontendAdapters;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * High-performance Webhook and REST API controller for automated hosting,
 * provisioning, and subscription lifecycle management (WHMCS, Blesta, FOSSBilling).
 *
 * Station create/delete/suspend follow the same Configuration + StationRepository
 * paths used by Admin\StationsController so billing automation does not leave
 * orphan supervisor processes or incomplete station directories.
 */
#[
    OA\Post(
        path: '/admin/billing/provision',
        operationId: 'billingProvisionStation',
        summary: 'Provision, suspend, unsuspend, terminate, or resize a station via billing automation.',
        requestBody: new OA\RequestBody(
            description: 'Billing payload with action, station identifier, and package limits',
            required: true,
            content: new OA\JsonContent(
                required: ['action'],
                properties: [
                    new OA\Property(
                        property: 'action',
                        type: 'string',
                        enum: ['create', 'suspend', 'unsuspend', 'terminate', 'modify_package', 'status']
                    ),
                    new OA\Property(
                        property: 'station',
                        type: 'string',
                        description: 'Station ID or short name (required for existing station actions)'
                    ),
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        description: 'Station name (for create)'
                    ),
                    new OA\Property(
                        property: 'max_listeners',
                        type: 'integer',
                        description: 'Max listener slot quota'
                    ),
                    new OA\Property(
                        property: 'max_bitrate',
                        type: 'integer',
                        description: 'Maximum allowed bitrate in kbps'
                    ),
                    new OA\Property(
                        property: 'disk_quota_mb',
                        type: 'integer',
                        description: 'Maximum storage quota in MB (applied to media/recordings/podcasts locations; 0 clears quota)'
                    ),
                ],
            )
        ),
        tags: [OpenApi::TAG_ADMIN],
        responses: [
            new OpenApi\Response\Success(
                content: new OA\JsonContent(ref: Status::class)
            ),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class BillingController
{
    use EntityManagerAwareTrait;
    use LoggerAwareTrait;

    public function __construct(
        private readonly StationRepository $stationRepo,
        private readonly StorageLocationRepository $storageLocationRepo,
        private readonly Configuration $configuration,
        private readonly Nginx $nginx,
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $data = (array)$request->getParsedBody();
        $action = strtolower(trim((string)($data['action'] ?? '')));

        if (empty($action)) {
            throw new InvalidArgumentException('Billing action is required.');
        }

        return match ($action) {
            'create', 'provision' => $this->handleCreate($data, $response),
            'suspend' => $this->handleSuspend($data, $response, false),
            'unsuspend' => $this->handleSuspend($data, $response, true),
            'terminate', 'delete' => $this->handleTerminate($data, $response),
            'modify_package', 'resize' => $this->handleModifyPackage($data, $response),
            'status' => $this->handleStatus($data, $response),
            default => throw new InvalidArgumentException(sprintf('Unsupported billing action: "%s"', $action)),
        };
    }

    private function handleCreate(array $data, Response $response): ResponseInterface
    {
        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            throw new InvalidArgumentException('Station name is required for provisioning.');
        }

        $station = new Station();
        $station->name = $name;
        $station->frontend_type = FrontendAdapters::Icecast;
        $station->backend_type = BackendAdapters::Liquidsoap;
        $station->is_enabled = true;

        if (isset($data['max_bitrate'])) {
            $station->max_bitrate = (int)$data['max_bitrate'];
        }

        $feConfig = $station->frontend_config;
        $feConfig->ensurePasswordsAreSet();
        if (isset($data['max_listeners'])) {
            $feConfig->max_listeners = (int)$data['max_listeners'];
        }
        $station->frontend_config = $feConfig;

        $station->generateAdapterApiKey();

        try {
            // Match Admin\StationsController::handleCreate — ports, dirs, playlist, supervisor prep.
            $this->configuration->initializeConfiguration($station);
            $this->applyDiskQuota($station, $data);
            $this->stationRepo->resetMounts($station);
        } catch (Throwable $e) {
            $this->em->remove($station);
            $this->em->flush();

            throw $e;
        }

        return $response->withJson([
            'success' => true,
            'message' => 'Station provisioned successfully.',
            'station_id' => $station->id,
            'short_name' => $station->short_name,
            'max_listeners' => $station->frontend_config->max_listeners,
            'max_bitrate' => $station->max_bitrate,
            'disk_quota_mb' => $this->getDiskQuotaMb($station),
        ]);
    }

    private function handleSuspend(array $data, Response $response, bool $enabled): ResponseInterface
    {
        $station = $this->requireStation($data);
        $station->is_enabled = $enabled;

        if ($enabled) {
            // Unsuspend: allow writeConfiguration to start adapters (same as ServicesController).
            $station->has_started = true;
        }

        $this->em->persist($station);
        $this->em->flush();

        try {
            // Disabled stations tear down local supervisor services via removeLocalServices.
            // Enabled stations rewrite and (re)start adapters.
            $this->configuration->writeConfiguration(
                station: $station,
                forceRestart: true
            );

            if ($enabled) {
                $this->nginx->writeConfiguration($station);
            }
        } catch (Throwable) {
            // Mirrors Admin\StationsController::handleEdit — supervisor may already be stopped.
        }

        $actionText = $enabled ? 'unsuspended' : 'suspended';
        return $response->withJson([
            'success' => true,
            'message' => sprintf('Station %s successfully.', $actionText),
            'station_id' => $station->id,
            'is_enabled' => $station->is_enabled,
        ]);
    }

    private function handleTerminate(array $data, Response $response): ResponseInterface
    {
        $station = $this->requireStation($data);
        $stationId = $station->id;
        $stationName = $station->name;

        // Match Admin\StationsController::handleDelete — stop supervisor before entity removal.
        $this->configuration->removeConfiguration($station);

        $fsUtils = new Filesystem();
        $stationBaseDir = $station->radio_base_dir;
        foreach (Station::NON_STORAGE_LOCATION_DIRS as $otherDir) {
            try {
                $fsUtils->remove($stationBaseDir . '/' . $otherDir);
            } catch (IOException $e) {
                $this->logger->error(
                    sprintf(
                        'Error while deleting station directory "%s": %s',
                        $otherDir,
                        $e->getMessage()
                    ),
                    [
                        'exception' => $e,
                    ]
                );
            }
        }

        $this->em->flush();

        foreach ($station->getAllStorageLocations() as $storageLocation) {
            $stations = $this->storageLocationRepo->getStationsUsingLocation($storageLocation);
            if (1 === count($stations)) {
                $this->em->remove($storageLocation);
            }
        }

        $this->em->remove($station);
        $this->em->flush();

        return $response->withJson([
            'success' => true,
            'message' => sprintf('Station "%s" (ID %d) terminated successfully.', $stationName, $stationId),
        ]);
    }

    private function handleModifyPackage(array $data, Response $response): ResponseInterface
    {
        $station = $this->requireStation($data);

        if (isset($data['max_bitrate'])) {
            $station->max_bitrate = (int)$data['max_bitrate'];
        }

        if (isset($data['max_listeners'])) {
            $feConfig = $station->frontend_config;
            $feConfig->max_listeners = (int)$data['max_listeners'];
            $station->frontend_config = $feConfig;
        }

        $this->applyDiskQuota($station, $data);

        $station->needs_restart = true;
        $this->em->persist($station);
        $this->em->flush();

        return $response->withJson([
            'success' => true,
            'message' => 'Station package limits modified successfully.',
            'station_id' => $station->id,
            'max_listeners' => $station->frontend_config->max_listeners,
            'max_bitrate' => $station->max_bitrate,
            'disk_quota_mb' => $this->getDiskQuotaMb($station),
        ]);
    }

    private function handleStatus(array $data, Response $response): ResponseInterface
    {
        $station = $this->requireStation($data);

        return $response->withJson([
            'success' => true,
            'station_id' => $station->id,
            'name' => $station->name,
            'short_name' => $station->short_name,
            'is_enabled' => $station->is_enabled,
            'has_started' => $station->has_started,
            'max_listeners' => $station->frontend_config->max_listeners,
            'max_bitrate' => $station->max_bitrate,
            'disk_quota_mb' => $this->getDiskQuotaMb($station),
        ]);
    }

    /**
     * Apply disk_quota_mb to all station storage locations (media, recordings, podcasts).
     * Supported via StorageLocation::$storageQuota; 0 clears the quota (unlimited).
     */
    private function applyDiskQuota(Station $station, array $data): void
    {
        if (!array_key_exists('disk_quota_mb', $data) || $data['disk_quota_mb'] === null || $data['disk_quota_mb'] === '') {
            return;
        }

        $mb = (int)$data['disk_quota_mb'];
        if ($mb < 0) {
            throw new InvalidArgumentException('disk_quota_mb must be greater than or equal to 0.');
        }

        // Readable size is honored by StorageLocation::$storageQuota / Quota::convertFromReadableSize.
        $quota = (0 === $mb) ? null : sprintf('%d MB', $mb);

        foreach ($station->getAllStorageLocations() as $storageLocation) {
            $storageLocation->storageQuota = $quota;
            $this->em->persist($storageLocation);
        }

        $this->em->flush();
    }

    private function getDiskQuotaMb(Station $station): ?int
    {
        $quotaBytes = $station->media_storage_location->storageQuotaBytes;
        if (!$quotaBytes instanceof BigInteger) {
            return null;
        }

        return $quotaBytes
            ->toBigDecimal()
            ->dividedBy(1024 * 1024, 0, RoundingMode::Down)
            ->toInt();
    }

    private function requireStation(array $data): Station
    {
        $identifier = (string)($data['station'] ?? $data['station_id'] ?? '');
        if (empty($identifier)) {
            throw new InvalidArgumentException('Station identifier is required.');
        }

        $station = $this->stationRepo->findByIdentifier($identifier);
        if (!$station instanceof Station) {
            throw new InvalidArgumentException(sprintf('Station "%s" not found.', $identifier));
        }

        return $station;
    }
}
