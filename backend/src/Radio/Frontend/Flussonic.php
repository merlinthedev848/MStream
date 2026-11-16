<?php

declare(strict_types=1);

namespace App\Radio\Frontend;

use App\Entity\Api\LogType;
use App\Entity\Station;
use App\Entity\StationMount;
use App\Environment;
use Psr\Http\Message\UriInterface;

/**
 * Flussonic Media Server — bring-your-own licensed binary adapter.
 *
 * MStream never vendors Flussonic installers or licenses. Operators upload/place
 * their licensed binary under storage/flussonic; we write a minimal public-format
 * station config and supervise the process. Create Service can provision the
 * station record immediately; going on-air requires the BYO binary.
 */
final class Flussonic extends AbstractFrontend
{
    public function getBinary(): ?string
    {
        $candidates = [
            self::getDirectory() . '/bin/flussonic',
            self::getDirectory() . '/flussonic',
            '/opt/flussonic/bin/flussonic',
        ];
        foreach ($candidates as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function getDirectory(): string
    {
        $parentDir = Environment::getInstance()->getParentDirectory();
        $servers = $parentDir . '/servers/flussonic';
        if (is_dir($servers)) {
            return $servers;
        }

        return $parentDir . '/storage/flussonic';
    }

    public function getVersion(): ?string
    {
        $versionFile = self::getDirectory() . '/VERSION';
        if (is_readable($versionFile)) {
            return trim((string)file_get_contents($versionFile)) ?: null;
        }

        return $this->getBinary() !== null ? 'installed' : null;
    }

    public function getConfigurationPath(Station $station): string
    {
        return $station->getRadioConfigDir() . '/flussonic.conf';
    }

    public function getCurrentConfiguration(Station $station): string
    {
        $fe = $station->frontend_config;
        $port = (int)$fe->port;
        $httpPort = $port + 1;
        $adminPw = $fe->admin_pw;
        $sourcePw = $fe->source_pw;
        $configDir = $station->getRadioConfigDir();
        $stationName = $station->name ?? 'mstream';

        $streams = '';
        /** @var StationMount $mount */
        foreach ($station->mounts as $mount) {
            $name = ltrim($mount->name, '/');
            if ('' === $name) {
                $name = 'live';
            }
            $name = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name) ?: 'live';

            $streams .= <<<CONF

stream {$name} {
  # Publish: rtmp://HOST:{$port}/{$name}?password={$sourcePw}
  protocols rtmp;
}
CONF;
        }

        if ('' === $streams) {
            $streams = <<<CONF

stream live {
  protocols rtmp;
}
CONF;
        }

        // Minimal Flussonic-style conf using publicly documented directives only.
        // Operators with full Flussonic licenses may extend via Custom Configuration.
        $custom = trim((string)($fe->custom_config ?? ''));

        return <<<CONF
# MStream Flussonic BYO station config — {$stationName}
# Licensed Flussonic binary required under storage/flussonic (not distributed by MStream).
# HTTP API/UI port: {$httpPort}
# RTMP port: {$port}
# Admin password: {$adminPw}
# Publish password: {$sourcePw}

http {$httpPort};
rtmp {$port};
edit_auth admin {$adminPw};
loglevel notice;
logaccess {$configDir}/flussonic-access.log;
session_log {$configDir}/flussonic-session.log;
{$streams}

{$custom}
CONF;
    }

    public function getCommand(Station $station): ?string
    {
        $binary = $this->getBinary();
        if ($binary === null) {
            return null;
        }

        return sprintf(
            '%s -c %s',
            escapeshellcmd($binary),
            escapeshellarg($this->getConfigurationPath($station))
        );
    }

    public function getAdminUrl(Station $station, ?UriInterface $baseUrl = null): UriInterface
    {
        $baseUrl ??= $this->router->getBaseUrl();
        return $baseUrl
            ->withPort(((int)$station->frontend_config->port) + 1)
            ->withPath('/');
    }

    public function getPublicUrl(Station $station, ?UriInterface $baseUrl = null): UriInterface
    {
        return $this->getAdminUrl($station, $baseUrl);
    }

    public function getLogTypes(Station $station): array
    {
        $stationConfigDir = $station->getRadioConfigDir();

        return [
            new LogType(
                'flussonic_access_log',
                __('Flussonic Access Log'),
                $stationConfigDir . '/flussonic-access.log',
                true
            ),
            new LogType(
                'flussonic_conf',
                __('Flussonic Configuration'),
                $stationConfigDir . '/flussonic.conf',
                false
            ),
        ];
    }
}
