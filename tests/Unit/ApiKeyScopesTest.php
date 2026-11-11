<?php

declare(strict_types=1);

namespace Unit;

use App\Acl;
use App\Doctrine\ReloadableEntityManagerInterface;
use App\Entity\ApiKey;
use App\Entity\ApiKeyPermission;
use App\Entity\User;
use App\Enums\GlobalPermissions;
use App\Enums\StationPermissions;
use App\Security\SplitToken;
use Codeception\Test\Unit;
use Doctrine\ORM\AbstractQuery;
use Mockery;
use Psr\EventDispatcher\EventDispatcherInterface;

final class ApiKeyScopesTest extends Unit
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testEmptyScopesInheritFullAccountAcl(): void
    {
        $acl = $this->createAcl();
        $apiKey = $this->createApiKey();

        $this->assertTrue($acl->apiKeyAllowed($apiKey, GlobalPermissions::Settings));
        $this->assertTrue($acl->apiKeyAllowed($apiKey, StationPermissions::Media, 1));
    }

    public function testScopedKeyAllowsOnlyGrantedGlobalActions(): void
    {
        $acl = $this->createAcl();
        $apiKey = $this->createApiKey();

        $apiKey->permissions->add(
            new ApiKeyPermission($apiKey, null, GlobalPermissions::View)
        );

        $this->assertTrue($acl->apiKeyAllowed($apiKey, GlobalPermissions::View));
        $this->assertFalse($acl->apiKeyAllowed($apiKey, GlobalPermissions::Settings));
        $this->assertFalse($acl->apiKeyAllowed($apiKey, StationPermissions::Media, 1));
    }

    public function testScopedKeyHonorsGlobalAllShortcut(): void
    {
        $acl = $this->createAcl();
        $apiKey = $this->createApiKey();

        $apiKey->permissions->add(
            new ApiKeyPermission($apiKey, null, GlobalPermissions::All)
        );

        $this->assertTrue($acl->apiKeyAllowed($apiKey, GlobalPermissions::Settings));
        $this->assertTrue($acl->apiKeyAllowed($apiKey, StationPermissions::Broadcasting, 99));
    }

    private function createAcl(): Acl
    {
        $query = Mockery::mock(AbstractQuery::class);
        $query->shouldReceive('toIterable')->andReturn([]);

        $em = Mockery::mock(ReloadableEntityManagerInterface::class);
        $em->shouldReceive('createQuery')->andReturn($query);

        $dispatcher = Mockery::mock(EventDispatcherInterface::class);
        $dispatcher->shouldReceive('dispatch')->andReturnUsing(
            static fn(object $event): object => $event
        );

        return new Acl($em, $dispatcher);
    }

    private function createApiKey(): ApiKey
    {
        $user = new User();
        $user->email = 'scopes-test@example.com';

        return new ApiKey($user, SplitToken::generate(), 'Scoped test key');
    }
}
