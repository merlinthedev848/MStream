<?php

declare(strict_types=1);

namespace Unit;

use App\Entity\Station;
use App\Entity\StationMount;
use App\Radio\Enums\StreamFormats;
use App\Radio\Frontend\Icecast;
use Codeception\Test\Unit;
use Symfony\Component\Filesystem\Filesystem;

final class IcecastFallbackMountTest extends Unit
{
    private string $webroot;

    private Filesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new Filesystem();
        $this->webroot = sys_get_temp_dir() . '/mstream-icecast-fallback-' . uniqid('', true);
        $this->fs->mkdir($this->webroot);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->webroot);
        parent::tearDown();
    }

    public function testExplicitFallbackMountIsPreserved(): void
    {
        $mount = $this->createMount();
        $mount->fallback_mount = '/custom.mp3';

        self::assertSame(
            '/custom.mp3',
            Icecast::resolveFallbackMount($mount, $this->webroot)
        );
    }

    public function testAutodjUsesPackagedBitrateFallbackWhenPresent(): void
    {
        $mount = $this->createMount();
        $mount->enable_autodj = true;
        $mount->autodj_format = StreamFormats::Mp3;
        $mount->autodj_bitrate = 128;

        $this->fs->dumpFile($this->webroot . '/fallback-[128].mp3', 'audio');

        self::assertSame(
            '/fallback-[128].mp3',
            Icecast::resolveFallbackMount($mount, $this->webroot)
        );
    }

    public function testAutodjFallsBackToErrorMp3WhenBitrateAssetMissing(): void
    {
        $mount = $this->createMount();
        $mount->enable_autodj = true;
        $mount->autodj_format = StreamFormats::Aac;
        $mount->autodj_bitrate = 160;

        self::assertSame(
            '/error.mp3',
            Icecast::resolveFallbackMount($mount, $this->webroot)
        );
    }

    public function testNoFallbackWhenAutodjDisabledAndUnset(): void
    {
        $mount = $this->createMount();
        $mount->enable_autodj = false;
        $mount->fallback_mount = null;

        self::assertNull(
            Icecast::resolveFallbackMount($mount, $this->webroot)
        );
    }

    private function createMount(): StationMount
    {
        return new StationMount(new Station());
    }
}
