<?php

declare(strict_types=1);

namespace App\Entity\Api;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Api_StationAudioMeters',
    required: ['*'],
    type: 'object'
)]
final readonly class StationAudioMeters
{
    public function __construct(
        #[OA\Property(
            description: 'Whether live Liquidsoap meter telemetry is currently available.',
            example: true
        )]
        public bool $available,
        #[OA\Property(
            description: 'Left-channel VU (RMS) level in dBFS.',
            example: -12.5
        )]
        public float $vu_l,
        #[OA\Property(
            description: 'Right-channel VU (RMS) level in dBFS.',
            example: -13.1
        )]
        public float $vu_r,
        #[OA\Property(
            description: 'Left-channel sample-peak level in dBFS from the Liquidsoap master bus.',
            example: -1.8
        )]
        public float $peak_l,
        #[OA\Property(
            description: 'Right-channel sample-peak level in dBFS from the Liquidsoap master bus.',
            example: -2.2
        )]
        public float $peak_r,
    ) {
    }

    /**
     * @param array{
     *     available: bool,
     *     vu_l: float,
     *     vu_r: float,
     *     peak_l: float,
     *     peak_r: float
     * } $meters
     */
    public static function fromArray(array $meters): self
    {
        return new self(
            available: $meters['available'],
            vu_l: $meters['vu_l'],
            vu_r: $meters['vu_r'],
            peak_l: $meters['peak_l'],
            peak_r: $meters['peak_r'],
        );
    }
}
