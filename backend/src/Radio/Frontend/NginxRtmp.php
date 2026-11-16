<?php

declare(strict_types=1);

namespace App\Radio\Frontend;

use App\Entity\Api\LogType;
use App\Entity\Station;
use App\Entity\StationMount;
use App\Environment;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;

/**
 * Open Nginx + nginx-rtmp-module live video frontend.
 *
 * MStream does not vendor a proprietary installer. Operators can use a system nginx
 * with the RTMP module, or upload a prebuilt binary under storage/nginx-rtmp
 * (same BYO pattern as SHOUTcast DNAS). Each station gets its own conf + port block
 * so multiple video (and audio) services run concurrently on one host.
 *
 * Port map within the station block:
 *   +0  RTMP ingest
 *   +1  HTTP (HLS / stats)
 */
final class NginxRtmp extends AbstractFrontend
{
    public const int PORT_OFFSET_HTTP = 1;

    public function getBinary(): ?string
    {
        $candidates = [
            self::getDirectory() . '/sbin/nginx',
            self::getDirectory() . '/nginx',
            '/usr/local/nginx-rtmp/sbin/nginx',
            '/usr/sbin/nginx',
            '/usr/local/sbin/nginx',
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
        $servers = $parentDir . '/servers/nginx-rtmp';
        if (is_dir($servers)) {
            return $servers;
        }

        return $parentDir . '/storage/nginx-rtmp';
    }

    public function getVersion(): ?string
    {
        $binary = $this->getBinary();
        if (!$binary) {
            return null;
        }

        $process = new Process([$binary, '-V']);
        $process->run();

        $output = $process->getErrorOutput() ?: $process->getOutput();
        if (!preg_match('/nginx\/([\d.]+)/i', $output, $matches)) {
            return null;
        }

        $version = $matches[1];
        if (!str_contains($output, 'rtmp')) {
            return $version . ' (no rtmp module detected)';
        }

        return $version;
    }

    public function getHttpPort(Station $station): int
    {
        return (int)$station->frontend_config->port + self::PORT_OFFSET_HTTP;
    }

    public function getConfigurationPath(Station $station): string
    {
        return $station->getRadioConfigDir() . '/nginx-rtmp.conf';
    }

    public function getCurrentConfiguration(Station $station): string
    {
        $configDir = $station->getRadioConfigDir();
        $hlsDir = $configDir . '/rtmp-hls';
        $fe = $station->frontend_config;
        $rtmpPort = (int)$fe->port;
        $httpPort = $this->getHttpPort($station);
        $maxListeners = (int)($fe->max_listeners ?? 1000);
        $sourcePw = $fe->source_pw;

        if (!is_dir($hlsDir)) {
            @mkdir($hlsDir, 0o755, true);
        }

        $applications = '';
        $i = 0;
        /** @var StationMount $mount */
        foreach ($station->mounts as $mount) {
            $i++;
            $appName = ltrim($mount->name, '/');
            if ('' === $appName) {
                $appName = 'live';
            }
            $appName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $appName) ?: 'live';

            $applications .= <<<NGINX

        application {$appName} {
            live on;
            record off;
            allow publish all;
            allow play all;

            hls on;
            hls_path {$hlsDir}/{$appName};
            hls_fragment 3s;
            hls_playlist_length 60s;
            hls_nested on;

            meta copy;
        }
NGINX;
        }

        if (0 === $i) {
            $applications = <<<NGINX

        application live {
            live on;
            record off;
            allow publish all;
            allow play all;
            hls on;
            hls_path {$hlsDir}/live;
            hls_fragment 3s;
            hls_playlist_length 60s;
            hls_nested on;
        }
NGINX;
        }

        $pidFile = $configDir . '/nginx-rtmp.pid';
        $errorLog = $configDir . '/nginx-rtmp-error.log';
        $accessLog = $configDir . '/nginx-rtmp-access.log';
        $clientBody = $configDir . '/nginx-client-body';
        if (!is_dir($clientBody)) {
            @mkdir($clientBody, 0o755, true);
        }

        $stationName = addcslashes($station->name ?? 'MStream', '"\\');

        return <<<NGINX
# MStream Nginx-RTMP station config — {$stationName}
# RTMP ingest: rtmp://HOST:{$rtmpPort}/APP/STREAM
# HLS play:    http://HOST:{$httpPort}/hls/APP/STREAM/index.m3u8
# Source password (document for publishers): {$sourcePw}
worker_processes 1;
error_log {$errorLog} warn;
pid {$pidFile};

events {
    worker_connections {$maxListeners};
}

rtmp {
    server {
        listen {$rtmpPort};
        chunk_size 4096;
{$applications}
    }
}

http {
    sendfile off;
    tcp_nopush on;
    directio 512;
    default_type application/octet-stream;
    client_body_temp_path {$clientBody};
    access_log {$accessLog};

    server {
        listen {$httpPort};
        server_name _;

        location /hls {
            types {
                application/vnd.apple.mpegurl m3u8;
                video/mp2t ts;
            }
            alias {$hlsDir};
            add_header Cache-Control no-cache;
            add_header Access-Control-Allow-Origin *;
        }

        location /stat {
            rtmp_stat all;
            rtmp_stat_stylesheet stat.xsl;
        }

        location / {
            return 200 'MStream Nginx-RTMP — {$stationName}\\n';
            add_header Content-Type text/plain;
        }
    }
}
NGINX;
    }

    public function getCommand(Station $station): ?string
    {
        $binary = $this->getBinary();
        if ($binary === null) {
            return null;
        }

        return sprintf(
            '%s -p %s -c %s',
            escapeshellcmd($binary),
            escapeshellarg($station->getRadioConfigDir()),
            escapeshellarg($this->getConfigurationPath($station))
        );
    }

    public function reload(Station $station): void
    {
        $binary = $this->getBinary();
        if ($binary === null) {
            return;
        }

        $process = new Process([
            $binary,
            '-p',
            $station->getRadioConfigDir(),
            '-c',
            $this->getConfigurationPath($station),
            '-s',
            'reload',
        ]);
        $process->run();
    }

    public function getAdminUrl(Station $station, ?UriInterface $baseUrl = null): UriInterface
    {
        $baseUrl ??= $this->router->getBaseUrl();
        return $baseUrl
            ->withPort($this->getHttpPort($station))
            ->withPath('/stat');
    }

    public function getPublicUrl(Station $station, ?UriInterface $baseUrl = null): UriInterface
    {
        $baseUrl ??= $this->router->getBaseUrl();
        return $baseUrl
            ->withPort($this->getHttpPort($station))
            ->withPath('/hls');
    }

    public function getLogTypes(Station $station): array
    {
        $stationConfigDir = $station->getRadioConfigDir();

        return [
            new LogType(
                'nginx_rtmp_error_log',
                __('Nginx-RTMP Error Log'),
                $stationConfigDir . '/nginx-rtmp-error.log',
                true
            ),
            new LogType(
                'nginx_rtmp_access_log',
                __('Nginx-RTMP Access Log'),
                $stationConfigDir . '/nginx-rtmp-access.log',
                true
            ),
            new LogType(
                'nginx_rtmp_conf',
                __('Nginx-RTMP Configuration'),
                $stationConfigDir . '/nginx-rtmp.conf',
                false
            ),
        ];
    }
}
