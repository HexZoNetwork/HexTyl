<?php

namespace Pterodactyl\Http\Middleware;

use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

class EnsureStatefulRequests extends EnsureFrontendRequestsAreStateful
{
    /**
     * Only trust Sanctum's frontend detection. A session cookie alone is not sufficient
     * to mark a request as stateful.
     */
    public static function fromFrontend($request)
    {
        return parent::fromFrontend($request);
    }
}
