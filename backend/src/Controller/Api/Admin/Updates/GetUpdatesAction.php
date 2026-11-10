<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\Updates;

use App\Container\SettingsAwareTrait;
use App\Controller\SingleActionInterface;
use App\Entity\Api\Admin\UpdateDetails;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Service\MStreamCentral;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[OA\Get(
    path: '/admin/updates',
    operationId: 'getUpdateStatus',
    summary: 'Show information about this installation and its update status.',
    tags: [OpenApi::TAG_ADMIN],
    responses: [
        new OpenApi\Response\Success(
            content: new OA\JsonContent(
                ref: UpdateDetails::class
            )
        ),
        new OpenApi\Response\AccessDenied(),
        new OpenApi\Response\GenericError(),
    ]
)]
final class GetUpdatesAction implements SingleActionInterface
{
    use SettingsAwareTrait;

    public function __construct(
        private readonly MStreamCentral $mstreamCentral
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $settings = $this->readSettings();

        $updates = $this->mstreamCentral->checkForUpdates();

        $settings->update_results = $updates;
        $this->writeSettings($settings);

        return $response->withJson($updates);
    }
}
