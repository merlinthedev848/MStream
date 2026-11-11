<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enums\PermissionInterface;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;

#[
    ORM\Entity(readOnly: true),
    ORM\Table(name: 'api_key_permissions'),
    ORM\UniqueConstraint(name: 'api_key_permission_unique_idx', columns: ['api_key_id', 'action_name', 'station_id'])
]
final class ApiKeyPermission implements
    JsonSerializable,
    Interfaces\IdentifiableEntityInterface
{
    use Traits\HasAutoIncrementId;
    use Traits\TruncateStrings;

    #[ORM\ManyToOne(inversedBy: 'permissions')]
    #[ORM\JoinColumn(name: 'api_key_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    public readonly ApiKey $apiKey;

    public string $api_key_id {
        get => $this->apiKey->id;
    }

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'station_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    public readonly ?Station $station;

    public ?int $station_id {
        get => $this->station?->id;
    }

    #[ORM\Column(length: 50)]
    public readonly string $action_name;

    public function __construct(
        ApiKey $apiKey,
        ?Station $station,
        string|PermissionInterface $actionName
    ) {
        $this->apiKey = $apiKey;
        $this->station = $station;

        if ($actionName instanceof PermissionInterface) {
            $actionName = $actionName->getValue();
        }

        $this->action_name = $this->truncateString($actionName, 50);
    }

    /**
     * @return mixed[]
     */
    public function jsonSerialize(): array
    {
        return [
            'action' => $this->action_name,
            'station_id' => $this->station?->id,
        ];
    }
}
