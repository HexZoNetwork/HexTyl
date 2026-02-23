<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminAuthenticate
{
    /**
     * Scopes that indicate admin panel read access.
     */
    private const ADMIN_SCOPES = [
        'user.read',
        'server.read',
        'node.read',
        'database.read',
    ];

    /**
     * Handle an incoming request.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();

        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($user->isPanelAdmin()) {
            return $next($request);
        }

        foreach (self::ADMIN_SCOPES as $scope) {
            if ($user->hasScope($scope)) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException();
    }
}
