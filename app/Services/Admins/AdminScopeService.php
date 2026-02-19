<?php

namespace Pterodactyl\Services\Admins;

use Pterodactyl\Models\User;
use Pterodactyl\Exceptions\DisplayException;

class AdminScopeService
{
    /**
     * Validate if an actor can grant specific permissions to a target user.
     */
    public function validateGrant(User $actor, array $requestedScopes): void
    {
        // 1. Root can grant anything.
        if ($actor->isRoot()) {
            return;
        }

        // 2. Regular Admins cannot grant 'root' or 'admin.create' privileges unless explicitly allowed (which they shouldn't be).
        // The requirement: "only root can edit admin scope"
        // Also "admin cannot add other admin"
        
        throw new DisplayException('Only the Root user can modify administrator scopes.');

        /* 
        // Logic if we allowed admins to create sub-admins:
        foreach ($requestedScopes as $scope) {
            if (!$actor->hasScope($scope)) {
                throw new DisplayException("You cannot grant a scope ($scope) that you do not possess.");
            }
            
            if ($scope === 'server:private:view' && !$actor->isRoot()) {
                 throw new DisplayException("Only Root can grant private server visibility.");
            }
        }
        */
    }

    /**
     * Check if actor can view a server based on visibility and scope.
     */
    public function canViewServer(User $actor, \Pterodactyl\Models\Server $server): bool
    {
        // Root sees all
        if ($actor->isRoot()) {
            return true;
        }

        // Public servers are visible to all (or just logged in?)
        if ($server->isPublic()) {
            return true;
        }

        // Private servers require specific scope
        if ($server->isPrivate()) {
            return $actor->hasScope('server:private:view');
        }

        return false;
    }
}
