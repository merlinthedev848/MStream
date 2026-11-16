<?php

declare(strict_types=1);

namespace App\Service;

use App\Acl;
use App\Doctrine\ReloadableEntityManagerInterface;
use App\Entity\Customer;
use App\Entity\Reseller;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Entity\Station;
use App\Entity\User;
use App\Enums\GlobalPermissions;
use App\Enums\StationPermissions;
use InvalidArgumentException;
use RuntimeException;

/**
 * MediaCP-like customer account helpers: login user + station operator role.
 * Observation-only UX parity — no MediaCP code cloned.
 */
final class CustomerAccountService
{
    public const string ROLE_PREFIX = 'Customer:';

    public function __construct(
        private readonly ReloadableEntityManagerInterface $em,
        private readonly Acl $acl,
    ) {
    }

    public function ensureHostReseller(): Reseller
    {
        $repo = $this->em->getRepository(Reseller::class);

        $host = $repo->findOneBy(['name' => Reseller::DEFAULT_NAME]);
        if ($host instanceof Reseller) {
            return $host;
        }

        $any = $repo->findOneBy([]);
        if ($any instanceof Reseller) {
            return $any;
        }

        $reseller = new Reseller();
        $reseller->name = Reseller::DEFAULT_NAME;
        $this->em->persist($reseller);
        $this->em->flush();

        return $reseller;
    }

    /**
     * Create (or attach) a panel login for a customer and grant station console access.
     *
     * @return array{user: User, role: Role, generated_password: string|null}
     */
    public function provisionLogin(
        Customer $customer,
        string $email,
        ?string $password = null,
        ?string $displayName = null,
    ): array {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email is required for customer login.');
        }

        $generatedPassword = null;
        if ($password === null || trim($password) === '') {
            $generatedPassword = bin2hex(random_bytes(8));
            $password = $generatedPassword;
        }

        $userRepo = $this->em->getRepository(User::class);
        $existing = $userRepo->findOneBy(['email' => $email]);

        if ($existing instanceof User) {
            $user = $existing;
            if ($generatedPassword === null && trim((string)$password) !== '') {
                $user->setNewPassword($password);
            }
            if ($displayName !== null && trim($displayName) !== '') {
                $user->name = trim($displayName);
            }
        } else {
            $user = new User();
            $user->email = $email;
            $user->name = (null !== $displayName && trim($displayName) !== '')
                ? trim($displayName)
                : $customer->name;
            $user->setNewPassword($password);
            $this->em->persist($user);
        }

        $role = $this->ensureOperatorRole($customer);
        if (!$user->roles->contains($role)) {
            $user->roles->add($role);
        }

        $customer->owner_user = $user;

        $this->em->persist($customer);
        $this->em->persist($user);
        $this->em->flush();

        $this->syncOwnerStationAccess($customer);

        return [
            'user' => $user,
            'role' => $role,
            'generated_password' => $generatedPassword,
        ];
    }

    public function ensureOperatorRole(Customer $customer): Role
    {
        if (!isset($customer->id)) {
            throw new RuntimeException('Customer must be persisted before creating an operator role.');
        }

        $roleName = self::ROLE_PREFIX . $customer->id;
        $roleRepo = $this->em->getRepository(Role::class);
        $role = $roleRepo->findOneBy(['name' => $roleName]);

        if (!$role instanceof Role) {
            $role = new Role();
            $role->name = $roleName;
            $this->em->persist($role);
            $this->em->flush();
        }

        return $role;
    }

    /**
     * Grant the customer owner StationPermissions::All on every service they own.
     * Does not grant any GlobalPermissions (no admin console).
     */
    public function syncOwnerStationAccess(Customer $customer): void
    {
        $owner = $customer->owner_user;
        if (!$owner instanceof User) {
            return;
        }

        $role = $this->ensureOperatorRole($customer);
        if (!$owner->roles->contains($role)) {
            $owner->roles->add($role);
            $this->em->persist($owner);
        }

        foreach ($role->permissions->toArray() as $perm) {
            if ($perm->station !== null) {
                $this->em->remove($perm);
                $role->permissions->removeElement($perm);
            }
        }
        $this->em->flush();

        // Re-query so newly assigned stations are visible even if the
        // in-memory OneToMany collection is stale after Station create/edit.
        /** @var list<Station> $stations */
        $stations = $this->em->getRepository(Station::class)->findBy([
            'customer' => $customer,
        ]);

        foreach ($stations as $station) {
            $perm = new RolePermission($role, $station, StationPermissions::All);
            $this->em->persist($perm);
            $role->permissions->add($perm);
        }

        $this->em->flush();
        $this->acl->reload();
    }

    public function resolveUserLevel(?User $user): string
    {
        if (!$user instanceof User) {
            return 'No Login';
        }

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $perm) {
                if (
                    $perm->station === null
                    && (
                        $perm->action_name === GlobalPermissions::All->value
                        || $perm->action_name === GlobalPermissions::Stations->value
                    )
                ) {
                    return 'Administrator';
                }
            }
        }

        return 'Customer';
    }
}
