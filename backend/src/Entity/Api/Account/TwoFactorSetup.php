<?php

declare(strict_types=1);

namespace App\Entity\Api\Account;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Api_Account_TwoFactorSetup',
    required: ['*'],
    type: 'object'
)]
final readonly class TwoFactorSetup
{
    public function __construct(
        #[OA\Property(description: 'Base32 TOTP secret for authenticator enrollment.')]
        public string $secret,
        #[OA\Property(description: 'otpauth:// URI for authenticator apps.')]
        public string $totp_uri,
    ) {
    }
}
