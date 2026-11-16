<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\NginxRtmp;

use App\Controller\SingleActionInterface;
use App\Entity\Api\Admin\BinaryInstallStatus;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\Frontend\NginxRtmp;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[OA\Get(
    path: '/admin/nginx_rtmp',
    operationId: 'getNginxRtmp',
    summary: 'Get details about the Nginx RTMP installation.',
    tags: [OpenApi::TAG_ADMIN],
    responses: [
        new OpenApi\Response\Success(
            content: new OA\JsonContent(ref: BinaryInstallStatus::class)
        ),
        new OpenApi\Response\AccessDenied(),
        new OpenApi\Response\GenericError(),
    ]
)]
final readonly class GetAction implements SingleActionInterface
{
    public function __construct(
        private NginxRtmp $adapter,
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        return $response->withJson(
            new BinaryInstallStatus($this->adapter->getVersion())
        );
    }
}
