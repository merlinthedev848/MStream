<?php

declare(strict_types=1);

namespace App\Radio\Frontend;

use App\Entity\Api\LogType;
use App\Entity\Station;
use App\Environment;
use NowPlaying\Result\Result;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;

/**
 * Classic SHOUTcast DNAS 1.x local frontend (BYO binary — MStream does not distribute it).
 *
 * Distinct from {@see Shoutcast} (DNAS 2). Multi-service fleets can run v1 and v2 stations
 * concurrently on different port blocks.
 */
final class Shoutcast1 extends AbstractFrontend
{
    public function getBinary(): ?string
    {
        $binaryPath = self::getDirectory() . '/sc_serv';
        return file_exists($binaryPath)
            ? $binaryPath
            : null;
    }

    public static function getDirectory(): string
    {
        $parentDir = Environment::getInstance()->getParentDirectory();
        $servers = $parentDir . '/servers/shoutcast1';
        if (is_dir($servers)) {
            return $servers;
        }

        return $parentDir . '/storage/shoutcast1';
    }

    public function getVersion(): ?string
    {
        $binary = $this->getBinary();
        if (!$binary) {
            return null;
        }

        $process = new Process([$binary]);
        $process->setWorkingDirectory(dirname($binary));
        $process->setTimeout(5);
        $process->run();

        $output = $process->getOutput() . $process->getErrorOutput();

        return preg_match('/SHOUTcast.*?([\d.]+)/i', $output, $matches)
            ? $matches[1]
            : (file_exists($binary) ? '1.x' : null);
    }

    public function getNowPlaying(Station $station, bool $includeClients = true): Result
    {
        $feConfig = $station->frontend_config;
        $radioPort = $feConfig->port;

        $baseUrl = $this->environment->getLocalUri()
            ->withPort($radioPort);

        $npAdapter = $this->adapterFactory->getShoutcast1Adapter($baseUrl);
        $npAdapter->setAdminPassword($feConfig->admin_pw);

        return $npAdapter->getNowPlaying(null, $includeClients);
    }

    public function getConfigurationPath(Station $station): string
    {
        return $station->getRadioConfigDir() . '/sc_serv.conf';
    }

    public function getCurrentConfiguration(Station $station): string
    {
        $configPath = $station->getRadioConfigDir();
        $frontendConfig = $station->frontend_config;

        // Classic DNAS 1.x key=value conf (not DNAS 2 streamid_/streampath_ blocks).
        $config = [
            'MaxUser' => $frontendConfig->max_listeners ?? 250,
            'Password' => $frontendConfig->source_pw,
            'AdminPassword' => $frontendConfig->admin_pw,
            'PortBase' => $frontendConfig->port,
            'LogFile' => $configPath . '/sc_serv.log',
            'RealTime' => 1,
            'ScreenLog' => 0,
            'ShowLastSongs' => 10,
            'SrcIP' => 'ANY',
            'DestIP' => 'ANY',
            'Yport' => 80,
            'NameLookups' => 0,
            'BanFile' => $this->writeIpBansFile($station, 'sc_serv.ban', "\n"),
            'RipFile' => $configPath . '/sc_serv.rip',
            'AutoDumpUsers' => 0,
            'AutoDumpSourceTime' => 30,
            'TitleFormat' => $station->name ?? 'MStream',
            'PublicServer' => 'default',
            'AllowRelay' => 1,
            'AllowPublicRelay' => 1,
            'MetaInterval' => 32768,
        ];

        $customConf = $this->processCustomConfig($frontendConfig->custom_config);
        if (false !== $customConf) {
            $config = array_merge($config, $customConf);
        }

        $configFileOutput = '';
        foreach ($config as $configKey => $configValue) {
            $configFileOutput .= $configKey . '=' . str_replace("\n", '', (string)$configValue) . "\n";
        }

        return $configFileOutput;
    }

    public function getCommand(Station $station): ?string
    {
        $binary = $this->getBinary();
        if ($binary === null) {
            return null;
        }

        return sprintf(
            '%s %s',
            escapeshellcmd($binary),
            escapeshellarg($this->getConfigurationPath($station))
        );
    }

    public function getAdminUrl(Station $station, ?UriInterface $baseUrl = null): UriInterface
    {
        $publicUrl = $this->getPublicUrl($station, $baseUrl);
        return $publicUrl
            ->withPath($publicUrl->getPath() . '/admin.cgi');
    }

    protected function writeIpBansFile(
        Station $station,
        string $fileName = 'sc_serv.ban',
        string $ipsSeparator = "\n"
    ): string {
        $ips = $this->getBannedIps($station);

        $configDir = $station->getRadioConfigDir();
        $bansFile = $configDir . '/' . $fileName;

        file_put_contents($bansFile, implode($ipsSeparator, $ips));

        return $bansFile;
    }

    public function getLogTypes(Station $station): array
    {
        $stationConfigDir = $station->getRadioConfigDir();

        return [
            new LogType(
                'shoutcast1_log',
                __('SHOUTcast 1 Log'),
                $stationConfigDir . '/sc_serv.log',
                true
            ),
            new LogType(
                'shoutcast1_conf',
                __('SHOUTcast 1 Configuration'),
                $stationConfigDir . '/sc_serv.conf',
                false
            ),
        ];
    }
}
