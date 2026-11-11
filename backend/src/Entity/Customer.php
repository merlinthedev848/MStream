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
    ORM\Table(name: 'customer'),
    Attributes\Auditable
]
final class Customer implements Stringable, IdentifiableEntityInterface
{
    use Traits\HasAutoIncrementId;
    use Traits\TruncateStrings;

    #[
        ORM\ManyToOne(inversedBy: 'customers'),
        ORM\JoinColumn(name: 'reseller_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE'),
        Assert\NotNull
    ]
    public Reseller $reseller;

    public int $reseller_id {
        get => $this->reseller->id;
    }

    #[
        OA\Property(example: 'Acme Radio'),
        ORM\Column(length: 200),
        Assert\NotBlank
    ]
    public string $name = '' {
        set => $this->name = $this->truncateString($value, 200);
    }

    #[
        ORM\ManyToOne(targetEntity: User::class),
        ORM\JoinColumn(name: 'owner_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')
    ]
    public ?User $owner_user = null;

    /** @var Collection<int, Station> */
    #[ORM\OneToMany(targetEntity: Station::class, mappedBy: 'customer')]
    public private(set) Collection $stations;

    public function __construct()
    {
        $this->stations = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
