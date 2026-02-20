<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\View\View;
use ReflectionException;
use ReflectionMethod;
use Pterodactyl\Http\Controllers\Controller;

class DocumentationController extends Controller
{
    public function index(): View
    {
        $ptlaRoutes = $this->collectRoutesByPrefix('api/application');
        $ptlcRoutes = $this->collectRoutesByPrefix('api/client');
        $ptlrRoutes = $this->collectRoutesByPrefix('api/rootapplication');
        $ptldRoutes = $this->collectRoutesByPrefix('api/remote');

        return view('docs.index', [
            'ptlaRoutes' => $ptlaRoutes,
            'ptlcRoutes' => $ptlcRoutes,
            'ptlrRoutes' => $ptlrRoutes,
            'ptldRoutes' => $ptldRoutes,
            'ptlaTutorials' => $this->buildPtlaTutorials($ptlaRoutes),
            'ptlcTutorials' => $this->buildTutorials($ptlcRoutes, 'ptlc', 'ptlc'),
            'ptlrTutorials' => $this->buildTutorials($ptlrRoutes, 'ptlr', 'ptlr'),
            'ptldTutorials' => $this->buildTutorials($ptldRoutes, 'ptdl', 'ptld'),
        ]);
    }

    private function collectRoutesByPrefix(string $prefix): array
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(function (Route $route) use ($prefix) {
                return str_starts_with(trim($route->uri(), '/'), trim($prefix, '/'));
            })
            ->map(function (Route $route) {
                $methods = collect($route->methods())
                    ->reject(fn ($m) => in_array($m, ['HEAD', 'OPTIONS'], true))
                    ->values()
                    ->implode('|');

                return [
                    'methods' => $methods ?: 'GET',
                    'primary_method' => $this->primaryMethod($methods ?: 'GET'),
                    'uri' => '/' . ltrim($route->uri(), '/'),
                    'name' => $route->getName() ?? '-',
                    'input' => $this->inferInputType($methods ?: 'GET'),
                    'validator' => $this->resolveValidatorClass($route),
                ];
            })
            ->sortBy('uri')
            ->values()
            ->all();

