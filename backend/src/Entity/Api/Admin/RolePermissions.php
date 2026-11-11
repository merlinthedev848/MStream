<?php

declare(strict_types=1);

namespace App\Entity\Api\Admin;

use App\Entity\ApiKeyPermission;
use App\Entity\RolePermission;
use App\Enums\GlobalPermissions;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Api_Admin_RolePermissions',
    required: ['*'],
    type: 'object'
)]
final readonly class RolePermissions
{
    /**
     * @param string[] $global
     * @param RoleStationPermission[] $station
     */
    public function __construct(
        #[OA\Property(
            items: new OA\Items(
                type: GlobalPermissions::class,
            ),
        )]
        public array $global,
        #[OA\Property(
            items: new OA\Items(ref: '#/components/schemas/Api_Admin_RoleStationPermission'),
        )]
        public array $station,
    ) {
    }

    /**
     * @param iterable<int, RolePermission|ApiKeyPermission> $permissions
     */
    public static function fromRolePermissions(iterable $permissions): self
    {
        $globalPermissions = [];
        $stationPermissions = [];

        $permissionsByStation = [];

        foreach ($permissions as $permission) {
            $station = $permission->station;
            if (null !== $station) {
                $permissionsByStation[$station->id][] = $permission->action_name;
            } else {
                $globalPermissions[] = $permission->action_name;
            }
        }

        foreach ($permissionsByStation as $stationId => $stationPerms) {
            $stationPermissions[] = new RoleStationPermission(
                $stationId,
                $stationPerms
            );
        }

        return new self(
            $globalPermissions,
            $stationPermissions
        );
    }
}
