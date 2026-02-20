<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Routing\Route;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;

class DocumentationController extends Controller
{
    public function index(): View
    {
        $ptlaRoutes = $this->collectRoutesByPrefix('api/application');

        return view('docs.index', [
            'ptlaRoutes' => $ptlaRoutes,
            'ptlcRoutes' => $this->collectRoutesByPrefix('api/client'),
            'ptlrRoutes' => $this->collectRoutesByPrefix('api/rootapplication'),
            'ptlaTutorials' => $this->buildPtlaTutorials($ptlaRoutes),
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
            'external_id' => 'ext-demo-001',
            'force' => 'force',
            default => '1',
        };
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
                'language' => 'en',
            ],
            $method === 'PATCH' && $uri === '/api/application/users/{user}' => [
                'first_name' => 'Updated',
                'last_name' => 'User',
                'email' => 'updated@example.com',
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
