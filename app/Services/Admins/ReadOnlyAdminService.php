<?php

namespace Pterodactyl\Services\Admins;

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

        if (!$user) {
            return;
        }

        if (!$user->isRoot() && $user->root_admin && $user->hasScope('admin:read_only')) {
            if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
                throw new AccessDeniedHttpException('Read-Only Admin: Modification actions are disabled.');
            }
        }
    }
}
