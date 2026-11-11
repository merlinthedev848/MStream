<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations;

use App\Controller\SingleActionInterface;
use App\Entity\Api\StationAudioMeters;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\Adapters;
use App\Radio\Backend\Liquidsoap;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

#[
    OA\Get(
        path: '/station/{station_id}/meters',
        operationId: 'getStationAudioMeters',
        summary: 'Get live VU and sample-peak meters from the Liquidsoap master bus.',
        tags: [OpenApi::TAG_STATIONS_BROADCASTING],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            new OpenApi\Response\Success(
                content: new OA\JsonContent(ref: StationAudioMeters::class)
            ),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final readonly class GetMetersAction implements SingleActionInterface
{
    public function __construct(
        private Adapters $adapters,
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $station = $request->getStation();
        $backend = $this->adapters->getBackendAdapter($station);

        if (!$backend instanceof Liquidsoap) {
            return $response->withJson(
                StationAudioMeters::fromArray(Liquidsoap::silentMeters())
            );
        }

        return $response->withJson(
            StationAudioMeters::fromArray($backend->getMeters($station))
        );
    }
}
