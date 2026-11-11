<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Enums\PermissionInterface;
use App\Exception\Http\NotLoggedInException;
use App\Exception\Http\PermissionDeniedException;
use App\Http\ServerRequest;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Allow the request when the user has any of the listed global/station permissions.
 */
final class PermissionsAny extends AbstractMiddleware
{
    /**
     * @param list<string|PermissionInterface> $actions
     */
    public function __construct(
        private readonly array $actions,
        private readonly bool $useStation = false
    ) {
    }

    public function __invoke(ServerRequest $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->useStation) {
            $stationId = $request->getStation()->id;
        } else {
            $stationId = null;
        }

        try {
            $request->getUser();
        } catch (Exception) {
            throw NotLoggedInException::create($request);
        }

        $acl = $request->getAcl();
        foreach ($this->actions as $action) {
            if ($acl->isAllowed($action, $stationId)) {
                return $handler->handle($request);
            }
        }

        throw PermissionDeniedException::create($request);
    }
}
