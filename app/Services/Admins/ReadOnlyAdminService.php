<?php

namespace Pterodactyl\Services\Admins;

use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReadOnlyAdminService
{
    /**
     * Check if a user is a Read-Only Admin and trying to perform a write action.
     */
    public function check(Request $request): void
    {
        $user = $request->user();

        if (!$user || !$user->is_root_admin) {
            return;
        }

        // Check if user has specific 'read-only' flag or role
        // Assuming we added 'is_read_only' column or scope to users/roles
        // Mocking via a hypothetical scope check
        
        if ($user->hasScope('admin:read_only')) {
            if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
                throw new AccessDeniedHttpException('Read-Only Admin: Modification actions are disabled.');
            }
        }
    }
}
