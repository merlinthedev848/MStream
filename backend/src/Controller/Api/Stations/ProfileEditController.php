<?php

declare(strict_types=1);

namespace App\Controller\Api\Stations;

use App\Controller\Api\Admin\StationsController;
use App\Entity\Api\Status;
use App\Entity\Interfaces\EntityGroupsInterface;
use App\Entity\StationBackendConfiguration;
use App\Enums\GlobalPermissions;
use App\Enums\StationPermissions;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

/**
 * This controller handles the specific "Edit Profile" function on a station's profile, which has different permissions
 * and possible actions than the Admin Station Edit function.
 *
 * MediaCP-like split: station operators may configure streaming service options, but ports, adapter selection,
 * listener quotas, and other server-managed fields require GlobalPermissions::Stations (or All).
 */
#[
    OA\Get(
        path: '/station/{station_id}/profile/edit',
        operationId: 'getStationProfileEdit',
        summary: 'Get the editable profile for the current station.',
        tags: [OpenApi::TAG_STATIONS],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            // TODO API Response Body
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Put(
        path: '/station/{station_id}/profile/edit',
        operationId: 'putStationProfileEdit',
        summary: 'Save the station profile for the current station.',
        tags: [OpenApi::TAG_STATIONS],
        parameters: [
            new OA\Parameter(ref: OpenApi::REF_STATION_ID_REQUIRED),
        ],
        responses: [
            // TODO API Response Body
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class ProfileEditController extends StationsController
{
    public function getProfileAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $station = $request->getStation();

        return $response->withJson(
            $this->toArray($station, $this->getContext($request))
        );
    }

    public function putProfileAction(
        ServerRequest $request,
        Response $response
    ): ResponseInterface {
        $requestBody = (array)$request->getParsedBody();
        $acl = $request->getAcl();

        // Explicitly filter out server-managed fields when the caller is not a station/fleet admin.
        if (!$acl->isAllowed(GlobalPermissions::Stations)) {
            $requestBody = $this->stripServerManagedFields($requestBody);
        }

        $this->editRecord($requestBody, $request->getStation(), $this->getContext($request));

        return $response->withJson(Status::updated());
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function stripServerManagedFields(array $body): array
    {
        unset(
            $body['frontend_type'],
            $body['backend_type'],
            $body['is_enabled'],
            $body['radio_base_dir'],
            $body['max_bitrate'],
            $body['max_mounts'],
            $body['max_hls_streams'],
            $body['media_storage_location'],
            $body['recordings_storage_location'],
            $body['podcasts_storage_location'],
        );

        if (isset($body['frontend_config']) && is_array($body['frontend_config'])) {
            unset(
                $body['frontend_config']['port'],
                $body['frontend_config']['max_listeners'],
                $body['frontend_config']['custom_config'],
            );
        }

        if (isset($body['backend_config']) && is_array($body['backend_config'])) {
            unset(
                $body['backend_config']['dj_port'],
                $body['backend_config']['telnet_port'],
                $body['backend_config'][StationBackendConfiguration::CUSTOM_TOP],
                $body['backend_config'][StationBackendConfiguration::CUSTOM_PRE_PLAYLISTS],
                $body['backend_config'][StationBackendConfiguration::CUSTOM_PRE_LIVE],
                $body['backend_config'][StationBackendConfiguration::CUSTOM_PRE_FADE],
                $body['backend_config'][StationBackendConfiguration::CUSTOM_PRE_BROADCAST],
                $body['backend_config'][StationBackendConfiguration::CUSTOM_BOTTOM],
            );
        }

        return $body;
    }

    private function getContext(ServerRequest $request): array
    {
        $context = [
            AbstractNormalizer::GROUPS => [
                EntityGroupsInterface::GROUP_ID,
                EntityGroupsInterface::GROUP_GENERAL,
            ],
        ];

        $acl = $request->getAcl();
        $station = $request->getStation();

        // Streaming service config (crossfade, bans, streamer enable, etc.) — not Profile-only.
        if ($acl->isAllowed(StationPermissions::Broadcasting, $station)) {
            $context[AbstractNormalizer::GROUPS][] = EntityGroupsInterface::GROUP_BROADCAST_MANAGER;
        }

        // Ports, adapter type, storage, quotas, and other server-managed fields.
        if ($acl->isAllowed(GlobalPermissions::Stations)) {
            $context[AbstractNormalizer::GROUPS][] = EntityGroupsInterface::GROUP_ALL;
        }

        return $context;
    }
}
