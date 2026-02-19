<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Services\Admins\ReadOnlyAdminService;

class ReadOnlyAdminMiddleware
{
    public function __construct(private ReadOnlyAdminService $service)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $this->service->check($request);

        return $next($request);
    }
}
