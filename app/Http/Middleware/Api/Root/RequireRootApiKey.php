<?php

namespace Pterodactyl\Http\Middleware\Api\Root;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Models\ApiKey;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RequireRootApiKey
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if (!$user) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        $token = $user->currentAccessToken();

        // Allow either root session auth or root master API key.
        if ($user->isRoot() || ($token instanceof ApiKey && $token->isRootKey())) {
            return $next($request);
        }

        throw new AccessDeniedHttpException('This endpoint requires a root master API key.');
    }
}
