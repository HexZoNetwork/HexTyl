<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CheckPanicMode
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if Panic Mode is enabled
        // We use DB directly or Cache to avoid overhead. Cache is better.
        // For now, let's assume valid cache or direct DB check if critical.
        
        $panicMode = \Illuminate\Support\Facades\Cache::remember('system:panic_mode', 60, function () {
            // Check system_settings table
            $setting = DB::table('system_settings')->where('key', 'panic_mode')->first();
            return $setting ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN) : false;
        });

        if ($panicMode) {
            // Allow GET requests (Read-only)
            if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
                return $next($request);
            }

            // Allow Root User (Rescue Access)
            if ($request->user() && ($request->user()->id === 1 || $request->user()->is_system_root)) {
                return $next($request);
            }

            // Block everything else
            throw new HttpException(503, 'System is in PANIC MODE. All modification actions are suspended.');
        }

        return $next($request);
    }
}
