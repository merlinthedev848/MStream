<?php

declare(strict_types=1);

namespace Unit;

use App\Doctrine\ReloadableEntityManagerInterface;
use App\Entity\Enums\PlaylistOrders;
use App\Entity\Enums\PlaylistSources;
use App\Entity\Enums\PlaylistTypes;
use App\Entity\Station;
use App\Entity\StationPlaylist;
use App\Entity\StationPlaylistFolder;
use App\Environment;
use App\Radio\Adapters;
use App\Radio\AutoDJ\PlaylistQueueResetter;
use App\Radio\Configuration;
use App\Radio\Enums\BackendAdapters;
use App\Radio\Enums\CrossfadeModes;
use App\Radio\Enums\FrontendAdapters;
use Codeception\Test\Unit;
use Mockery;
use Supervisor\SupervisorInterface;
use Symfony\Component\Filesystem\Filesystem;

final class ConfigurationAutoDjDefaultsTest extends Unit
{
    private string $tempBase;

    private Filesystem $fs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fs = new Filesystem();
        $this->tempBase = sys_get_temp_dir() . '/mstream-autodj-defaults-' . uniqid('', true);
        $this->fs->mkdir($this->tempBase);
    }

    protected function tearDown(): void
    {
        $this->fs->remove($this->tempBase);
        Mockery::close();
        parent::tearDown();
    }

    public function testEnsureAutoDjReadyDefaultsCreatesPlaylistAndMediaRootFolder(): void
    {
        $configuration = $this->createConfiguration();

        $station = new Station();
        $station->name = 'AutoDJ Ready';
        $station->frontend_type = FrontendAdapters::Icecast;
        $station->backend_type = BackendAdapters::Liquidsoap;
        $station->radio_base_dir = $this->tempBase . '/station';

        $configuration->ensureAutoDjReadyDefaults($station);

        self::assertFalse($station->backend_config->use_manual_autodj);
        self::assertSame(CrossfadeModes::Normal->value, $station->backend_config->crossfade_type);
        self::assertSame(2.0, $station->backend_config->crossfade);
        self::assertSame(3, $station->backend_config->autodj_queue_length);

        self::assertCount(1, $station->playlists);

        $playlist = $station->playlists->first();
        self::assertInstanceOf(StationPlaylist::class, $playlist);
        self::assertSame(Configuration::DEFAULT_PLAYLIST_NAME, $playlist->name);
        self::assertTrue($playlist->is_enabled);
        self::assertSame(PlaylistTypes::Standard, $playlist->type);
        self::assertSame(PlaylistSources::Songs, $playlist->source);
        self::assertSame(PlaylistOrders::Shuffle, $playlist->order);
        self::assertSame(StationPlaylist::DEFAULT_WEIGHT, $playlist->weight);

        self::assertCount(1, $playlist->folders);
        $folder = $playlist->folders->first();
        self::assertInstanceOf(StationPlaylistFolder::class, $folder);
        self::assertSame(
            Configuration::DEFAULT_MEDIA_FOLDER_PATH,
            $folder->path
        );
    }

    public function testEnsureAutoDjReadyDefaultsIsIdempotent(): void
    {
        $configuration = $this->createConfiguration();

        $station = new Station();
        $station->name = 'Idempotent';
        $station->frontend_type = FrontendAdapters::Icecast;
        $station->backend_type = BackendAdapters::Liquidsoap;
        $station->radio_base_dir = $this->tempBase . '/idempotent';

        $configuration->ensureAutoDjReadyDefaults($station);
        $configuration->ensureAutoDjReadyDefaults($station);

        self::assertCount(1, $station->playlists);
        $playlist = $station->playlists->first();
        self::assertInstanceOf(StationPlaylist::class, $playlist);
        self::assertCount(1, $playlist->folders);
    }

    private function createConfiguration(): Configuration
    {
        $em = Mockery::mock(ReloadableEntityManagerInterface::class);
        $em->shouldReceive('persist');
        $em->shouldReceive('createQuery')->never();

        $configuration = new Configuration(
            Mockery::mock(Adapters::class),
            Mockery::mock(SupervisorInterface::class),
            Mockery::mock(PlaylistQueueResetter::class),
        );
        $configuration->setEntityManager($em);
        $configuration->setEnvironment(new Environment([]));

        return $configuration;
    }
}
