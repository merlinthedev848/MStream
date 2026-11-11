<?php

declare(strict_types=1);

namespace Unit;

use App\Entity\Station;
use App\Event\Radio\WriteLiquidsoapConfiguration;
use App\Radio\Backend\Liquidsoap\ConfigWriter;
use App\Radio\Enums\BackendAdapters;
use App\Radio\Enums\FrontendAdapters;
use App\Tests\Module;
use Codeception\Test\Unit;
use UnitTester;

/**
 * Regression coverage for P2 PR11 harbor live handoff hardening
 * (Liquidsoap #2107 disconnect auto-skip, #2109 live metadata).
 */
final class HarborLiveHandoffConfigTest extends Unit
{
    protected UnitTester $tester;

    private ConfigWriter $configWriter;

    protected function _inject(Module $testsModule): void
    {
        $this->configWriter = $testsModule->container->get(ConfigWriter::class);
    }

    public function testHarborConfigIncludesDisconnectSkipAndMetadataWorkarounds(): void
    {
        $station = new Station();
        $station->name = 'Live Handoff';
        $station->frontend_type = FrontendAdapters::Icecast;
        $station->backend_type = BackendAdapters::Liquidsoap;
        $station->enable_streamers = true;

        $backendConfig = $station->backend_config;
        $backendConfig->dj_port = 8005;
        $backendConfig->dj_buffer = 5;
        $backendConfig->live_broadcast_text = 'Live Broadcast';
        $station->backend_config = $backendConfig;

        $event = new WriteLiquidsoapConfiguration($station, forEditing: true, writeToDisk: false);

        $this->configWriter->writeHarborConfiguration($event);

        $configuration = $event->buildConfiguration();

        self::assertStringContainsString(
            'live = metadata.map(insert_missing, live)',
            $configuration
        );
        self::assertStringContainsString(
            'live.on_metadata(synchronous=true, mstream.store_live_metadata)',
            $configuration
        );
        self::assertStringContainsString(
            'mstream.insert_latest_live_metadata(s)',
            $configuration
        );
        self::assertStringContainsString(
            'live.on_disconnect(synchronous=true, mstream.live_disconnected)',
            $configuration
        );
        self::assertStringContainsString(
            'track_sensitive=true',
            $configuration
        );
        self::assertStringContainsString(
            'if mstream.to_live() and not mstream.live_enabled() then',
            $configuration
        );
        self::assertSame(
            2,
            substr_count($configuration, 'radio_without_live.skip()')
        );
    }

    public function testHarborConfigSkippedWhenStreamersDisabled(): void
    {
        $station = new Station();
        $station->name = 'No Streamers';
        $station->frontend_type = FrontendAdapters::Icecast;
        $station->backend_type = BackendAdapters::Liquidsoap;
        $station->enable_streamers = false;

        $event = new WriteLiquidsoapConfiguration($station, forEditing: true, writeToDisk: false);

        $this->configWriter->writeHarborConfiguration($event);

        self::assertSame('', $event->buildConfiguration());
    }
}
