<?php

declare(strict_types=1);

namespace Functional;

use App\Entity\Listener;
use App\Entity\ListenerDevice;
use App\Entity\ListenerLocation;
use App\Entity\StationTrafficDaily;
use App\Sync\Task\MeterTrafficTask;
use App\Utilities\Time;
use DateTimeImmutable;
use FunctionalTester;

class Api_Stations_TrafficMeteringCest extends CestAbstract
{
    /**
     * @before setupComplete
     * @before login
     */
    public function metersListenerSecondsIntoDailyTraffic(FunctionalTester $I): void
    {
        $I->wantTo('Meter listener session seconds into station_traffic_daily and expose used MB.');

        $station = $this->getTestStation();
        $station->max_traffic_mb = 10240;
        $this->em->persist($station);

        $mount = $station->mounts->first() ?: null;
        $dayStart = Time::nowUtc()->subDay()->startOfDay();
        $start = $dayStart->addHours(1)->toDateTimeImmutable();
        $end = $dayStart->addHours(2)->toDateTimeImmutable(); // 3600 seconds

        $listener = new Listener(
            $station,
            $mount ?: null,
            null,
            null,
            1,
            '127.0.0.1',
            'FunctionalTestAgent',
            md5('functional-traffic'),
            $start,
            $end,
            ListenerLocation::fromArray(['description' => 'Unknown']),
            ListenerDevice::fromArray(['client' => 'Unknown'])
        );
        $this->em->persist($listener);
        $this->em->flush();

        /** @var MeterTrafficTask $task */
        $task = $this->di->get(MeterTrafficTask::class);
        $task->run(true);

        $this->em->clear();
        $station = $this->getTestStation();

        $row = $this->em->getRepository(StationTrafficDaily::class)->findOneBy([
            'station' => $station,
            'day' => $dayStart->toDateTimeImmutable(),
        ]);
        $I->assertInstanceOf(StationTrafficDaily::class, $row);

        $bitrate = MeterTrafficTask::DEFAULT_BITRATE_KBPS;
        if ($mount) {
            $bitrate = max(1, (int)($mount->autodj_bitrate ?? $bitrate));
        }
        $expected = (int)round(3600 * $bitrate * 1000 / 8);
        $I->assertSame((string)$expected, $row->bytes);

        $I->sendGet('/api/station/' . $station->id . '/dashboard');
        $I->seeResponseCodeIs(200);
        $used = (float)$I->grabDataFromResponseByJsonPath('$.trafficUsedMb')[0];
        $I->assertGreaterThan(0.0, $used);
        $I->assertSame(10240, (int)$I->grabDataFromResponseByJsonPath('$.maxTrafficMb')[0]);
    }
}
