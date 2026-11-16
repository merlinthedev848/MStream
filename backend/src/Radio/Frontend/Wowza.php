<?php

declare(strict_types=1);

namespace App\Radio\Frontend;

use App\Entity\Api\LogType;
use App\Entity\Station;
use App\Entity\StationMount;
use App\Environment;
use Psr\Http\Message\UriInterface;

/**
 * Wowza Streaming Engine — bring-your-own licensed install adapter.
 *
 * MStream never vendors Wowza installers, JARs, or licenses. Operators place a
 * working WSE tree under storage/wowza (or servers/wowza). We write a per-station
 * Application.xml for live RTMP/HLS and start via the install's startup script
 * when present. Create Service provisions the fleet record; on-air needs BYO WSE.
 */
final class Wowza extends AbstractFrontend
{
    public function getBinary(): ?string
    {
        $candidates = [
            self::getDirectory() . '/bin/startup.sh',
            self::getDirectory() . '/startup.sh',
            self::getDirectory() . '/Manager/bin/startup.sh',
        ];
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function getDirectory(): string
    {
        $parentDir = Environment::getInstance()->getParentDirectory();
        $servers = $parentDir . '/servers/wowza';
        if (is_dir($servers)) {
            return $servers;
        }

        return $parentDir . '/storage/wowza';
    }

    public function getVersion(): ?string
    {
        $versionFile = self::getDirectory() . '/version.txt';
        if (is_readable($versionFile)) {
            return trim((string)file_get_contents($versionFile)) ?: null;
        }

        return $this->getBinary() !== null ? 'installed' : null;
    }

    public function getConfigurationPath(Station $station): string
    {
        return $station->getRadioConfigDir() . '/Application.xml';
    }

    public function getCurrentConfiguration(Station $station): string
    {
        $fe = $station->frontend_config;
        $stationName = htmlspecialchars($station->name ?? 'MStream', ENT_XML1 | ENT_QUOTES);
        $sourcePw = htmlspecialchars($fe->source_pw, ENT_XML1 | ENT_QUOTES);
        $adminPw = htmlspecialchars($fe->admin_pw, ENT_XML1 | ENT_QUOTES);
        $port = (int)$fe->port;

        $streamNames = [];
        /** @var StationMount $mount */
        foreach ($station->mounts as $mount) {
            $name = ltrim($mount->name, '/');
            $streamNames[] = '' === $name ? 'live' : $name;
        }
        if ([] === $streamNames) {
            $streamNames[] = 'live';
        }
        $streamsComment = htmlspecialchars(implode(', ', $streamNames), ENT_XML1 | ENT_QUOTES);

        // Minimal Application.xml skeleton using the public Wowza application schema shape.
        // Full WSE still requires a licensed install; this file is dropped into the station
        // config dir for the operator to symlink/copy into conf/apps as needed.
        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!--
  MStream Wowza BYO station application — {$stationName}
  Licensed Wowza Streaming Engine required under storage/wowza (not distributed).
  Suggested RTMP port / VHost mapping: {$port}
  Publish password (configure in WSE security): {$sourcePw}
  Admin password reference: {$adminPw}
  Stream names: {$streamsComment}
-->
<Root version="1">
    <Application>
        <Name>{$stationName}</Name>
        <AppType>Live</AppType>
        <Description>MStream provisioned live application</Description>
        <Streams>
            <StreamType>live</StreamType>
            <StorageDir>\${com.wowza.wms.context.VHostConfigHome}/content</StorageDir>
        </Streams>
        <Transcoder>
            <LiveStreamTranscoder>none</LiveStreamTranscoder>
        </Transcoder>
        <DVR>
            <Recorders>dvrrecorder</Recorders>
            <Store>dvrfilestorage</Store>
            <WindowDuration>0</WindowDuration>
        </DVR>
        <HTTPStreamers>cupertinostreaming,mpegdashstreaming</HTTPStreamers>
        <Client>
            <IdleFrequency>-1</IdleFrequency>
            <Access>
                <StreamReadAccess>*</StreamReadAccess>
                <StreamWriteAccess>*</StreamWriteAccess>
                <StreamAudioSampleAccess></StreamAudioSampleAccess>
                <StreamVideoSampleAccess></StreamVideoSampleAccess>
            </Access>
        </Client>
    </Application>
</Root>
XML;
    }

    public function getCommand(Station $station): ?string
    {
        $binary = $this->getBinary();
        if ($binary === null) {
            return null;
        }

        // Wowza is typically a shared JVM service; per-station supervisor start is best-effort.
        // Operators often run one WSE and map applications — command still enables "installed" detection.
        return sprintf(
            '%s',
            escapeshellcmd($binary)
        );
    }

    public function getAdminUrl(Station $station, ?UriInterface $baseUrl = null): UriInterface
    {
        $baseUrl ??= $this->router->getBaseUrl();
        // Wowza Manager defaults to 8088 in many installs; keep station port as the public hint.
        return $baseUrl
            ->withPort((int)$station->frontend_config->port)
            ->withPath('/enginemanager');
    }

    public function getPublicUrl(Station $station, ?UriInterface $baseUrl = null): UriInterface
    {
        $baseUrl ??= $this->router->getBaseUrl();
        $mount = '/live';
        foreach ($station->mounts as $m) {
            $mount = $m->name;
            break;
        }

        return $baseUrl
            ->withPort((int)$station->frontend_config->port)
            ->withPath('/' . ltrim($mount, '/'));
    }

    public function getLogTypes(Station $station): array
    {
        $stationConfigDir = $station->getRadioConfigDir();

        return [
            new LogType(
                'wowza_application_xml',
                __('Wowza Application Configuration'),
                $stationConfigDir . '/Application.xml',
                false
            ),
        ];
    }
}
