<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations\Media;

use App\Container\EntityManagerAwareTrait;
use App\Entity\Station;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Visual Radio & Video Re-Streaming controller:
 * Ingest live studio feeds via RTMP / WebRTC / RTSP and configure restream targets
 * to YouTube Live, Twitch, and Facebook Live simultaneously.
 */
#[
    OA\Get(
        path: '/station/{station_id}/visual-radio',
        operationId: 'getVisualRadioConfig',
        summary: 'Get visual radio and video restreaming configuration for the station.',
        tags: [OpenApi::TAG_STATIONS],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            new OpenApi\Response\Success(description: 'Visual Radio configuration'),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Post(
        path: '/station/{station_id}/visual-radio',
        operationId: 'setVisualRadioConfig',
        summary: 'Update visual radio and video restreaming configuration for the station.',
        requestBody: new OA\RequestBody(
            description: 'Visual Radio settings',
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'is_enabled',
                        type: 'boolean',
                        description: 'Enable visual radio stream'
                    ),
                    new OA\Property(
                        property: 'rtmp_source_url',
                        type: 'string',
                        description: 'RTMP or RTSP studio camera source ingest URL'
                    ),
                    new OA\Property(
                        property: 'video_bitrate',
                        type: 'integer',
                        description: 'Video encoding bitrate in kbps'
                    ),
                    new OA\Property(
                        property: 'fps',
                        type: 'integer',
                        description: 'Target frames per second (e.g. 30, 60)'
                    ),
                    new OA\Property(
                        property: 'restream_targets',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string', example: 'YouTube Live'),
                                new OA\Property(
                                    property: 'url',
                                    type: 'string',
                                    example: 'rtmp://a.rtmp.youtube.com/live2'
                                ),
                                new OA\Property(property: 'key', type: 'string', example: 'xxxx-xxxx-xxxx-xxxx'),
                                new OA\Property(property: 'is_enabled', type: 'boolean', example: true),
                            ]
                        )
                    ),
                ]
            )
        ),
        tags: [OpenApi::TAG_STATIONS],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            new OpenApi\Response\Success(description: 'Updated visual radio settings'),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class VisualRadioController
{
    use EntityManagerAwareTrait;

    public function getConfig(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $station = $request->getStation();
        $backendConfig = $station->backend_config->toArray();
        $vrConfig = is_array($backendConfig['visual_radio'] ?? null)
            ? $backendConfig['visual_radio']
            : [
                'is_enabled' => false,
                'rtmp_source_url' => '',
                'video_bitrate' => 2500,
                'fps' => 30,
                'restream_targets' => [],
            ];

        return $response->withJson($vrConfig);
    }

    public function setConfig(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $station = $request->getStation();
        $body = (array)$request->getParsedBody();

        $rawConfig = $station->backend_config->toArray();

        $vrConfig = [
            'is_enabled' => (bool)($body['is_enabled'] ?? false),
            'rtmp_source_url' => trim((string)($body['rtmp_source_url'] ?? '')),
            'video_bitrate' => max(500, (int)($body['video_bitrate'] ?? 2500)),
            'fps' => max(15, min(60, (int)($body['fps'] ?? 30))),
            'restream_targets' => is_array($body['restream_targets'] ?? null)
                ? $body['restream_targets']
                : [],
        ];

        $rawConfig['visual_radio'] = $vrConfig;
        $station->backend_config = $rawConfig;
        $station->needs_restart = true;

        $this->em->persist($station);
        $this->em->flush();

        return $response->withJson([
            'success' => true,
            'message' => 'Visual radio configuration saved successfully.',
            'visual_radio' => $vrConfig,
        ]);
    }
}
