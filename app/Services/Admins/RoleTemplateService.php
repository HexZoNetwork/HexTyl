<?php

namespace Pterodactyl\Services\Admins;

class RoleTemplateService
{
    /**
     * Built-in role templates. These are intentionally conservative and avoid wildcard scopes.
     */
    public static function templates(): array
    {
        return [
            'viewer' => [
                'label' => 'Viewer',
                'description' => 'Read-only visibility for users, servers, nodes, and databases.',
                'scopes' => ['user.read', 'server.read', 'node.read', 'database.read'],
            ],
            'operator' => [
                'label' => 'Operator',
                'description' => 'Operational control for infrastructure without user-admin promotion.',
                'scopes' => [
                    'user.read',
                    'server.read', 'server.create', 'server.update',
                    'node.read',
                    'database.read', 'database.create', 'database.update',
                ],
            ],
            'manager' => [
                'label' => 'Manager',
                'description' => 'Broad management for user and server lifecycle, no wildcard.',
                'scopes' => [
                    'user.read', 'user.create', 'user.update', 'user.delete',
                    'server.read', 'server.create', 'server.update', 'server.delete',
                    'node.read',
                    'database.read', 'database.create', 'database.update', 'database.delete',
                ],
            ],
            'security' => [
                'label' => 'Security',
                'description' => 'Security operations and restricted admin controls.',
                'scopes' => [
                    'user.read',
                    'server.read',
                    'node.read',
                    'database.read',
                    'admin:read_only',
                    'server:private:view',
                ],
            ],
        ];
    }
}
