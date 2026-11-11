<?php

declare(strict_types=1);

namespace App\Entity\Api\Account;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Api_Account_DashboardCharts',
    required: ['*'],
    type: 'object'
)]
final readonly class DashboardCharts
{
    /**
     * @param array{metrics: list<mixed>, alt: list<mixed>} $average
     * @param array{metrics: list<mixed>, alt: list<mixed>} $unique
     */
    public function __construct(
        #[OA\Property(
            type: 'object',
            additionalProperties: true,
            description: 'Average listener chart series and accessible alt text.'
        )]
        public array $average,
        #[OA\Property(
            type: 'object',
            additionalProperties: true,
            description: 'Unique listener chart series and accessible alt text.'
        )]
        public array $unique,
    ) {
    }
}
