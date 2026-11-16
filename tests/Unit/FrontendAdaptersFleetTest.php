<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Radio\Enums\FrontendAdapters;
use App\Radio\Frontend\Flussonic;
use App\Radio\Frontend\Icecast;
use App\Radio\Frontend\NginxRtmp;
use App\Radio\Frontend\Rsas;
use App\Radio\Frontend\Shoutcast;
use App\Radio\Frontend\Shoutcast1;
use App\Radio\Frontend\Wowza;
use Codeception\Attribute\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Multi-engine fleet: Create Service types must map to real adapters.
 */
final class FrontendAdaptersFleetTest extends TestCase
{
    public function testAudioAndVideoPartitionsAreDisjoint(): void
    {
        foreach (FrontendAdapters::cases() as $adapter) {
            if (FrontendAdapters::Remote === $adapter) {
                self::assertFalse($adapter->isAudio());
                self::assertFalse($adapter->isVideo());
                continue;
            }

            self::assertTrue(
                $adapter->isAudio() xor $adapter->isVideo(),
                $adapter->value . ' must be exactly audio or video'
            );
        }
    }

    /**
     * @return iterable<string, array{FrontendAdapters, class-string|null}>
     */
    public static function adapterClassProvider(): iterable
    {
        yield 'icecast' => [FrontendAdapters::Icecast, Icecast::class];
        yield 'shoutcast2' => [FrontendAdapters::Shoutcast, Shoutcast::class];
        yield 'shoutcast1' => [FrontendAdapters::Shoutcast1, Shoutcast1::class];
        yield 'rsas' => [FrontendAdapters::Rsas, Rsas::class];
        yield 'nginx_rtmp' => [FrontendAdapters::NginxRtmp, NginxRtmp::class];
        yield 'flussonic' => [FrontendAdapters::Flussonic, Flussonic::class];
        yield 'wowza' => [FrontendAdapters::Wowza, Wowza::class];
        yield 'remote' => [FrontendAdapters::Remote, null];
    }

    #[DataProvider('adapterClassProvider')]
    public function testGetClassMatchesExpected(
        FrontendAdapters $adapter,
        ?string $expectedClass
    ): void {
        self::assertSame($expectedClass, $adapter->getClass());
    }

    public function testDefaultMountNames(): void
    {
        self::assertSame('/radio.mp3', FrontendAdapters::Icecast->defaultMountName());
        self::assertSame('/radio.mp3', FrontendAdapters::Shoutcast1->defaultMountName());
        self::assertSame('/live', FrontendAdapters::NginxRtmp->defaultMountName());
        self::assertSame('/live', FrontendAdapters::Flussonic->defaultMountName());
    }

    public function testByoFlags(): void
    {
        self::assertFalse(FrontendAdapters::Icecast->isBringYourOwn());
        self::assertTrue(FrontendAdapters::Shoutcast->isBringYourOwn());
        self::assertTrue(FrontendAdapters::Shoutcast1->isBringYourOwn());
        self::assertTrue(FrontendAdapters::Flussonic->isBringYourOwn());
        self::assertTrue(FrontendAdapters::Wowza->isBringYourOwn());
        self::assertTrue(FrontendAdapters::NginxRtmp->isBringYourOwn());
    }
}
