<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\NginxRtmp;

use App\Controller\SingleActionInterface;
use App\Entity\Api\Status;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\Frontend\NginxRtmp;
use App\Service\Flow;
use App\Utilities\File;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

#[OA\Post(
    path: '/admin/nginx_rtmp',
    operationId: 'postNginxRtmp',
    summary: 'Upload a bring-your-own Nginx RTMP archive (MStream does not distribute proprietary binaries).',
    requestBody: new OA\RequestBody(ref: OpenApi::REF_REQUEST_BODY_FLOW_FILE_UPLOAD),
    tags: [OpenApi::TAG_ADMIN],
    responses: [
        new OpenApi\Response\Success(),
        new OpenApi\Response\AccessDenied(),
        new OpenApi\Response\GenericError(),
    ]
)]
final class PostAction implements SingleActionInterface
{
    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $flowResponse = Flow::process($request, $response);
        if ($flowResponse instanceof ResponseInterface) {
            return $flowResponse;
        }

        $baseDir = NginxRtmp::getDirectory();
        File::mkdirIfNotExists($baseDir);

        $fsUtils = new Filesystem();
        $archivePath = $baseDir . '/upload.tar.gz';
        $fsUtils->remove($archivePath);
        $flowResponse->moveTo($archivePath);

        try {
            $cmd = ['tar', 'xvzf', $archivePath];
            $process = new Process($cmd, $baseDir);
            $process->mustRun();
        } finally {
            $fsUtils->remove($archivePath);
        }

        return $response->withJson(Status::success());
    }
}
