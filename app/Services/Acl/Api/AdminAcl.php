<?php

namespace Pterodactyl\Services\Acl\Api;

use Illuminate\Support\Str;
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

        $user = $key->user;
        if ($user && !self::userCanAccessResourceAction($user, $resource, $action)) {
            return false;
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

        $matrix = self::resourceScopeMatrix();
        if (!isset($matrix[$resource]['read'])) {
            return self::NONE;
        }

        $readScope = (string) $matrix[$resource]['read'];
        if (!$user->hasScope($readScope)) {
            return self::NONE;
        }

        $writeScopes = (array) ($matrix[$resource]['write'] ?? []);
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

    /**
     * Build a scope catalog for PTLA key creation.
     *
     * @return array<int, array{
     *     scope: string,
     *     label: string,
     *     assignable: bool,
     *     grants: array<int, array{resource: string, permission: int}>
     * }>
     */
    public static function getCreationScopeCatalog(User $user): array
    {
        $catalog = [];

        foreach (self::resourceScopeMatrix() as $resource => $entry) {
            $readScope = (string) ($entry['read'] ?? '');
            if ($readScope !== '') {
                $catalog[$readScope]['scope'] = $readScope;
                $catalog[$readScope]['label'] = self::labelForScope($readScope);
                $catalog[$readScope]['grants'][] = [
                    'resource' => $resource,
                    'permission' => self::READ,
                ];
            }

            foreach ((array) ($entry['write'] ?? []) as $writeScope) {
                $catalog[$writeScope]['scope'] = $writeScope;
                $catalog[$writeScope]['label'] = self::labelForScope($writeScope);
                $catalog[$writeScope]['grants'][] = [
                    'resource' => $resource,
                    'permission' => self::WRITE,
                ];
            }
        }

        ksort($catalog);

        return collect($catalog)->map(function (array $entry) use ($user) {
            $grants = collect($entry['grants'] ?? [])
                ->filter(fn (array $grant) => isset($grant['resource'], $grant['permission']))
                ->map(function (array $grant) {
                    return [
                        'resource' => (string) $grant['resource'],
                        'permission' => (int) $grant['permission'],
                    ];
                })
                ->unique(fn (array $grant) => $grant['resource'] . ':' . $grant['permission'])
                ->sortBy(fn (array $grant) => $grant['resource'] . ':' . $grant['permission'])
                ->values()
                ->all();

            $scope = (string) ($entry['scope'] ?? '');
            $scopeOwned = $user->isRoot() || $user->hasScope($scope);
            $grantsAssignable = $user->isRoot() || collect($grants)->contains(function (array $grant) use ($user) {
                $resource = (string) ($grant['resource'] ?? '');
                if ($resource === '') {
                    return false;
                }

                $target = ((int) ($grant['permission'] ?? self::NONE)) === self::WRITE
                    ? self::READ_WRITE
                    : self::READ;

                return min($target, self::getCreationPermissionCap($user, $resource)) > self::NONE;
            });

            return [
                'scope' => $scope,
                'label' => (string) ($entry['label'] ?? self::labelForScope($scope)),
                'assignable' => $scopeOwned && $grantsAssignable,
                'grants' => $grants,
            ];
        })->values()->all();
    }

    /**
     * @return array<int, string>
     */
    public static function getAssignableCreationScopes(User $user): array
    {
        return collect(self::getCreationScopeCatalog($user))
            ->filter(fn (array $row) => ($row['assignable'] ?? false) === true)
            ->pluck('scope')
            ->map(fn ($scope) => trim((string) $scope))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve resource permissions (`r_*`) from selected scopes while enforcing
     * actor role limits.
     *
     * @param array<int, string> $selectedScopes
     *
     * @return array<string, int>
     */
    public static function buildPermissionsFromScopes(User $user, array $selectedScopes): array
    {
        $permissions = collect(self::getResourceList())
            ->mapWithKeys(fn (string $resource) => [self::COLUMN_IDENTIFIER . $resource => self::NONE])
            ->all();

        $catalog = collect(self::getCreationScopeCatalog($user))
            ->keyBy('scope');

        $scopes = collect($selectedScopes)
            ->map(fn ($scope) => trim((string) $scope))
            ->filter()
            ->unique()
            ->values();

        foreach ($scopes as $scope) {
            $entry = $catalog->get($scope);
            if (!is_array($entry) || (($entry['assignable'] ?? false) !== true && !$user->isRoot())) {
                continue;
            }

            foreach ((array) ($entry['grants'] ?? []) as $grant) {
                $resource = (string) ($grant['resource'] ?? '');
                if ($resource === '') {
                    continue;
                }

                $column = self::COLUMN_IDENTIFIER . $resource;
                if (!array_key_exists($column, $permissions)) {
                    continue;
                }

                $target = ((int) ($grant['permission'] ?? self::NONE)) === self::WRITE
                    ? self::READ_WRITE
                    : self::READ;

                $cap = self::getCreationPermissionCap($user, $resource);
                if ($cap <= self::NONE) {
                    continue;
                }

                $safe = min($target, $cap);
                $permissions[$column] = max((int) $permissions[$column], (int) $safe);
            }
        }

        return $permissions;
    }

    /**
     * @return array<string, array{read: string, write: array<int, string>}>
     */
    public static function resourceScopeMatrix(): array
    {
        return [
            self::RESOURCE_NODES => [
                'read' => 'node.read',
                'write' => ['node.write'],
            ],
            self::RESOURCE_SERVERS => [
                'read' => 'server.read',
                'write' => ['server.create', 'server.update', 'server.delete'],
            ],
            self::RESOURCE_USERS => [
                'read' => 'user.read',
                'write' => ['user.create', 'user.update', 'user.delete'],
            ],
            self::RESOURCE_ALLOCATIONS => [
                'read' => 'server.read',
                'write' => ['node.write'],
            ],
            self::RESOURCE_DATABASE_HOSTS => [
                'read' => 'database.read',
                'write' => ['database.create', 'database.update', 'database.delete'],
            ],
            self::RESOURCE_SERVER_DATABASES => [
                'read' => 'database.read',
                'write' => ['database.create', 'database.update', 'database.delete'],
            ],
            self::RESOURCE_LOCATIONS => [
                'read' => 'server.read',
                'write' => ['node.write'],
            ],
            self::RESOURCE_NESTS => [
                'read' => 'server.read',
                'write' => ['server.create', 'server.update'],
            ],
            self::RESOURCE_EGGS => [
                'read' => 'server.read',
                'write' => ['server.create', 'server.update'],
            ],
        ];
    }

    private static function userCanAccessResourceAction(User $user, string $resource, int $action): bool
    {
        if ($user->isRoot()) {
            return true;
        }

        $matrix = self::resourceScopeMatrix();
        if (!isset($matrix[$resource])) {
            return false;
        }

        $readScope = (string) ($matrix[$resource]['read'] ?? '');
        $writeScopes = (array) ($matrix[$resource]['write'] ?? []);
        $hasRead = $readScope !== '' && $user->hasScope($readScope);
        $hasWrite = collect($writeScopes)->contains(fn (string $scope) => $user->hasScope($scope));

        if ($action === self::WRITE) {
            return $hasWrite;
        }

        if ($action === self::READ_WRITE) {
            return $hasRead && $hasWrite;
        }

        return $hasRead;
    }

    private static function labelForScope(string $scope): string
    {
        return Str::headline(str_replace(['.', ':'], ' ', $scope));
    }
}
