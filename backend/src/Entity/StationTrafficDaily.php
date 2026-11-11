<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interfaces\IdentifiableEntityInterface;
use App\Entity\Interfaces\StationAwareInterface;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;

#[
    OA\Schema(type: 'object'),
    ORM\Entity,
    ORM\Table(name: 'station_traffic_daily'),
    ORM\UniqueConstraint(name: 'station_traffic_daily_unique', columns: ['station_id', 'day']),
    ORM\Index(name: 'idx_station_traffic_day', columns: ['station_id', 'day'])
]
final class StationTrafficDaily implements IdentifiableEntityInterface, StationAwareInterface
{
    use Traits\HasAutoIncrementId;

    #[
        ORM\ManyToOne(inversedBy: 'traffic_daily'),
        ORM\JoinColumn(name: 'station_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')
    ]
    public Station $station;

    public int $station_id {
        get => $this->station->id;
    }

    #[ORM\Column(type: 'date_immutable')]
    public DateTimeImmutable $day;

    #[
        OA\Property(description: 'Estimated outbound audio bytes for the day.'),
        ORM\Column(type: 'bigint')
    ]
    public string $bytes = '0';
}