        return $routes;
    }

    private function inferInputType(string $methods): string
    {
        $list = collect(explode('|', strtoupper($methods)));
        if ($list->contains('POST') || $list->contains('PUT') || $list->contains('PATCH')) {
            return 'JSON body';
        }
        if ($list->contains('DELETE')) {
            return 'Path param (optional JSON)';
        }

        return 'Query/path only';
    }

    private function primaryMethod(string $methods): string
    {
        return strtoupper((string) collect(explode('|', $methods))->first() ?: 'GET');
    }

    private function buildPtlaTutorials(array $routes): array
    {
        return collect($routes)->map(function (array $route, int $index) {
            $method = strtoupper((string) ($route['primary_method'] ?? 'GET'));
            $uri = (string) ($route['uri'] ?? '/api/application');
            $uriExample = $this->interpolateUri($uri);
            $query = $this->ptlaQueryExample($method, $uri);
            $body = $this->ptlaBodyExample($method, $uri);

            $path = $uriExample;
            if (!empty($query)) {
                $path .= '?' . http_build_query($query);
            }

            $curl = ['curl -X ' . $method . ' "https://panel.example.com' . $path . '"'];
            $curl[] = '  -H "Authorization: Bearer ptla_xxx"';
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $curl[] = '  -H "Content-Type: application/json"';
            }
            if (is_array($body) && !empty($body)) {
                $curl[] = "  -d '" . json_encode($body, JSON_UNESCAPED_SLASHES) . "'";
            }

            return [
                'id' => 'ptla-guide-' . ($index + 1),
                'method' => $method,
                'uri' => $uri,
                'uri_example' => $uriExample,
                'name' => (string) ($route['name'] ?? '-'),
                'validator' => (string) ($route['validator'] ?? '-'),
                'query' => $query,
                'body' => $body,
                'curl' => implode(" \\\n", $curl),
            ];
        })->values()->all();
    }

    private function buildTutorials(array $routes, string $tokenPrefix, string $group): array
    {
        return collect($routes)->map(function (array $route, int $index) use ($tokenPrefix, $group) {
            $method = strtoupper((string) ($route['primary_method'] ?? 'GET'));
            $uri = (string) ($route['uri'] ?? '/');
            $uriExample = $this->interpolateUri($uri);
            $query = $this->queryExample($group, $method, $uri);
            $body = $this->bodyExample($group, $method, $uri);

            $path = $uriExample;
            if (!empty($query)) {
                $path .= '?' . http_build_query($query);
            }

            $curl = ['curl -X ' . $method . ' "https://panel.example.com' . $path . '"'];
            $curl[] = '  -H "Authorization: Bearer ' . $tokenPrefix . '_xxx"';
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $curl[] = '  -H "Content-Type: application/json"';
            }
            if (is_array($body) && !empty($body)) {
                $curl[] = "  -d '" . json_encode($body, JSON_UNESCAPED_SLASHES) . "'";
            }

            return [
                'id' => $group . '-guide-' . ($index + 1),
                'method' => $method,
                'uri' => $uri,
                'uri_example' => $uriExample,
                'name' => (string) ($route['name'] ?? '-'),
                'validator' => (string) ($route['validator'] ?? '-'),
                'query' => $query,
                'body' => $body,
                'curl' => implode(" \\\n", $curl),
            ];
        })->values()->all();
    }

    private function interpolateUri(string $uri): string
    {
        return (string) preg_replace_callback('/\{([^}]+)\}/', function (array $matches) {
            return $this->placeholderValue((string) $matches[1]);
        }, $uri);
    }

    private function placeholderValue(string $raw): string
    {
        $key = trim(strtolower(str_replace('?', '', $raw)));

        return match ($key) {
            'location' => '1',
            'nest' => '1',
            'egg' => '1',
            'node' => '1',
            'allocation' => '10',
            'server' => '1',
            'database' => '1',
            'user' => '1',
            'schedule' => '1',
            'task' => '1',
            'backup' => '1',
            'webhookid' => '1',
            'external_id' => 'ext-demo-001',
            'force' => 'force',
            default => '1',
        };
    }

    private function queryExample(string $group, string $method, string $uri): array
    {
        if ($method !== 'GET') {
            return [];
        }

        if ($group === 'ptlc') {
            return match ($uri) {
                '/api/client/servers/{server}/files/list' => ['directory' => '/'],
                '/api/client/servers/{server}/files/contents' => ['file' => '/index.js'],
                '/api/client/servers/{server}/files/download' => ['file' => '/backup.zip'],
                '/api/client/account/chat/messages',
                '/api/client/servers/{server}/chat/messages' => ['page' => 1, 'per_page' => 50],
                default => [],
            };
        }

        if ($group === 'ptlr') {
            return match ($uri) {
                '/api/rootapplication/servers/offline' => ['per_page' => 50],
                '/api/rootapplication/servers/reputations' => ['min_trust' => 60, 'per_page' => 50],
                '/api/rootapplication/audit/timeline' => ['risk_level' => 'high', 'per_page' => 50],
                '/api/rootapplication/security/node/runtime-summary',
                '/api/rootapplication/security/node/score' => ['server_id' => 1],
                default => [],
            };
        }

        if ($group === 'ptld') {
            return match ($uri) {
                '/api/remote/servers' => ['page' => 1, 'per_page' => 50],
                default => [],
            };
        }

        return [];
    }

    private function bodyExample(string $group, string $method, string $uri): ?array
    {
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        if ($group === 'ptlc') {
            return match ($uri) {
                '/api/client/servers/{server}/power' => ['signal' => 'start'],
                '/api/client/servers/{server}/command' => ['command' => 'npm run start'],
                '/api/client/servers/{server}/settings/rename' => ['name' => 'My Renamed Server'],
                '/api/client/servers/{server}/settings/reinstall' => [],
                '/api/client/servers/{server}/settings/docker-image' => ['docker_image' => 'ghcr.io/pterodactyl/yolks:nodejs_22'],
                '/api/client/servers/{server}/ide/session' => ['terminal' => true, 'extensions' => true],
                '/api/client/account/email' => ['email' => 'owner@example.com', 'password' => 'CurrentPassword!'],
                '/api/client/account/password' => ['current_password' => 'CurrentPassword!', 'password' => 'NewStrongPass123!', 'password_confirmation' => 'NewStrongPass123!'],
                '/api/client/account/chat/messages',
                '/api/client/servers/{server}/chat/messages' => ['message' => 'Hello from API'],
                default => ['_note' => 'See endpoint validator for required fields.'],
            };
        }

        if ($group === 'ptlr') {
            return match ($uri) {
                '/api/rootapplication/security/settings' => [
                    'panic_mode' => false,
                    'maintenance_mode' => false,
                    'progressive_security_mode' => 'normal',
                    'ide_connect_enabled' => true,
                    'ide_session_ttl_minutes' => 10,
                    'node_secure_mode_enabled' => true,
                    'node_secure_container_policy_enabled' => true,
                    'node_secure_container_min_major' => 18,
                    'node_secure_container_preferred_major' => 22,
                ],
                '/api/rootapplication/security/emergency-mode' => ['enabled' => true],
                '/api/rootapplication/security/trust-automation/run' => [],
                '/api/rootapplication/adaptive/run' => ['window_minutes' => 30],
                '/api/rootapplication/security/simulate' => ['scenario' => 'ddos_probe'],
                '/api/rootapplication/reputation-network/sync' => ['direction' => 'pull'],
                '/api/rootapplication/ecosystem/webhooks' => ['url' => 'https://example.com/hook', 'events' => ['security.alert']],
                '/api/rootapplication/ecosystem/webhooks/{webhookId}/toggle' => ['enabled' => false],
                '/api/rootapplication/security/node/safe-deploy-scan' => ['server_id' => 1, 'path' => '/home/container'],
                '/api/rootapplication/security/node/npm-audit' => ['server_id' => 1, 'path' => '/home/container', 'production' => true],
                '/api/rootapplication/security/node/runtime-sample' => ['server_id' => 1, 'rss_mb' => 512, 'heap_used_mb' => 280, 'heap_total_mb' => 360, 'external_mb' => 35, 'uptime_sec' => 7200],
                '/api/rootapplication/security/node/container-policy-check' => ['server_id' => 1, 'docker_image' => 'ghcr.io/pterodactyl/yolks:nodejs_18'],
                '/api/rootapplication/ide/sessions/validate' => ['token' => 'hexide_token_from_client_api', 'consume' => false],
                '/api/rootapplication/ide/sessions/revoke' => ['server_id' => 1],
                default => ['_note' => 'See endpoint validator for required fields.'],
            };
        }

        if ($group === 'ptld') {
            return match ($uri) {
                '/api/remote/sftp/auth' => ['username' => 'root', 'password' => 'secret', 'ip' => '127.0.0.1', 'type' => 'password'],
                '/api/remote/activity' => ['event' => 'server.console.command', 'metadata' => ['server_uuid' => 'uuid-value']],
                '/api/remote/servers/reset' => ['uuid' => 'server-uuid'],
                '/api/remote/servers/{uuid}/install' => ['successful' => true, 'reinstall' => false],
                '/api/remote/servers/{uuid}/transfer/failure' => ['message' => 'transfer failed'],
                '/api/remote/servers/{uuid}/transfer/success' => ['message' => 'transfer complete'],
                '/api/remote/backups/{backup}' => ['successful' => true, 'checksum' => 'sha256:...'],
                '/api/remote/backups/{backup}/restore' => ['successful' => true],
                default => ['_note' => 'Daemon/Wings internal endpoint, see validator for contract.'],
            };
        }

        return null;
    }

    private function resolveValidatorClass(Route $route): string
    {
        $action = (string) $route->getActionName();
        if ($action === '' || !str_contains($action, '@')) {
            return '-';
        }

        [$controller, $method] = explode('@', $action, 2);
        if (!class_exists($controller) || $method === '') {
            return '-';
        }

        try {
            $reflection = new ReflectionMethod($controller, $method);
        } catch (ReflectionException) {
            return '-';
        }

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type || $type->isBuiltin()) {
                continue;
            }

            $name = $type->getName();
            if (is_subclass_of($name, FormRequest::class)) {
                return $name;
            }
        }

        return '-';
    }

    private function ptlaQueryExample(string $method, string $uri): array
    {
        if ($method !== 'GET') {
            return [];
        }

        return match ($uri) {
            '/api/application/locations',
            '/api/application/nodes',
            '/api/application/users' => ['page' => 1, 'per_page' => 25],

            '/api/application/servers' => ['page' => 1, 'per_page' => 25, 'include' => 'node,allocations'],
            '/api/application/servers/offline' => ['per_page' => 50],
            '/api/application/nodes/deployable' => ['location_id' => 1],
            default => [],
        };
    }

    private function ptlaBodyExample(string $method, string $uri): ?array
    {
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return null;
        }

        return match (true) {
            $method === 'POST' && $uri === '/api/application/users' => [
                'email' => 'newuser@example.com',
                'username' => 'newuser',
                'first_name' => 'New',
                'last_name' => 'User',
                'password' => 'StrongPass123!',
                'root_admin' => false,
                'role_id' => 2,
                'language' => 'en',
            ],
            $method === 'PATCH' && $uri === '/api/application/users/{user}' => [
                'first_name' => 'Updated',
                'last_name' => 'User',
                'email' => 'updated@example.com',
                'role_id' => 2,
            ],
            $method === 'POST' && $uri === '/api/application/servers' => [
                'name' => 'My Server',
                'visibility' => 'public',
                'user' => 2,
                'egg' => 5,
                'docker_image' => 'ghcr.io/pterodactyl/yolks:nodejs_22',
                'startup' => 'npm run start',
                'environment' => ['AUTO_UPDATE' => '0'],
                'limits' => ['memory' => 2048, 'swap' => 0, 'disk' => 10240, 'io' => 500, 'cpu' => 100],
                'feature_limits' => ['databases' => 2, 'allocations' => 1, 'backups' => 2],
                'allocation' => ['default' => 10],
            ],
            $method === 'PATCH' && $uri === '/api/application/servers/{server}/details' => [
                'name' => 'Renamed Server',
                'description' => 'Updated by PTLA',
                'visibility' => 'private',
            ],
            $method === 'PATCH' && $uri === '/api/application/servers/{server}/build' => [
                'allocation' => 10,
                'memory' => 4096,
                'swap' => 0,
                'disk' => 20480,
                'io' => 500,
                'cpu' => 150,
                'threads' => null,
                'feature_limits' => ['databases' => 5, 'allocations' => 2, 'backups' => 5],
            ],
            $method === 'PATCH' && $uri === '/api/application/servers/{server}/startup' => [
                'startup' => 'npm run start',
                'environment' => ['NODE_ENV' => 'production'],
            ],
            $method === 'POST' && $uri === '/api/application/servers/{server}/databases' => [
                'database' => 'appdb',
                'remote' => '%',
                'host' => 1,
                'max_connections' => 25,
            ],
            $method === 'POST' && $uri === '/api/application/servers/{server}/databases/{database}/reset-password' => [],
            $method === 'POST' && $uri === '/api/application/servers/{server}/reinstall' => [],
            $method === 'POST' && $uri === '/api/application/servers/{server}/suspend' => [],
            $method === 'POST' && $uri === '/api/application/servers/{server}/unsuspend' => [],
            $method === 'POST' && $uri === '/api/application/locations' => [
                'short' => 'sgp',
                'long' => 'Singapore DC',
            ],
            $method === 'PATCH' && $uri === '/api/application/locations/{location}' => [
                'short' => 'sgp',
                'long' => 'Singapore DC Updated',
            ],
            $method === 'POST' && $uri === '/api/application/nodes' => [
                'name' => 'node-01',
                'location_id' => 1,
                'fqdn' => 'node01.example.com',
                'scheme' => 'https',
                'memory' => 16384,
                'memory_overallocate' => 0,
                'disk' => 204800,
                'disk_overallocate' => 0,
                'upload_size' => 100,
                'daemon_sftp' => 2022,
                'daemon_listen' => 8080,
            ],
            $method === 'PATCH' && $uri === '/api/application/nodes/{node}' => [
                'name' => 'node-01-updated',
                'location_id' => 1,
                'fqdn' => 'node01.example.com',
                'scheme' => 'https',
                'memory' => 16384,
                'memory_overallocate' => 0,
                'disk' => 204800,
                'disk_overallocate' => 0,
                'upload_size' => 100,
                'daemon_sftp' => 2022,
                'daemon_listen' => 8080,
                'maintenance_mode' => false,
            ],
            $method === 'POST' && $uri === '/api/application/nodes/{node}/allocations' => [
                'ip' => '203.0.113.10',
                'alias' => 'public-ip-1',
                'ports' => ['25565', '3000-3010'],
            ],
            default => ['_note' => 'See endpoint validator for required fields.'],
        };
    }
}
