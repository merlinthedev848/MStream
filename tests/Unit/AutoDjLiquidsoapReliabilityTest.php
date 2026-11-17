<?php

declare(strict_types=1);

namespace Unit;

use Codeception\Test\Unit;

/**
 * Contract checks for Liquidsoap AutoDJ dead-air soft-fail and live yield.
 */
final class AutoDjLiquidsoapReliabilityTest extends Unit
{
    private string $mstreamLiq;

    private string $configWriter;

    protected function setUp(): void
    {
        parent::setUp();

        $repoRoot = dirname(__DIR__, 2);

        $liqPath = $repoRoot . '/util/docker/stations/liquidsoap/mstream.liq';
        $liq = file_get_contents($liqPath);
        self::assertNotFalse($liq, 'mstream.liq must be readable');
        $this->mstreamLiq = $liq;

        $writerPath = $repoRoot . '/backend/src/Radio/Backend/Liquidsoap/ConfigWriter.php';
        $writer = file_get_contents($writerPath);
        self::assertNotFalse($writer, 'ConfigWriter.php must be readable');
        $this->configWriter = $writer;
    }

    public function testEmptyQueueSoftFailsToNullRequest(): void
    {
        self::assertStringContainsString(
            'if uri == "" then',
            $this->mstreamLiq
        );
        self::assertStringContainsString(
            'AutoDJ queue empty; waiting for nextsong (dead-air fallback active).',
            $this->mstreamLiq
        );
    }

    public function testSafeFallbackProtectsAgainstDeadAir(): void
    {
        self::assertStringContainsString(
            'def mstream.add_fallback(s) =',
            $this->mstreamLiq
        );
        self::assertStringContainsString(
            'fallback(id="safe_fallback"',
            $this->mstreamLiq
        );
        self::assertStringContainsString(
            'settings.mstream.fallback_path()',
            $this->mstreamLiq
        );
    }

    public function testLiveHarborYieldsAutodjWithoutTalkover(): void
    {
        // Live source is preferred; AutoDJ radio is the fallback (yield model).
        self::assertStringContainsString(
            'id="live_fallback"',
            $this->configWriter
        );
        self::assertStringContainsString(
            '[live, radio]',
            $this->configWriter
        );
        self::assertStringContainsString(
            'Skip AutoDJ when entering OR leaving live',
            $this->configWriter
        );
    }
}
