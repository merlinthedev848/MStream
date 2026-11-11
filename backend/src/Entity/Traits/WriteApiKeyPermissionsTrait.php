<?php

declare(strict_types=1);

namespace App\Entity\Traits;

use App\Acl;
use App\Entity\ApiKey;
use App\Entity\ApiKeyPermission;
use App\Entity\Station;
use App\Entity\User;
use App\Exception\Http\PermissionDeniedException;
use App\Http\ServerRequest;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Persist optional API-key scopes. An empty/omitted permissions set means the key
 * inherits the owning user's full ACL (backward compatible).
 */
trait WriteApiKeyPermissionsTrait
{
    /**
     * @param array{
     *     global?: list<string>,
     *     station?: array<int|string, mixed>
     * }|null $permissions
     */
    protected function writeApiKeyPermissions(
        EntityManagerInterface $em,
        Acl $acl,
        ApiKey $apiKey,
        User $owner,
        ?array $permissions,
        ServerRequest $request
    ): void {
        if (null === $permissions) {
            return;
        }

        foreach ($apiKey->permissions as $existing) {
            $em->remove($existing);
        }
        $em->flush();
        $apiKey->permissions->clear();

        foreach (($permissions['global'] ?? []) as $permName) {
            $permName = (string)$permName;
            if (!$acl->isValidPermission($permName, true)) {
                continue;
            }
            if (!$acl->userAllowed($permName, $owner)) {
                throw new PermissionDeniedException(
                    $request,
                    sprintf(
                        'Cannot grant API key scope the owner does not have: %s',
                        $permName
                    )
                );
            }

            $permRecord = new ApiKeyPermission($apiKey, null, $permName);
            $em->persist($permRecord);
            $apiKey->permissions->add($permRecord);
        }

        foreach (($permissions['station'] ?? []) as $stationId => $stationPerms) {
            if (is_array($stationPerms) && isset($stationPerms['id'])) {
                $stationId = $stationPerms['id'];
                $stationPerms = $stationPerms['permissions'] ?? [];
            }

            if (!is_array($stationPerms)) {
                continue;
            }

            $station = $em->find(Station::class, $stationId);
            if (!($station instanceof Station)) {
                continue;
            }

            foreach ($stationPerms as $permName) {
                $permName = (string)$permName;
                if (!$acl->isValidPermission($permName, false)) {
                    continue;
                }
                if (!$acl->userAllowed($permName, $owner, $station->id)) {
                    throw new PermissionDeniedException(
                        $request,
                        sprintf(
                            'Cannot grant API key station scope the owner does not have: %s',
                            $permName
                        )
                    );
                }

                $permRecord = new ApiKeyPermission($apiKey, $station, $permName);
                $em->persist($permRecord);
                $apiKey->permissions->add($permRecord);
            }
        }
    }
}
