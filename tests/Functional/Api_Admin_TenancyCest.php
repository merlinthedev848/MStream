<?php

declare(strict_types=1);

namespace Functional;

use App\Acl;
use App\Entity\ApiKey;
use App\Entity\Customer;
use App\Entity\Reseller;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\User;
use App\Enums\GlobalPermissions;
use App\Security\SplitToken;
use FunctionalTester;

class Api_Admin_TenancyCest extends CestAbstract
{
    /**
     * @before setupComplete
     * @before login
     */
    public function resellerCustomerCrudAndStationScope(FunctionalTester $I): void
    {
        $I->wantTo('Create reseller/customer tenancy and scope stations for reseller admins.');

        $I->haveHttpHeader('Content-Type', 'application/json');

        $I->sendPost('/api/admin/resellers', [
            'name' => 'Partner Co',
        ]);
        $I->seeResponseCodeIs(200);
        $resellerId = (int)$I->grabDataFromResponseByJsonPath('$.id')[0];

        $I->sendPost('/api/admin/customers', [
            'name' => 'Partner Customer',
            'reseller' => $resellerId,
        ]);
        $I->seeResponseCodeIs(200);
        $customerId = (int)$I->grabDataFromResponseByJsonPath('$.id')[0];
        $I->seeResponseContainsJson([
            'name' => 'Partner Customer',
            'reseller' => $resellerId,
        ]);

        /** @var Reseller $reseller */
        $reseller = $this->em->find(Reseller::class, $resellerId);
        /** @var Customer $customer */
        $customer = $this->em->find(Customer::class, $customerId);

        $testStation = $this->getTestStation();
        $scopedName = $testStation->name;
        $testStation->reseller = $reseller;
        $testStation->customer = $customer;
        $this->em->persist($testStation);

        // Create an out-of-scope station via the admin API (full create path).
        $I->sendPost('/api/admin/stations', [
            'name' => 'Other Host Station',
        ]);
        $I->seeResponseCodeIs(200);

        $role = new Role();
        $role->name = 'Reseller Admin Role';
        $this->em->persist($role);

        $this->em->persist(new RolePermission($role, null, GlobalPermissions::ResellerStations));
        $this->em->persist(new RolePermission($role, null, GlobalPermissions::View));

        $resellerUser = new User();
        $resellerUser->email = 'reseller-admin@example.com';
        $resellerUser->name = 'Reseller Admin';
        $resellerUser->setNewPassword('ResellerPass123!');
        $resellerUser->reseller = $reseller;
        $resellerUser->roles->add($role);
        $this->em->persist($resellerUser);
        $this->em->flush();

        // Authenticate as the reseller admin via API key.
        $key = SplitToken::generate();
        $apiKey = new ApiKey($resellerUser, $key, 'Reseller Test');
        $this->em->persist($apiKey);
        $this->em->flush();

        $this->di->get(Acl::class)->reload();
        $I->amBearerAuthenticated((string)$key);

        $I->sendGet('/api/admin/stations', [
            'rowCount' => 0,
        ]);
        $I->seeResponseCodeIs(200);
        $rows = $I->grabDataFromResponseByJsonPath('$.rows')[0];
        $I->assertIsArray($rows);
        $names = array_map(static fn(array $row) => $row['name'] ?? null, $rows);
        $I->assertContains($scopedName, $names);
        $I->assertNotContains('Other Host Station', $names);
    }
}
