<?php

declare(strict_types=1);

namespace App\Entity\Repository;

use App\Doctrine\Repository;
use App\Entity\Station;
use App\Entity\StationTrafficDaily;
use Carbon\CarbonImmutable;
use DateTimeImmutable;

/**
 * @extends Repository<StationTrafficDaily>
 */
final class StationTrafficRepository extends Repository
{
    protected string $entityClass = StationTrafficDaily::class;

    public function upsertDailyBytes(Station $station, DateTimeImmutable $day, int|string $bytes): StationTrafficDaily
    {
        $dayDate = CarbonImmutable::instance($day)->startOfDay()->toDateTimeImmutable();

        $existing = $this->repository->findOneBy([
            'station' => $station,
            'day' => $dayDate,
        ]);

        if ($existing instanceof StationTrafficDaily) {
            $existing->bytes = (string)$bytes;
            $this->em->persist($existing);
            return $existing;
        }

        $row = new StationTrafficDaily();
        $row->station = $station;
        $row->day = $dayDate;
        $row->bytes = (string)$bytes;
        $this->em->persist($row);
        return $row;
    }

    /**
     * Sum of estimated outbound bytes for the UTC calendar month containing $moment.
     */
    public function getMonthBytes(Station $station, DateTimeImmutable $moment): string
    {
        $start = CarbonImmutable::instance($moment)->startOfMonth()->toDateTimeImmutable();
        $end = CarbonImmutable::instance($moment)->endOfMonth()->toDateTimeImmutable();

        $result = $this->em->createQuery(
            <<<'DQL'
                SELECT COALESCE(SUM(t.bytes), 0) FROM App\Entity\StationTrafficDaily t
                WHERE t.station = :station AND t.day BETWEEN :start AND :end
            DQL
        )->setParameter('station', $station)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getSingleScalarResult();

        return (string)$result;
    }

    public function getMonthMegabytes(Station $station, DateTimeImmutable $moment): float
    {
        $bytes = (float)$this->getMonthBytes($station, $moment);
        return $bytes / 1_000_000;
    }
}
