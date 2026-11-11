<?php

declare(strict_types=1);

namespace App\Entity\Api;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Api_StationProfileOps',
    required: ['*'],
    type: 'object'
)]
final readonly class StationProfileOps
{
    public function __construct(
        #[OA\Property(
            description: 'Number of unplayed AutoDJ queue rows for this station.',
            example: 12
        )]
        public int $queueDepth,
        #[OA\Property(
            description: 'Number of pending (unplayed) listener song requests.',
            example: 3
        )]
        public int $pendingRequests,
        #[OA\Property(
            description: 'Whether a live streamer/DJ is currently connected.',
            example: false
        )]
        public bool $isStreamerLive,
        #[OA\Property(
            description: 'Display name of the currently connected streamer/DJ, if any.',
            example: 'DJ Jazzy Jeff',
            nullable: true
        )]
        public ?string $connectedStreamer,
    ) {
    }
}
