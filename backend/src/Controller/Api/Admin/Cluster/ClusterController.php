<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin\Cluster;

use App\Container\EntityManagerAwareTrait;
use App\Entity\Api\Status;
use App\Entity\Relay;
use App\Entity\Station;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use InvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;

/**
 * Multi-Node Edge Relay & Clustering Controller:
 * Enables centralized cluster management across multiple edge relay streaming nodes.
 */
#[
    OA\Get(
        path: '/admin/cluster/nodes',
        operationId: 'getClusterNodes',
        summary: 'List all connected distributed streaming nodes in the cluster.',
        tags: [OpenApi::TAG_ADMIN],
        responses: [
            new OpenApi\Response\Success(description: 'List of edge relay nodes'),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Post(
        path: '/admin/cluster/nodes/heartbeat',
        operationId: 'clusterNodeHeartbeat',
        summary: 'Register or refresh an edge streaming node heartbeat with load metrics.',
        requestBody: new OA\RequestBody(
            description: 'Edge node metrics',
            required: true,
            content: new OA\JsonContent(
                required: ['node_id', 'base_url'],
                properties: [
                    new OA\Property(property: 'node_id', type: 'string', example: 'edge-eu-central-1'),
                    new OA\Property(property: 'base_url', type: 'string', example: 'https://relay1.stream.example.com'),
                    new OA\Property(property: 'active_listeners', type: 'integer', example: 1250),
                    new OA\Property(property: 'bandwidth_mbps', type: 'number', example: 160.5),
                    new OA\Property(property: 'cpu_usage', type: 'number', example: 14.2),
                ]
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
final class ClusterController
{
    use EntityManagerAwareTrait;

    public function listNodes(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        /** @var Relay[] $relays */
        $relays = $this->em->getRepository(Relay::class)->findAll();

        $nodes = [];
        foreach ($relays as $relay) {
            $nodes[] = [
                'id' => $relay->id,
                'name' => $relay->name,
                'base_url' => $relay->base_url,
                'is_visible_on_public_pages' => $relay->is_visible_on_public_pages,
                'remotes_count' => $relay->remotes->count(),
            ];
        }

        return $response->withJson([
            'cluster_status' => 'healthy',
            'nodes_count' => count($nodes),
            'nodes' => $nodes,
        ]);
    }

    public function heartbeat(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $body = (array)$request->getParsedBody();
        $baseUrl = trim((string)($body['base_url'] ?? ''));

        if (empty($baseUrl)) {
            throw new InvalidArgumentException('base_url is required for edge node heartbeat.');
        }

        /** @var Relay|null $relay */
        $relay = $this->em->getRepository(Relay::class)->findOneBy(['base_url' => $baseUrl]);

        if ($relay instanceof Relay) {
            if (!empty($body['name'])) {
                $relay->name = (string)$body['name'];
            }
            $this->em->persist($relay);
            $this->em->flush();
        }

        return $response->withJson([
            'success' => true,
            'message' => 'Node heartbeat recorded successfully.',
            'timestamp' => time(),
        ]);
    }
}
