<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Interfaces\IdentifiableEntityInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use OpenApi\Attributes as OA;
use Stringable;
use Symfony\Component\Validator\Constraints as Assert;

#[
    OA\Schema(type: 'object'),
    ORM\Entity,
    ORM\Table(name: 'reseller'),
    Attributes\Auditable
]
final class Reseller implements Stringable, IdentifiableEntityInterface
{
    use Traits\HasAutoIncrementId;
    use Traits\TruncateStrings;

    public const string DEFAULT_NAME = 'Host';

    #[
        OA\Property(example: 'Acme Reseller'),
        ORM\Column(length: 200),
        Assert\NotBlank
    ]
    public string $name = self::DEFAULT_NAME {
        set => $this->name = $this->truncateString($value, 200);
    }

    /** @var Collection<int, Customer> */
    #[ORM\OneToMany(targetEntity: Customer::class, mappedBy: 'reseller')]
    public private(set) Collection $customers;

    /** @var Collection<int, Station> */
    #[ORM\OneToMany(targetEntity: Station::class, mappedBy: 'reseller')]
    public private(set) Collection $stations;

    public function __construct()
    {
        $this->customers = new ArrayCollection();
        $this->stations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
