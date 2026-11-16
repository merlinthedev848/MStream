<?php

declare(strict_types=1);

namespace App\Controller\Api\Admin;

use App\Controller\Api\AbstractApiCrudController;
use App\Controller\Api\Traits\CanSearchResults;
use App\Controller\Api\Traits\CanSortResults;
use App\Controller\Frontend\Account\MasqueradeAction;
use App\Entity\Customer;
use App\Entity\Reseller;
use App\Entity\Station;
use App\Entity\User;
use App\Http\Response;
use App\Http\ServerRequest;
use App\OpenApi;
use App\Service\CustomerAccountService;
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
        private readonly CustomerAccountService $customerAccounts,
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
        $loginEmail = isset($data['email']) ? trim((string)$data['email']) : '';
        $loginPassword = isset($data['password']) ? (string)$data['password'] : '';
        $loginName = isset($data['login_name']) ? trim((string)$data['login_name']) : '';
        unset($data['email'], $data['password'], $data['login_name'], $data['new_password']);

        if (
            !$this->tenancyScope->isHostAdmin($request)
            && $this->tenancyScope->isResellerAdmin($request)
            && null !== $request->getUser()->reseller
        ) {
            $data['reseller'] = $request->getUser()->reseller->id;
        } elseif (empty($data['reseller']) && empty($data['reseller_id'])) {
            $data['reseller'] = $this->customerAccounts->ensureHostReseller()->id;
        }

        /** @var Customer $customer */
        $customer = $this->editRecord($data);

        if ($loginEmail !== '') {
            $this->customerAccounts->provisionLogin(
                $customer,
                $loginEmail,
                $loginPassword !== '' ? $loginPassword : null,
                $loginName !== '' ? $loginName : $customer->name,
            );
        } elseif ($customer->owner_user instanceof User) {
            $this->customerAccounts->syncOwnerStationAccess($customer);
        }

        return $customer;
    }

    protected function editRecord(?array $data, ?object $record = null, array $context = []): object
    {
        $loginEmail = null;
        $loginPassword = null;
        $loginName = null;

        if (is_array($data)) {
            if (array_key_exists('email', $data)) {
                $loginEmail = trim((string)$data['email']);
                unset($data['email']);
            }
            if (array_key_exists('password', $data) || array_key_exists('new_password', $data)) {
                $loginPassword = (string)($data['password'] ?? $data['new_password'] ?? '');
                unset($data['password'], $data['new_password']);
            }
            if (array_key_exists('login_name', $data)) {
                $loginName = trim((string)$data['login_name']);
                unset($data['login_name']);
            }
        }

        /** @var Customer $customer */
        $customer = parent::editRecord($data, $record, $context);

        if ($loginEmail !== null && $loginEmail !== '') {
            $this->customerAccounts->provisionLogin(
                $customer,
                $loginEmail,
                ($loginPassword !== null && $loginPassword !== '') ? $loginPassword : null,
                ($loginName !== null && $loginName !== '') ? $loginName : null,
            );
        } elseif ($customer->owner_user instanceof User) {
            $this->customerAccounts->syncOwnerStationAccess($customer);
        }

        return $customer;
    }

    protected function toArray(object $record, array $context = []): array
    {
        $context[AbstractNormalizer::CALLBACKS]['reseller'] = static fn(Reseller $value) => $value->id;
        $context[AbstractNormalizer::CALLBACKS]['owner_user'] = static fn($value) => $value?->id;
        // Avoid serializing the stations collection as nested entities.
        $context[AbstractNormalizer::IGNORED_ATTRIBUTES] = ['stations'];

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

    protected function viewRecord(object $record, ServerRequest $request): mixed
    {
        /** @var Customer $record */
        $return = parent::viewRecord($record, $request);

        $router = $request->getRouter();
        $csrf = $request->getCsrf();
        $isInternal = $request->isInternal();

        $owner = $record->owner_user;
        $return['owner_email'] = $owner?->email;
        $return['owner_name'] = $owner?->getDisplayName();
        $return['user_level'] = $this->customerAccounts->resolveUserLevel($owner);

        /** @var list<Station> $stationRows */
        $stationRows = $this->em->getRepository(Station::class)->findBy([
            'customer' => $record,
        ]);

        $stations = [];
        foreach ($stationRows as $station) {
            $stations[] = [
                'id' => $station->id,
                'name' => $station->name,
                'short_name' => $station->short_name,
                'is_enabled' => $station->is_enabled,
                'links' => [
                    'manage' => $router->named(
                        routeName: 'stations:index:index',
                        routeParams: ['station_id' => $station->id],
                        absolute: !$isInternal
                    ),
                ],
            ];
        }
        $return['stations'] = $stations;
        $return['services_count'] = count($stations);

        $return['links'] ??= [];
        $return['links']['self'] = $router->fromHere(
            routeName: $this->resourceRouteName,
            routeParams: ['id' => $record->id],
            absolute: !$isInternal
        );
        $return['links']['services'] = $router->named(
            routeName: 'admin:stations:index',
            routeParams: [],
            queryParams: ['customer' => (string)$record->id],
            absolute: !$isInternal
        );

        if ($owner !== null) {
            $return['links']['masquerade'] = $router->fromHere(
                routeName: 'account:masquerade',
                routeParams: [
                    'id' => $owner->id,
                    'csrf' => $csrf->generate(MasqueradeAction::CSRF_NAMESPACE),
                ],
                absolute: !$isInternal
            );
        }

        return $return;
    }
}
