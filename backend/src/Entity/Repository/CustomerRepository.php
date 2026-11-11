<?php

declare(strict_types=1);

namespace App\Entity\Repository;

use App\Doctrine\Repository;
use App\Entity\Customer;
use App\Entity\Reseller;

/**
 * @extends Repository<Customer>
 */
final class CustomerRepository extends Repository
{
    protected string $entityClass = Customer::class;

    /**
     * @return mixed[]
     */
    public function fetchSelectForReseller(?Reseller $reseller, bool|string $addBlank = false): array
    {
        $select = [];

        if ($addBlank !== false) {
            $select[''] = ($addBlank === true) ? __('Select...') : $addBlank;
        }

        $qb = $this->em->createQueryBuilder()
            ->from($this->entityClass, 'e')
            ->select('e.id')
            ->addSelect('e.name')
            ->orderBy('e.name', 'ASC');

        if (null !== $reseller) {
            $qb->andWhere('e.reseller = :reseller')
                ->setParameter('reseller', $reseller);
        }

        foreach ($qb->getQuery()->getArrayResult() as $result) {
            $select[$result['id']] = $result['name'];
        }

        return $select;
    }
}
