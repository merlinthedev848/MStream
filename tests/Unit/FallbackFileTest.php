<?php

declare(strict_types=1);

namespace Unit;

use App\Entity\Station;
use App\Environment;
use App\Radio\FallbackFile;
use Codeception\Test\Unit;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;

/**
 * Dead-air protection: packaged error.mp3 must resolve for AutoDJ fallback.
 */
final class FallbackFileTest extends Unit
{
    public function testDefaultFallbackPathResolvesToPackagedErrorMp3(): void
    {
        $fallback = new FallbackFile();
        $fallback->setEnvironment(new Environment([]));

        $path = $fallback->getDefaultFallbackPath();
        $env = $fallback->getEnvironment();

        if ($env->isDocker()) {
            self::assertSame('/usr/local/share/icecast/web/error.mp3', $path);
        } else {
            self::assertSame($env->getBaseDirectory() . '/resources/error.mp3', $path);
            self::assertFileExists($path);
        }
    }

    public function testStationWithoutCustomFallbackUsesDefaultPath(): void
    {
        $fallback = new FallbackFile();
        $fallback->setEnvironment(new Environment([]));

        $logger = new Logger('test');
        $handler = new TestHandler(Level::Debug, false);
        $logger->pushHandler($handler);
        $fallback->setLogger($logger);

        $station = new Station();
        $station->name = 'Fallback Station';
        $station->fallback_path = null;

        $path = $fallback->getFallbackPathForStation($station);

        self::assertSame($fallback->getDefaultFallbackPath(), $path);
        self::assertFalse($handler->hasWarningRecords());
    }
}
