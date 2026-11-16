<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Container\SettingsAwareTrait;
use App\Controller\Api\AbstractApiCrudController;
use App\Entity\Api\Status;
use App\Entity\Repository\StationRepository;
use App\Entity\Settings;
use App\Exception\ValidationException;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Utilities\InstanceBranding;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractApiCrudController<Settings> */
#[
    OA\Get(
        path: '/admin/settings',
        operationId: 'getSettings',
        summary: 'List the current values of all editable system settings.',
        tags: [OpenApi::TAG_ADMIN_SETTINGS],
        responses: [
            new OpenApi\Response\Success(
                content: new OA\JsonContent(ref: Settings::class)
            ),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Put(
        path: '/admin/settings',
        operationId: 'editSettings',
        summary: 'Update settings to modify any settings provided.',
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(ref: Settings::class)
        ),
        tags: [OpenApi::TAG_ADMIN_SETTINGS],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class SettingsController extends AbstractApiCrudController
{
    use SettingsAwareTrait;

    protected string $entityClass = Settings::class;

    public function __construct(
        Serializer $serializer,
        ValidatorInterface $validator,
        private readonly StationRepository $stationRepo
    ) {
        parent::__construct($serializer, $validator);
    }

    public function listAction(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        /** @var string|null $group */
        $group = $params['group'] ?? null;

        $context = [];
        if (null !== $group && in_array($group, Settings::VALID_GROUPS, true)) {
            $context[AbstractNormalizer::GROUPS] = [$group];
        }

        return $response->withJson(
            $this->toArray(
                $this->readSettings(),
                $context
            )
        );
    }

    public function updateAction(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        /** @var string|null $group */
        $group = $params['group'] ?? null;

        $context = [];
        if (null !== $group && in_array($group, Settings::VALID_GROUPS, true)) {
            $context[AbstractNormalizer::GROUPS] = [$group];
        }

        $settings = $this->settingsRepo->readSettings();

        if ($group === Settings::GROUP_GENERAL && !$settings->isSetupComplete()) {
            $settings->updateSetupComplete();
        }

        $body = (array)$request->getParsedBody();

        // Panel brand only — never allow a station/customer name as instance_name.
        if (
            ($group === null || $group === Settings::GROUP_GENERAL)
            && array_key_exists('instance_name', $body)
            && is_string($body['instance_name'])
            && '' !== trim($body['instance_name'])
            && InstanceBranding::conflictsWithStationLabels(
                $body['instance_name'],
                $this->stationRepo->getInstanceBrandingConflictLabels()
            )
        ) {
            throw new ValidationException(
                __(
                    'MStream Instance Name must be the panel/hoster brand (e.g. your company name), not a station or customer name.'
                )
            );
        }

        $this->editRecord($body, $settings, $context);

        return $response->withJson(Status::success());
    }
}
