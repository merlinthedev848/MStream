<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\NginxRtmp;

use App\Controller\SingleActionInterface;
use App\Entity\Api\Status;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\Frontend\NginxRtmp;
use App\Utilities\File;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[OA\Delete(
    path: '/admin/nginx_rtmp',
    operationId: 'deleteNginxRtmp',
    summary: 'Removes the installed Nginx RTMP binary tree.',
    tags: [OpenApi::TAG_ADMIN],
    responses: [
        new OpenApi\Response\Success(),
        new OpenApi\Response\AccessDenied(),
        new OpenApi\Response\GenericError(),
    ]
)]
final class DeleteAction implements SingleActionInterface
{
    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $libraryPath = NginxRtmp::getDirectory();
        if (is_dir($libraryPath)) {
            File::clearDirectoryContents($libraryPath);
        }

        return $response->withJson(Status::success());
    }
}
