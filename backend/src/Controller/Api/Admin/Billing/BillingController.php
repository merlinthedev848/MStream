<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\Billing;

use App\Container\EntityManagerAwareTrait;
use App\Entity\Api\Status;
use App\Entity\Repository\StationRepository;
use App\Entity\Station;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Radio\Enums\BackendAdapters;
use App\Radio\Enums\FrontendAdapters;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * High-performance Webhook and REST API controller for automated hosting,
 * provisioning, and subscription lifecycle management (WHMCS, Blesta, FOSSBilling).
 */
#[
    OA\Post(
        path: '/admin/billing/provision',
        operationId: 'billingProvisionStation',
        summary: 'Provision, suspend, unsuspend, terminate, or resize a station via billing automation.',
        requestBody: new OA\RequestBody(
            description: 'Billing payload with action, station identifier, and package limits',
            required: true,
            content: new OA\JsonContent(
                required: ['action'],
                properties: [
                    new OA\Property(
                        property: 'action',
                        type: 'string',
                        enum: ['create', 'suspend', 'unsuspend', 'terminate', 'modify_package', 'status']
                    ),
                    new OA\Property(
                        property: 'station',
                        type: 'string',
                        description: 'Station ID or short name (required for existing station actions)'
                    ),
                    new OA\Property(
                        property: 'name',
                        type: 'string',
                        description: 'Station name (for create)'
                    ),
                    new OA\Property(
                        property: 'max_listeners',
                        type: 'integer',
                        description: 'Max listener slot quota'
                    ),
                    new OA\Property(
                        property: 'max_bitrate',
                        type: 'integer',
                        description: 'Maximum allowed bitrate in kbps'
                    ),
                    new OA\Property(
                        property: 'disk_quota_mb',
                        type: 'integer',
                        description: 'Maximum storage quota in MB'
                    ),
                ],
            )
        ),
        tags: [OpenApi::TAG_ADMIN],
        responses: [
            new OpenApi\Response\Success(
                content: new OA\JsonContent(ref: Status::class)
            ),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class BillingController
{
    use EntityManagerAwareTrait;

    public function __construct(
        private readonly StationRepository $stationRepo,
    ) {
    }

    public function __invoke(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $data = (array)$request->getParsedBody();
        $action = strtolower(trim((string)($data['action'] ?? '')));

        if (empty($action)) {
            throw new InvalidArgumentException('Billing action is required.');
        }

        return match ($action) {
            'create', 'provision' => $this->handleCreate($data, $response),
            'suspend' => $this->handleSuspend($data, $response, false),
            'unsuspend' => $this->handleSuspend($data, $response, true),
            'terminate', 'delete' => $this->handleTerminate($data, $response),
            'modify_package', 'resize' => $this->handleModifyPackage($data, $response),
            'status' => $this->handleStatus($data, $response),
            default => throw new InvalidArgumentException(sprintf('Unsupported billing action: "%s"', $action)),
        };
    }

    private function handleCreate(array $data, Response $response): ResponseInterface
    {
        $name = trim((string)($data['name'] ?? ''));
        if (empty($name)) {
            throw new InvalidArgumentException('Station name is required for provisioning.');
        }

        $station = new Station();
        $station->name = $name;
        $station->frontend_type = FrontendAdapters::Icecast;
        $station->backend_type = BackendAdapters::Liquidsoap;
        $station->is_enabled = true;

        if (isset($data['max_bitrate'])) {
            $station->max_bitrate = (int)$data['max_bitrate'];
        }

        $feConfig = $station->frontend_config;
        if (isset($data['max_listeners'])) {
            $feConfig->max_listeners = (int)$data['max_listeners'];
        }
        $station->frontend_config = $feConfig;

        $this->em->persist($station);
        $this->em->flush();

        return $response->withJson([
            'success' => true,
            'message' => 'Station provisioned successfully.',
            'station_id' => $station->id,
            'short_name' => $station->short_name,
            'max_listeners' => $station->frontend_config->max_listeners,
            'max_bitrate' => $station->max_bitrate,
        ]);
    }

    private function handleSuspend(array $data, Response $response, bool $enabled): ResponseInterface
    {
        $station = $this->requireStation($data);
        $station->is_enabled = $enabled;
        $station->needs_restart = true;

        $this->em->persist($station);
        $this->em->flush();

        $actionText = $enabled ? 'unsuspended' : 'suspended';
        return $response->withJson([
            'success' => true,
            'message' => sprintf('Station %s successfully.', $actionText),
            'station_id' => $station->id,
            'is_enabled' => $station->is_enabled,
        ]);
    }

    private function handleTerminate(array $data, Response $response): ResponseInterface
    {
        $station = $this->requireStation($data);
        $stationId = $station->id;
        $stationName = $station->name;

        $this->em->remove($station);
        $this->em->flush();

        return $response->withJson([
            'success' => true,
            'message' => sprintf('Station "%s" (ID %d) terminated successfully.', $stationName, $stationId),
        ]);
    }

    private function handleModifyPackage(array $data, Response $response): ResponseInterface
    {
        $station = $this->requireStation($data);

        if (isset($data['max_bitrate'])) {
            $station->max_bitrate = (int)$data['max_bitrate'];
        }

        if (isset($data['max_listeners'])) {
            $feConfig = $station->frontend_config;
            $feConfig->max_listeners = (int)$data['max_listeners'];
            $station->frontend_config = $feConfig;
        }

        $station->needs_restart = true;
        $this->em->persist($station);
        $this->em->flush();

        return $response->withJson([
            'success' => true,
            'message' => 'Station package limits modified successfully.',
            'station_id' => $station->id,
            'max_listeners' => $station->frontend_config->max_listeners,
            'max_bitrate' => $station->max_bitrate,
        ]);
    }

    private function handleStatus(array $data, Response $response): ResponseInterface
    {
        $station = $this->requireStation($data);

        return $response->withJson([
            'success' => true,
            'station_id' => $station->id,
            'name' => $station->name,
            'short_name' => $station->short_name,
            'is_enabled' => $station->is_enabled,
            'has_started' => $station->has_started,
            'max_listeners' => $station->frontend_config->max_listeners,
            'max_bitrate' => $station->max_bitrate,
        ]);
    }

    private function requireStation(array $data): Station
    {
        $identifier = (string)($data['station'] ?? $data['station_id'] ?? '');
        if (empty($identifier)) {
            throw new InvalidArgumentException('Station identifier is required.');
        }

        $station = $this->stationRepo->findByIdentifier($identifier);
        if (!$station instanceof Station) {
            throw new InvalidArgumentException(sprintf('Station "%s" not found.', $identifier));
        }

        return $station;
    }
}
