<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Station;
use App\Entity\User;
use App\Enums\GlobalPermissions;
use App\Exception\Http\PermissionDeniedException;
use App\Http\ServerRequest;
use Doctrine\ORM\QueryBuilder;

/**
 * MediaCP-like reseller/customer scoping for admin fleet operations.
 * Hosters with Stations/All see everything; reseller users see only their reseller_id.
 */
final class TenancyScope
{
    public function isHostAdmin(ServerRequest $request): bool
    {
        $acl = $request->getAcl();
        return $acl->isAllowed(GlobalPermissions::All)
            || $acl->isAllowed(GlobalPermissions::Stations);
    }

    public function isResellerAdmin(ServerRequest $request): bool
    {
        return $request->getAcl()->isAllowed(GlobalPermissions::ResellerStations);
    }

    public function applyStationListFilter(QueryBuilder $qb, ServerRequest $request, string $alias = 'e'): QueryBuilder
    {
        if ($this->isHostAdmin($request)) {
            return $qb;
        }

        $user = $request->getUser();
        if ($this->isResellerAdmin($request) && null !== $user->reseller) {
            return $qb
                ->andWhere($alias . '.reseller = :tenancy_reseller')
                ->setParameter('tenancy_reseller', $user->reseller);
        }

        // No host/reseller scope → empty list.
        return $qb->andWhere('1 = 0');
    }

    public function assertStationAccessible(ServerRequest $request, Station $station): void
    {
        if ($this->isHostAdmin($request)) {
            return;
        }

        $user = $request->getUser();
        if (
            $this->isResellerAdmin($request)
            && null !== $user->reseller
            && $station->reseller?->id === $user->reseller->id
        ) {
            return;
        }

        throw PermissionDeniedException::create($request);
    }

    public function resellerIdForCreate(ServerRequest $request): ?int
    {
        if ($this->isHostAdmin($request)) {
            return null; // caller may set explicitly
        }

        return $request->getUser()->reseller?->id;
    }
}
