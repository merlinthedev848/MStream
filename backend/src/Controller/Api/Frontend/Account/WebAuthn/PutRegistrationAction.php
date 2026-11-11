<?php

declare(strict_types=1);

namespace App\Controller\Api\Frontend\Account\WebAuthn;

use App\Container\EntityManagerAwareTrait;
use App\Controller\Traits\UsesWebAuthnTrait;
use App\Entity\Api\Status;
use App\Entity\UserPasskey;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Security\WebAuthnPasskey;
use App\Utilities\Types;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[
    OA\Put(
        path: '/frontend/account/webauthn/register',
        operationId: 'putAccountWebAuthnRegister',
        summary: 'Submit a new registration request for WebAuthn.',
        tags: [OpenApi::TAG_ACCOUNTS],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['createResponse'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Laptop'),
                    new OA\Property(
                        property: 'createResponse',
                        type: 'object',
                        additionalProperties: true,
                        description: 'Browser PublicKeyCredential attestation fields (base64).'
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class PutRegistrationAction
{
    use UsesWebAuthnTrait;
    use EntityManagerAwareTrait;

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $user = $request->getUser();

        $webAuthn = $this->getWebAuthn($request);

        $parsedBody = Types::array($request->getParsedBody());
        $challenge = $this->getChallenge($request);

        // Turn the submitted data into a raw passkey.
        $passkeyRaw = $webAuthn->processCreate(
            base64_decode($parsedBody['createResponse']['clientDataJSON'] ?? ''),
            base64_decode($parsedBody['createResponse']['attestationObject'] ?? ''),
            $challenge,
            requireUserVerification: true
        );

        $passkey = WebAuthnPasskey::fromWebAuthnObject($passkeyRaw);

        $record = new UserPasskey(
            $user,
            $parsedBody['name'] ?? 'New Passkey',
            $passkey
        );

        $this->em->persist($record);
        $this->em->flush();

        return $response->withJson(Status::success());
    }
}
