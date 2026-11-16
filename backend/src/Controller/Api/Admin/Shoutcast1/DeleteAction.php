<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\Shoutcast1;

use App\Controller\SingleActionInterface;
use App\Entity\Api\Status;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\Frontend\Shoutcast1;
use App\Utilities\File;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[OA\Delete(
    path: '/admin/shoutcast1',
    operationId: 'deleteShoutcast1',
    summary: 'Removes the installed SHOUTcast 1 DNAS binary tree.',
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
        $libraryPath = Shoutcast1::getDirectory();
        if (is_dir($libraryPath)) {
            File::clearDirectoryContents($libraryPath);
        }

        return $response->withJson(Status::success());
    }
}
