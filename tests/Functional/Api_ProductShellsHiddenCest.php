<?php

declare(strict_types=1);

namespace Functional;

use FunctionalTester;

/**
 * Incomplete product shells (Cluster / VAST / Visual Radio) are intentionally
 * unregistered until engine-backed implementations exist. Lock that hide policy.
 */
class Api_ProductShellsHiddenCest extends CestAbstract
{
    /**
     * @before setupComplete
     */
    public function incompleteProductShellsReturnNotFound(FunctionalTester $I): void
    {
        $I->wantTo('Confirm incomplete Cluster, VAST, and Visual Radio APIs are hidden.');

        $station = $this->getTestStation();
        $stationId = $station->id;

        $I->sendGET('/api/station/' . $stationId . '/vast');
        $I->seeResponseCodeIs(404);

        $I->sendGET('/api/station/' . $stationId . '/visual-radio');
        $I->seeResponseCodeIs(404);

        $I->sendPOST('/api/station/' . $stationId . '/visual-radio', [
            'is_enabled' => true,
        ]);
        $I->seeResponseCodeIs(404);

        $I->sendGET('/api/admin/cluster/nodes');
        $I->seeResponseCodeIs(404);

        $I->sendPOST('/api/admin/cluster/nodes/heartbeat', [
            'node_id' => 'edge-test-1',
            'base_url' => 'https://relay.example.com',
        ]);
        $I->seeResponseCodeIs(404);
    }
}
