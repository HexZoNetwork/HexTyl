<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminAuthenticate
{
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
        
        // Allow if user is Root (ID 1 or is_system_root) OR has admin access scope
        // For now, let's assume 'admin.access' scope or just being in a system role (Root/Admin)
        // allows entry.
        // If we want to strictly separate, we might need more logic.
        // But preventing access completely if not root_admin was the old way.
        
        if ($user->isRoot() || ($user->role && $user->role->name === 'Admin')) {
            return $next($request);
        }
        
        // Fallback for backward compatibility if data isn't fully migrated or for other roles
        if ($user->root_admin) {
            return $next($request);
        }

        throw new AccessDeniedHttpException();
    }
}
