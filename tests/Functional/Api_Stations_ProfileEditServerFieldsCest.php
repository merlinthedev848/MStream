<?php

declare(strict_types=1);

namespace Functional;

use App\Acl;
use App\Entity\ApiKey;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\Station;
use App\Entity\User;
use App\Enums\StationPermissions;
use App\Security\SplitToken;
use FunctionalTester;

/**
 * MediaCP-like split: station operators may edit streaming config, but ports and
 * other server-managed fields stay under GlobalPermissions::Stations / All.
 */
class Api_Stations_ProfileEditServerFieldsCest extends CestAbstract
{
    /**
     * @before setupComplete
     */
    public function stationManagerCannotChangePorts(FunctionalTester $I): void
    {
        $I->wantTo('Prevent station managers from changing broadcast ports via profile edit.');

        $station = $this->getTestStation();
        $originalPort = $station->frontend_config->port;
        $originalDjPort = $station->backend_config->dj_port;
        $originalTelnetPort = $station->backend_config->telnet_port;
        $originalMaxListeners = $station->frontend_config->max_listeners;

        $I->assertNotNull($originalPort);

        // Limited station manager: Profile + Broadcasting on this station only.
        $role = new Role();
        $role->name = 'Station Manager';
        $this->em->persist($role);

        $this->em->persist(new RolePermission($role, $station, StationPermissions::Profile));
        $this->em->persist(new RolePermission($role, $station, StationPermissions::Broadcasting));

        $user = new User();
        $user->name = 'Station Manager';
        $user->email = 'station-manager@example.com';
        $user->setNewPassword('StationManagerTests!');
        $user->roles->add($role);
        $user->locale = 'en_US.UTF-8';
        $this->em->persist($user);

        $key = SplitToken::generate();
        $this->em->persist(new ApiKey($user, $key, 'Station Manager Key'));
        $this->em->flush();

        $this->di->get(Acl::class)->reload();
        $I->amBearerAuthenticated((string)$key);
        $I->haveHttpHeader('Content-Type', 'application/json');

        $profileUrl = '/api/station/' . $station->id . '/profile/edit';

        $I->sendGET($profileUrl);
        $I->seeResponseCodeIs(200);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($I->grabResponse(), true, 512, JSON_THROW_ON_ERROR);

        // GET must not expose hoster-managed ports / listener quotas to operators.
        $I->assertArrayNotHasKey('port', $payload['frontend_config'] ?? []);
        $I->assertArrayNotHasKey('max_listeners', $payload['frontend_config'] ?? []);
        $I->assertArrayNotHasKey('dj_port', $payload['backend_config'] ?? []);
        $I->assertArrayNotHasKey('telnet_port', $payload['backend_config'] ?? []);
        // Adapter types stay on GET so Configure renders the correct broadcasting tabs.
        $I->assertArrayHasKey('frontend_type', $payload);
        $I->assertArrayHasKey('backend_type', $payload);

        $payload['frontend_config'] = is_array($payload['frontend_config'] ?? null)
            ? $payload['frontend_config']
            : [];
        $payload['backend_config'] = is_array($payload['backend_config'] ?? null)
            ? $payload['backend_config']
            : [];
        $payload['frontend_config']['port'] = ((int)$originalPort) + 50;
        $payload['frontend_config']['max_listeners'] = 999;
        $payload['backend_config']['dj_port'] = ((int)$originalDjPort) + 50;
        $payload['backend_config']['telnet_port'] = ((int)$originalTelnetPort) + 50;
        $payload['name'] = 'Station Manager Renamed';

        $I->sendPUT($profileUrl, $payload);
        $I->seeResponseCodeIs(200);

        $this->em->clear();
        $reloaded = $this->em->find(Station::class, $station->id);
        $I->assertNotNull($reloaded);

        // Streaming config (name) may change; server-managed ports/quotas must not.
        $I->assertSame('Station Manager Renamed', $reloaded->name);
        $I->assertSame($originalPort, $reloaded->frontend_config->port);
        $I->assertSame($originalMaxListeners, $reloaded->frontend_config->max_listeners);
        $I->assertSame($originalDjPort, $reloaded->backend_config->dj_port);
        $I->assertSame($originalTelnetPort, $reloaded->backend_config->telnet_port);
    }

    /**
     * @before setupComplete
     */
    public function stationAdminCanChangePorts(FunctionalTester $I): void
    {
        $I->wantTo('Allow station administrators to change broadcast ports via profile edit.');

        $station = $this->getTestStation();
        $originalPort = (int)$station->frontend_config->port;
        $newPort = $originalPort + 25;

        $I->haveHttpHeader('Content-Type', 'application/json');

        $profileUrl = '/api/station/' . $station->id . '/profile/edit';
        $I->sendGET($profileUrl);
        $I->seeResponseCodeIs(200);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($I->grabResponse(), true, 512, JSON_THROW_ON_ERROR);
        $payload['frontend_config']['port'] = $newPort;

        $I->sendPUT($profileUrl, $payload);
        $I->seeResponseCodeIs(200);

        $this->em->clear();
        $reloaded = $this->em->find(Station::class, $station->id);
        $I->assertNotNull($reloaded);
        $I->assertSame($newPort, $reloaded->frontend_config->port);
    }
}
