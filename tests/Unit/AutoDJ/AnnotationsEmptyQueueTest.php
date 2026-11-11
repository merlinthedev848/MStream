<?php

declare(strict_types=1);

namespace Unit\AutoDJ;

use App\Entity\Repository\CustomFieldRepository;
use App\Entity\Repository\StationQueueRepository;
use App\Entity\Station;
use App\Radio\AutoDJ\Annotations;
use Codeception\Test\Unit;
use Mockery;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Regression: empty AutoDJ queue must soft-fail for Liquidsoap nextsong,
 * not throw RuntimeException('Queue is empty!').
 *
 * Queue builder / Scheduler dump fixtures remain the oracle for fill behavior;
 * this covers the annotation handoff when no unsent StationQueue row exists.
 *
 * Uses Mockery (with BypassFinals) for final repository classes — same approach
 * as InMemoryAutoDjHarnessFactory.
 */
final class AnnotationsEmptyQueueTest extends Unit
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAnnotateNextSongReturnsEmptyStringWhenQueueIsEmpty(): void
    {
        $station = new Station();
        $station->name = 'Empty Queue Station';

        $queueRepo = Mockery::mock(StationQueueRepository::class);
        $queueRepo->shouldReceive('getNextToSendToAutoDj')
            ->once()
            ->with($station)
            ->andReturnNull();

        $customFieldRepo = Mockery::mock(CustomFieldRepository::class);
        $customFieldRepo->shouldNotReceive('getCustomFields');

        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldNotReceive('dispatch');

        $annotations = new Annotations(
            $queueRepo,
            $customFieldRepo,
            $eventDispatcher,
        );

        $logger = new Logger('test');
        $handler = new TestHandler(Level::Debug, false);
        $logger->pushHandler($handler);
        $annotations->setLogger($logger);

        $result = $annotations->annotateNextSong($station, asAutoDj: true);

        $this->assertSame('', $result);
        $this->assertTrue(
            $handler->hasWarningThatContains('AutoDJ queue is empty'),
            'Empty-queue soft-fail should be operator-visible in logs.'
        );
    }
}
