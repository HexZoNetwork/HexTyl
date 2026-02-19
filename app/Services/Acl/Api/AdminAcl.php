<?php

namespace Pterodactyl\Services\Acl\Api;

use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\User;

class AdminAcl
{
    /**
     * Resource permission columns in the api_keys table begin
     * with this identifier.
     */
    public const COLUMN_IDENTIFIER = 'r_';

    /**
     * The different types of permissions available for API keys. This
     * implements a read/write/none permissions scheme for all endpoints.
     */
    public const NONE = 0;
    public const READ = 1;
    public const WRITE = 2;
    public const READ_WRITE = 3;

    /**
     * Resources that are available on the API and can contain a permissions
     * set for each key. These are stored in the database as r_{resource}.
     */
    public const RESOURCE_SERVERS = 'servers';
    public const RESOURCE_NODES = 'nodes';
    public const RESOURCE_ALLOCATIONS = 'allocations';
    public const RESOURCE_USERS = 'users';
    public const RESOURCE_LOCATIONS = 'locations';
    public const RESOURCE_NESTS = 'nests';
    public const RESOURCE_EGGS = 'eggs';
    public const RESOURCE_DATABASE_HOSTS = 'database_hosts';
    public const RESOURCE_SERVER_DATABASES = 'server_databases';

    /**
     * Determine if an API key has permission to perform a specific read/write operation.
     */
    public static function can(int $permission, int $action = self::READ): bool
    {
        if ($permission & $action) {
            return true;
        }

        return false;
    }

    /**
     * Determine if an API Key model has permission to access a given resource
     * at a specific action level.
     */
    public static function check(ApiKey $key, string $resource, int $action = self::READ): bool
    {
        // Root master API keys bypass all resource permission checks.
        if ($key->isRootKey()) {
            return true;
        }

        // Map AdminAcl resource names to the corresponding server-level scope key.
        // If the admin's role does NOT contain the required scope, deny even if the
        // API key's r_* column would otherwise allow it.
        $scopeMap = self::scopeMap();

        $user = $key->user;
        if ($user && !$user->isRoot() && isset($scopeMap[$resource])) {
            if (!$user->hasScope($scopeMap[$resource])) {
                return false;
            }
        }

        return self::can(data_get($key, self::COLUMN_IDENTIFIER . $resource, self::NONE), $action);
    }

    /**
     * Maximum permission level a specific admin user can assign to a resource
     * when creating an application API key from the panel UI.
     */
    public static function getCreationPermissionCap(User $user, string $resource): int
    {
        if ($user->isRoot()) {
            return self::READ_WRITE;
        }

        $readScopeMap = self::scopeMap();
        if (!isset($readScopeMap[$resource])) {
            return self::NONE;
        }

        $readScope = $readScopeMap[$resource];
        if (!$user->hasScope($readScope)) {
            return self::NONE;
        }

        $writeScopeMap = self::writeScopeMap();
        $writeScopes = $writeScopeMap[$resource] ?? [];
        foreach ($writeScopes as $scope) {
            if ($user->hasScope($scope)) {
                return self::READ_WRITE;
            }
        }

        return self::READ;
    }

    /**
     * Return a list of all resource constants defined in this ACL.
     *
     * @throws \ReflectionException
     */
    public static function getResourceList(): array
    {
        $reflect = new \ReflectionClass(__CLASS__);

        return collect($reflect->getConstants())->filter(function ($value, $key) {
            return substr($key, 0, 9) === 'RESOURCE_';
        })->values()->toArray();
    }

    private static function scopeMap(): array
    {
        return [
            self::RESOURCE_NODES            => 'node.read',
            self::RESOURCE_SERVERS          => 'server.read',
            self::RESOURCE_USERS            => 'user.read',
            self::RESOURCE_ALLOCATIONS      => 'server.read',
            self::RESOURCE_DATABASE_HOSTS   => 'database.read',
            self::RESOURCE_SERVER_DATABASES => 'database.read',
            self::RESOURCE_LOCATIONS        => 'server.read',
            self::RESOURCE_NESTS            => 'server.read',
            self::RESOURCE_EGGS             => 'server.read',
        ];
    }

    private static function writeScopeMap(): array
    {
        return [
            self::RESOURCE_NODES => ['node.write'],
            self::RESOURCE_ALLOCATIONS => ['node.write'],
            self::RESOURCE_SERVERS => ['server.create', 'server.update', 'server.delete'],
            self::RESOURCE_USERS => ['user.create', 'user.update', 'user.delete'],
            self::RESOURCE_DATABASE_HOSTS => ['database.create', 'database.update', 'database.delete'],
            self::RESOURCE_SERVER_DATABASES => ['database.create', 'database.update', 'database.delete'],
            self::RESOURCE_LOCATIONS => ['node.write'],
            self::RESOURCE_NESTS => ['server.create', 'server.update'],
            self::RESOURCE_EGGS => ['server.create', 'server.update'],
        ];
    }
}
