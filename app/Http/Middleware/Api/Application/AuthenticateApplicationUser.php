<?php

namespace Pterodactyl\Http\Middleware\Api\Application;

use Illuminate\Http\Request;
use Pterodactyl\Models\ApiKey;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateApplicationUser
{
    /**
     * Authenticate that the currently authenticated user is an administrator
     * and should be allowed to proceed through the application API.
     *
     * Elevated keys (TYPE_ROOT / ptlr_) bypass the root_admin requirement so they
     * can interact with application-level endpoints.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        /** @var \Pterodactyl\Models\User|null $user */
        $user = $request->user();
        if (!$user) {
            throw new AccessDeniedHttpException('This account does not have permission to access the API.');
        }

        // Elevated root API keys bypass the admin check entirely.
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof ApiKey && $token->isRootKey()) {
            return $next($request);
        }

        if (!$user->root_admin) {
            throw new AccessDeniedHttpException('This account does not have permission to access the API.');
        }

        return $next($request);
    }
}
