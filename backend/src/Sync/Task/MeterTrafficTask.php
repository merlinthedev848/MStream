<?php

declare(strict_types=1);

namespace App\Sync\Task;

use App\Entity\Repository\StationTrafficRepository;
use App\Entity\Station;
use App\Entity\StationHlsStream;
use App\Entity\StationMount;
use App\Entity\StationRemote;
use App\Utilities\Time;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Estimate outbound listener traffic from listener session seconds × stream bitrate.
 * Persists daily totals on station_traffic_daily and soft-suspends over-quota stations.
 */
final class MeterTrafficTask extends AbstractTask
{
    public const int DEFAULT_BITRATE_KBPS = 128;

    public function __construct(
        private readonly StationTrafficRepository $trafficRepo,
    ) {
    }

    public static function getSchedulePattern(): string
    {
        return '20 * * * *';
    }

    public static function isLongTask(): bool
    {
        return true;
    }

    public function run(bool $force = false): void
    {
        $now = Time::nowUtc();
        // Recompute yesterday every run; also refresh today for live progress.
        $days = [
            $now->subDay()->startOfDay(),
            $now->startOfDay(),
        ];

        foreach ($this->iterateStations() as $station) {
            /** @var Station $station */
            try {
                foreach ($days as $day) {
                    $bytes = $this->estimateDayBytes($station, $day);
                    $this->trafficRepo->upsertDailyBytes($station, $day->toDateTimeImmutable(), $bytes);
                }
                $this->em->flush();

                $this->enforceQuota($station, $now);
            } catch (Throwable $e) {
                $this->logger->error(
                    sprintf('Traffic metering failed for station %s: %s', $station->name, $e->getMessage()),
                    ['exception' => $e]
                );
            }
        }
    }

    private function estimateDayBytes(Station $station, CarbonImmutable $dayStart): int
    {
        $dayEnd = $dayStart->addDay();

        $conn = $this->em->getConnection();

        $rows = $conn->fetchAllAssociative(
            <<<'SQL'
                SELECT
                    l.mount_id,
                    l.remote_id,
                    l.hls_stream_id,
                    SUM(
                        TIMESTAMPDIFF(
                            SECOND,
                            GREATEST(l.timestamp_start, :day_start),
                            LEAST(COALESCE(l.timestamp_end, :day_end), :day_end)
                        )
                    ) AS listener_seconds
                FROM listener l
                WHERE l.station_id = :station_id
                  AND l.timestamp_start < :day_end
                  AND (l.timestamp_end IS NULL OR l.timestamp_end > :day_start)
                GROUP BY l.mount_id, l.remote_id, l.hls_stream_id
            SQL,
            [
                'station_id' => $station->id,
                'day_start' => $dayStart->format('Y-m-d H:i:s.u'),
                'day_end' => $dayEnd->format('Y-m-d H:i:s.u'),
            ]
        );

        $bitRates = $this->buildBitrateMap($station);
        $totalBytes = 0;

        foreach ($rows as $row) {
            $seconds = max(0, (int)($row['listener_seconds'] ?? 0));
            if ($seconds === 0) {
                continue;
            }

            $kbps = self::DEFAULT_BITRATE_KBPS;
            if (!empty($row['mount_id']) && isset($bitRates['mount'][(int)$row['mount_id']])) {
                $kbps = $bitRates['mount'][(int)$row['mount_id']];
            } elseif (!empty($row['remote_id']) && isset($bitRates['remote'][(int)$row['remote_id']])) {
                $kbps = $bitRates['remote'][(int)$row['remote_id']];
            } elseif (!empty($row['hls_stream_id']) && isset($bitRates['hls'][(int)$row['hls_stream_id']])) {
                $kbps = $bitRates['hls'][(int)$row['hls_stream_id']];
            }

            // bytes = seconds × kbps × 1000 / 8
            $totalBytes += (int)round($seconds * $kbps * 1000 / 8);
        }

        return $totalBytes;
    }

    /**
     * @return array{mount: array<int, int>, remote: array<int, int>, hls: array<int, int>}
     */
    private function buildBitrateMap(Station $station): array
    {
        $map = [
            'mount' => [],
            'remote' => [],
            'hls' => [],
        ];

        foreach ($station->mounts as $mount) {
            /** @var StationMount $mount */
            $map['mount'][$mount->id] = max(1, (int)($mount->autodj_bitrate ?? self::DEFAULT_BITRATE_KBPS));
        }
        foreach ($station->remotes as $remote) {
            /** @var StationRemote $remote */
            $map['remote'][$remote->id] = max(1, (int)($remote->autodj_bitrate ?? self::DEFAULT_BITRATE_KBPS));
        }
        foreach ($station->hls_streams as $hls) {
            /** @var StationHlsStream $hls */
            $map['hls'][$hls->id] = max(1, (int)($hls->bitrate ?? self::DEFAULT_BITRATE_KBPS));
        }

        return $map;
    }

    private function enforceQuota(Station $station, CarbonImmutable $now): void
    {
        if ($station->max_traffic_mb <= 0) {
            return;
        }

        $usedMb = $this->trafficRepo->getMonthMegabytes($station, $now->toDateTimeImmutable());
        if ($usedMb <= $station->max_traffic_mb) {
            return;
        }

        if (!$station->is_enabled) {
            return;
        }

        $this->logger->warning(
            sprintf(
                'Station "%s" exceeded monthly traffic quota (%.1f / %d MB); suspending broadcasting.',
                $station->name,
                $usedMb,
                $station->max_traffic_mb
            )
        );

        $station->is_enabled = false;
        $this->em->persist($station);
        $this->em->flush();
    }
}
