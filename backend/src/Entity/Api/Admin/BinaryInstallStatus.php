<?php

declare(strict_types=1);

namespace App\Entity\Api\Admin;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Api_Admin_BinaryInstallStatus',
    required: ['*'],
    type: 'object'
)]
final readonly class BinaryInstallStatus
{
    public function __construct(
        #[OA\Property]
        public string|null $version,
    ) {
    }
}
