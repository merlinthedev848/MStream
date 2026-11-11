<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Controller\Api\AbstractApiCrudController;
use App\Controller\Api\Traits\CanSearchResults;
use App\Controller\Api\Traits\CanSortResults;
use App\Entity\Customer;
use App\Entity\Reseller;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Service\TenancyScope;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @extends AbstractApiCrudController<Customer> */
#[
    OA\Get(
        path: '/admin/customers',
        operationId: 'adminGetCustomers',
        summary: 'List customers.',
        tags: [OpenApi::TAG_ADMIN_STATIONS],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Post(
        path: '/admin/customers',
        operationId: 'adminAddCustomer',
        summary: 'Create a customer.',
        tags: [OpenApi::TAG_ADMIN_STATIONS],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Get(
        path: '/admin/customer/{id}',
        operationId: 'adminGetCustomer',
        summary: 'Retrieve a customer.',
        tags: [OpenApi::TAG_ADMIN_STATIONS],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            ),
        ],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Put(
        path: '/admin/customer/{id}',
        operationId: 'adminEditCustomer',
        summary: 'Update a customer.',
        tags: [OpenApi::TAG_ADMIN_STATIONS],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            ),
        ],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    ),
    OA\Delete(
        path: '/admin/customer/{id}',
        operationId: 'adminDeleteCustomer',
        summary: 'Delete a customer.',
        tags: [OpenApi::TAG_ADMIN_STATIONS],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', format: 'int64')
            ),
        ],
        responses: [
            new OpenApi\Response\Success(),
            new OpenApi\Response\AccessDenied(),
            new OpenApi\Response\NotFound(),
            new OpenApi\Response\GenericError(),
        ]
    )
]
final class CustomersController extends AbstractApiCrudController
{
    use CanSortResults;
    use CanSearchResults;

    protected string $entityClass = Customer::class;
    protected string $resourceRouteName = 'api:admin:customer';

    public function __construct(
        private readonly TenancyScope $tenancyScope,
        Serializer $serializer,
        ValidatorInterface $validator
    ) {
        parent::__construct($serializer, $validator);
    }

    public function listAction(
        ServerRequest $request,
        Response $response,
        array $params
    ): ResponseInterface {
        $qb = $this->em->createQueryBuilder()
            ->select('e')
            ->from(Customer::class, 'e');

        if (
            !$this->tenancyScope->isHostAdmin($request)
            && $this->tenancyScope->isResellerAdmin($request)
            && null !== $request->getUser()->reseller
        ) {
            $qb->andWhere('e.reseller = :tenancy_reseller')
                ->setParameter('tenancy_reseller', $request->getUser()->reseller);
        } elseif (!$this->tenancyScope->isHostAdmin($request)) {
            $qb->andWhere('1 = 0');
        }

        $qb = $this->sortQueryBuilder($request, $qb, ['name' => 'e.name'], 'e.name');
        $qb = $this->searchQueryBuilder($request, $qb, ['e.name']);

        return $this->listPaginatedFromQuery($request, $response, $qb->getQuery());
    }

    protected function createRecord(ServerRequest $request, array $data): object
    {
        if (
            !$this->tenancyScope->isHostAdmin($request)
            && $this->tenancyScope->isResellerAdmin($request)
            && null !== $request->getUser()->reseller
        ) {
            $data['reseller'] = $request->getUser()->reseller->id;
        }

        return $this->editRecord($data);
    }

    protected function toArray(object $record, array $context = []): array
    {
        $context[AbstractNormalizer::CALLBACKS]['reseller'] = static fn(Reseller $value) => $value->id;
        $context[AbstractNormalizer::CALLBACKS]['owner_user'] = static fn($value) => $value?->id;

        return parent::toArray($record, $context);
    }

    protected function fromArray(array $data, ?object $record = null, array $context = []): object
    {
        if (array_key_exists('reseller_id', $data)) {
            $data['reseller'] = $data['reseller_id'];
            unset($data['reseller_id']);
        }
        if (array_key_exists('owner_user_id', $data)) {
            $data['owner_user'] = $data['owner_user_id'] ?: null;
            unset($data['owner_user_id']);
        }

        return parent::fromArray($data, $record, $context);
    }
}
