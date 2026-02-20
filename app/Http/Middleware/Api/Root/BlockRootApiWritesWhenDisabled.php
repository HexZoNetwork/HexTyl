<?php

namespace Pterodactyl\Http\Middleware\Api\Root;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BlockRootApiWritesWhenDisabled
{
    public function handle(Request $request, Closure $next)
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        if ($request->is('api/rootapplication/security/emergency-mode')) {
            return $next($request);
        }

        $disabled = filter_var(
            Cache::remember('system:ptla_write_disabled', 30, function () {
                return (string) (DB::table('system_settings')->where('key', 'ptla_write_disabled')->value('value') ?? 'false');
            }),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($disabled) {
            throw new HttpException(423, 'PTLA write access is temporarily disabled by emergency mode.');
        }

        return $next($request);
    }
}
