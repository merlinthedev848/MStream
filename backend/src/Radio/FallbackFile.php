<?php

declare(strict_types=1);

namespace App\Radio;

use App\Container\EnvironmentAwareTrait;
use App\Container\LoggerAwareTrait;
use App\Entity\Station;
use App\Flysystem\StationFilesystems;

final class FallbackFile
{
    use EnvironmentAwareTrait;
    use LoggerAwareTrait;

    public function getFallbackPathForStation(Station $station): string
    {
        $stationFallback = $station->fallback_path;
        if (!empty($stationFallback)) {
            $fsConfig = StationFilesystems::buildConfigFilesystem($station);
            if ($fsConfig->fileExists($stationFallback)) {
                return $fsConfig->getLocalPath($stationFallback);
            }

            $this->logger->warning(
                'Station custom fallback audio is missing; using packaged error.mp3 for dead-air protection.',
                [
                    'station' => (string) $station,
                    'fallback_path' => $stationFallback,
                ]
            );
        }

        return $this->getDefaultFallbackPath();
    }

    public function getDefaultFallbackPath(): string
    {
        return $this->environment->isDocker()
            ? '/usr/local/share/icecast/web/error.mp3'
            : $this->environment->getBaseDirectory() . '/resources/error.mp3';
    }
}
