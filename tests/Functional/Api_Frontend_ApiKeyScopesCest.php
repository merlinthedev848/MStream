<?php

declare(strict_types=1);

namespace Functional;

use App\Enums\GlobalPermissions;
use FunctionalTester;

class Api_Frontend_ApiKeyScopesCest extends CestAbstract
{
    /**
     * @before setupComplete
     * @before login
     */
    public function scopedApiKeyIsRestricted(FunctionalTester $I): void
    {
        $I->wantTo('Ensure API key scopes restrict access below the owning user ACL.');

        $I->sendPost('/api/frontend/account/api-keys', [
            'comment' => 'View-only scoped key',
            'permissions' => [
                'global' => [
                    GlobalPermissions::View->value,
                ],
                'station' => [],
            ],
        ]);
        $I->seeResponseCodeIsSuccessful();

        $scopedKey = $I->grabDataFromResponseByJsonPath('$.key')[0];
        $I->assertNotEmpty($scopedKey);

        // Full suite key (from login) can administer settings.
        $I->sendGet('/api/admin/settings');
        $I->seeResponseCodeIsSuccessful();

        // Scoped key authenticates but lacks settings administration.
        $I->amBearerAuthenticated($scopedKey);
        $I->sendGet('/api/admin/settings');
        $I->seeResponseCodeIs(403);
    }
}
