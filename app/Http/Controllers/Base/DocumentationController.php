<?php

namespace Pterodactyl\Http\Controllers\Base;

use Illuminate\Routing\Route;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;

class DocumentationController extends Controller
{
    public function index(): View
    {
        return view('docs.index', [
            'ptlaRoutes' => $this->collectRoutesByPrefix('api/application'),
            'ptlcRoutes' => $this->collectRoutesByPrefix('api/client'),
            'ptlrRoutes' => $this->collectRoutesByPrefix('api/rootapplication'),
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
                    'uri' => '/' . ltrim($route->uri(), '/'),
                    'name' => $route->getName() ?? '-',
                ];
            })
            ->sortBy('uri')
            ->values()
            ->all();

        return $routes;
    }
}
