<?php

declare(strict_types=1);

namespace Functional;

use App\Entity\Enums\PlaylistTypes;
use App\Entity\Station;
use App\Entity\StationPlaylist;
use App\Radio\Configuration;
use App\Radio\Enums\CrossfadeModes;
use FunctionalTester;

class Api_Admin_BillingCest extends CestAbstract
{
    /**
     * @before setupComplete
     * @before login
     */
    public function provisionLifecycle(FunctionalTester $I): void
    {
        $I->wantTo('Provision, suspend, unsuspend, resize, and terminate a station via billing API.');

        $I->haveHttpHeader('Content-Type', 'application/json');

        // Create via billing (must initialize Configuration like admin station create).
        $I->sendPost('/api/admin/billing/provision', [
            'action' => 'create',
            'name' => 'Billing Provisioned Radio',
            'max_listeners' => 50,
            'max_bitrate' => 128,
            'disk_quota_mb' => 256,
        ]);

        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'success' => true,
            'max_listeners' => 50,
            'max_bitrate' => 128,
            'disk_quota_mb' => 256,
        ]);

        $stationId = $I->grabDataFromResponseByJsonPath('$.station_id')[0];
        $shortName = $I->grabDataFromResponseByJsonPath('$.short_name')[0];

        /** @var Station|null $station */
        $station = $this->em->find(Station::class, $stationId);
        $I->assertInstanceOf(Station::class, $station);
        $I->assertNotEmpty($station->adapter_api_key);
        $I->assertNotEmpty($station->frontend_config->port);
        $I->assertGreaterThan(0, $station->mounts->count());
        $I->assertNotNull($station->media_storage_location->storageQuotaBytes);
        $I->assertSame(
            (string)(256 * 1024 * 1024),
            (string)$station->media_storage_location->storageQuotaBytes
        );

        $I->assertFalse($station->backend_config->use_manual_autodj);
        $I->assertSame(CrossfadeModes::Normal->value, $station->backend_config->crossfade_type);

        $defaultPlaylist = null;
        foreach ($station->playlists as $playlist) {
            if (
                Configuration::DEFAULT_PLAYLIST_NAME === $playlist->name
                && PlaylistTypes::default() === $playlist->type
                && $playlist->is_enabled
            ) {
                $defaultPlaylist = $playlist;
                break;
            }
        }
        $I->assertInstanceOf(StationPlaylist::class, $defaultPlaylist);

        $hasMediaRootFolder = $defaultPlaylist->folders->exists(
            static fn(int $key, $folder): bool => Configuration::DEFAULT_MEDIA_FOLDER_PATH === $folder->path
        );
        $I->assertTrue($hasMediaRootFolder);

        $I->assertSame('/error.mp3', $station->mounts->first()->fallback_mount);

        // Status
        $I->sendPost('/api/admin/billing/provision', [
            'action' => 'status',
            'station' => $shortName,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'success' => true,
            'station_id' => $stationId,
            'is_enabled' => true,
            'disk_quota_mb' => 256,
        ]);

        // Suspend stops adapters via Configuration::writeConfiguration (disabled path).
        $I->sendPost('/api/admin/billing/provision', [
            'action' => 'suspend',
            'station' => $stationId,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'success' => true,
            'is_enabled' => false,
        ]);

        $this->em->clear();
        $station = $this->em->find(Station::class, $stationId);
        $I->assertInstanceOf(Station::class, $station);
        $I->assertFalse($station->is_enabled);

        // Unsuspend restarts via writeConfiguration + nginx rewrite.
        $I->sendPost('/api/admin/billing/provision', [
            'action' => 'unsuspend',
            'station' => $stationId,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'success' => true,
            'is_enabled' => true,
        ]);

        // Modify package including disk quota.
        $I->sendPost('/api/admin/billing/provision', [
            'action' => 'modify_package',
            'station' => $stationId,
            'max_listeners' => 100,
            'max_bitrate' => 192,
            'disk_quota_mb' => 512,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson([
            'success' => true,
            'max_listeners' => 100,
            'max_bitrate' => 192,
            'disk_quota_mb' => 512,
        ]);

        // Terminate removes configuration and entity.
        $I->sendPost('/api/admin/billing/provision', [
            'action' => 'terminate',
            'station' => $stationId,
        ]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseContainsJson(['success' => true]);

        $this->em->clear();
        $I->assertNull($this->em->find(Station::class, $stationId));
    }
}
