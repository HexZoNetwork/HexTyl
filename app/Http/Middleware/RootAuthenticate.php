<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RootAuthenticate
{
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();

        if (!$user || !$user->isRoot()) {
            throw new AccessDeniedHttpException('Root account access is required.');
        }

        return $next($request);
    }
}
