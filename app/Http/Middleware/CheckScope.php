<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CheckScope
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string $scope
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $scope)
    {
        /** @var \Pterodactyl\Models\User $user */
        $user = $request->user();

        if (!$user) {
             throw new AccessDeniedHttpException('Unauthenticated.');
        }

        // 1. Root User Bypass (Immutable Root or ID 1)
        if ($user->isRoot()) {
            return $next($request);
        }

        // 2. Master API Key Bypass
        // If the request is authenticated via API Key, checks if it is a "Master" key
        // Current logic: If the key belongs to Root, it is effectively a master key IF we treat it so.
        // Or if we added a specific flag.
        // User request: "Owner apikey for managing all in one api it has full access"
        // Let's assume if the tokenable is the Root user, it has full access.
        // The isRoot() check above already covers this if the API key authenticates AS the user.
        // Validating: Pterodactyl API keys authenticate as the user. $request->user() returns the user.
        // So `isRoot()` covers "Owner API Key" automatically if the key belongs to Root.

        // 3. Scope Check
        if (!$user->hasScope($scope)) {
            throw new AccessDeniedHttpException('You do not have the required scope (' . $scope . ') to perform this action.');
        }

        return $next($request);
    }
}
