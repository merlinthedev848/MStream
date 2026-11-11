<?php

declare(strict_types=1);

namespace Functional;

use App\Entity\Enums\PlaylistOrders;
use App\Entity\Enums\PlaylistSources;
use App\Entity\Enums\PlaylistTypes;
use App\Entity\Station;
use App\Entity\StationPlaylist;
use App\Radio\Configuration;
use App\Radio\Enums\BackendAdapters;
use App\Radio\Enums\CrossfadeModes;
use FunctionalTester;

class Api_Admin_StationDefaultsCest extends CestAbstract
{
    /**
     * @before setupComplete
     * @before login
     */
    public function newStationsAreAutoDjReadyWithAscendingPorts(FunctionalTester $I): void
    {
        $I->wantTo('Create stations that are AutoDJ-ready with ascending port blocks.');

        $I->haveHttpHeader('Content-Type', 'application/json');

        // Baseline station from setupComplete occupies the first block (typically 8000).
        $baseline = $this->getTestStation();
        $baselinePort = (int)$baseline->frontend_config->port;
        $I->assertNotEmpty($baselinePort);

        $createdIds = [];
        $ports = [];

        foreach (['AutoDJ Alpha', 'AutoDJ Beta'] as $name) {
            $I->sendPost('/api/admin/stations', [
                'name' => $name,
            ]);
            $I->seeResponseCodeIs(200);

            $stationId = (int)$I->grabDataFromResponseByJsonPath('$.id')[0];
            $createdIds[] = $stationId;

            $this->em->clear();

            /** @var Station $station */
            $station = $this->em->find(Station::class, $stationId);
            $I->assertInstanceOf(Station::class, $station);

            $I->assertSame(BackendAdapters::Liquidsoap, $station->backend_type);
            $I->assertTrue($station->is_enabled);
            $I->assertFalse($station->backend_config->use_manual_autodj);
            $I->assertSame(
                CrossfadeModes::Normal->value,
                $station->backend_config->crossfade_type
            );
            $I->assertGreaterThan(0, $station->backend_config->crossfade);
            $I->assertGreaterThanOrEqual(1, $station->backend_config->autodj_queue_length);

            $I->assertNotNull($station->media_storage_location);
            $I->assertNotEmpty($station->media_storage_location->path);

            $defaultPlaylist = null;
            foreach ($station->playlists as $playlist) {
                if (
                    $playlist->is_enabled
                    && PlaylistTypes::default() === $playlist->type
                ) {
                    $defaultPlaylist = $playlist;
                    break;
                }
            }

            $I->assertInstanceOf(StationPlaylist::class, $defaultPlaylist);
            $I->assertSame(Configuration::DEFAULT_PLAYLIST_NAME, $defaultPlaylist->name);
            $I->assertSame(PlaylistSources::Songs, $defaultPlaylist->source);
            $I->assertSame(PlaylistOrders::Shuffle, $defaultPlaylist->order);
            $I->assertGreaterThanOrEqual(1, $defaultPlaylist->weight);

            $hasMediaRootFolder = false;
            foreach ($defaultPlaylist->folders as $folder) {
                if (Configuration::DEFAULT_MEDIA_FOLDER_PATH === $folder->path) {
                    $hasMediaRootFolder = true;
                    break;
                }
            }
            $I->assertTrue($hasMediaRootFolder, 'Default playlist should be wired to the media root.');

            $I->assertGreaterThan(0, $station->mounts->count());
            $defaultMount = $station->mounts->first();
            $I->assertTrue($defaultMount->enable_autodj);
            $I->assertSame('/error.mp3', $defaultMount->fallback_mount);

            $port = (int)$station->frontend_config->port;
            $I->assertSame(
                $port + Configuration::PORT_OFFSET_TELNET,
                (int)$station->backend_config->telnet_port
            );
            $I->assertSame(
                $port + Configuration::PORT_OFFSET_DJ,
                (int)$station->backend_config->dj_port
            );

            $ports[] = $port;
        }

        // Ascending sequence: each new base is higher than the previous by PORT_RANGE_STEP,
        // continuing after the baseline station's block (no random gaps).
        $I->assertSame($baselinePort + Configuration::PORT_RANGE_STEP, $ports[0]);
        $I->assertSame($ports[0] + Configuration::PORT_RANGE_STEP, $ports[1]);

        foreach ($createdIds as $stationId) {
            $I->sendDelete('/api/admin/station/' . $stationId);
            $I->seeResponseCodeIsSuccessful();
        }
    }
}
