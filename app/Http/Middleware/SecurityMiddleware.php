<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Services\Security\BehavioralScoreService;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecurityMiddleware
{
    public function __construct(private BehavioralScoreService $riskService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        
        // Skip for trusted IPs (e.g., localhost or known admin IPs could be whitelisted here)
        
        $restriction = $this->riskService->getRestrictionLevel($ip);

        if ($restriction === 'block') {
            throw new HttpException(403, 'Access Denied: Your IP has been flagged for suspicious activity.');
        }

        if ($restriction === 'throttle_heavy') {
            // In a real app, we might sleep() or set stricter rate limit headers
            // sleep(1); 
        }

        return $next($request);
    }
}
