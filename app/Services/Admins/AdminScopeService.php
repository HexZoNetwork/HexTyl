<?php

namespace Pterodactyl\Services\Admins;

use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        
        throw new AccessDeniedHttpException('Only the Root user can modify administrator scopes.');

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
    public function canViewServer(User $actor, Server $server): bool
    {
        // Root sees all.
        if ($actor->isRoot()) {
            return true;
        }

        // Server list/view in admin scope always requires at least server.read.
        if (!$actor->hasScope('server.read')) {
            return false;
        }

        // Public servers are visible to scoped admins.
        if ($server->isPublic()) {
            return true;
        }

        // Private servers require specific scope.
        if ($server->isPrivate()) {
            return $actor->hasScope('server:private:view');
        }

        return false;
    }

    public function ensureCanReadServers(User $actor): void
    {
        if ($actor->isRoot()) {
            return;
        }

        if (!$actor->hasScope('server.read')) {
            throw new AccessDeniedHttpException('Missing required scope: server.read');
        }
    }

    public function ensureCanCreateServers(User $actor): void
    {
        if ($actor->isRoot()) {
            return;
        }

        if (!$actor->hasScope('server.create')) {
            throw new AccessDeniedHttpException('Missing required scope: server.create');
        }
    }

    public function ensureCanUpdateServers(User $actor): void
    {
        if ($actor->isRoot()) {
            return;
        }

        if (!$actor->hasScope('server.update')) {
            throw new AccessDeniedHttpException('Missing required scope: server.update');
        }
    }

    public function ensureCanDeleteServers(User $actor): void
    {
        if ($actor->isRoot()) {
            return;
        }

        if (!$actor->hasScope('server.delete')) {
            throw new AccessDeniedHttpException('Missing required scope: server.delete');
        }
    }

    public function ensureCanViewServer(User $actor, Server $server): void
    {
        if ($this->canViewServer($actor, $server)) {
            return;
        }

        // Hide private resources when viewer doesn't have private scope.
        if ($server->isPrivate() && !$actor->isRoot() && !$actor->hasScope('server:private:view')) {
            throw new NotFoundHttpException('Server not found.');
        }

        throw new AccessDeniedHttpException('You do not have permission to access this server.');
    }

    public function ensureCanCreateWithVisibility(User $actor, string $visibility): void
    {
        $this->ensureCanCreateServers($actor);

        if (
            !$actor->isRoot()
            && $visibility === Server::VISIBILITY_PRIVATE
            && !$actor->hasScope('server:private:view')
        ) {
            throw new AccessDeniedHttpException('Missing required scope: server:private:view');
        }
    }

    public function ensureCanUpdateWithVisibility(User $actor, Server $server, ?string $requestedVisibility = null): void
    {
        $this->ensureCanViewServer($actor, $server);
        $this->ensureCanUpdateServers($actor);

        if (
            !$actor->isRoot()
            && $requestedVisibility === Server::VISIBILITY_PRIVATE
            && !$actor->hasScope('server:private:view')
        ) {
            throw new AccessDeniedHttpException('Missing required scope: server:private:view');
        }
    }
}
