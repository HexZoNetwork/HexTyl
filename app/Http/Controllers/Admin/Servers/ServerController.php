<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Filters\AdminServerFilter;

class ServerController extends Controller
{
    /**
     * Returns all the servers that exist on the system using a paginated result set. If
     * a query is passed along in the request it is also passed to the repository function.
     */
    public function index(Request $request): View
    {
        $query = QueryBuilder::for(Server::query()->with('node', 'user', 'allocation'))
            ->allowedFilters([
                AllowedFilter::exact('owner_id'),
                AllowedFilter::custom('*', new AdminServerFilter()),
            ]);

        $state = strtolower((string) $request->query('state', ''));
        if ($state === 'off' || $state === 'offline') {
            $query->whereNotNull('status');
        } elseif ($state === 'on' || $state === 'online') {
            $query->whereNull('status');
        }

        $servers = $query->paginate(config()->get('pterodactyl.paginate.admin.servers'));

        return view('admin.servers.index', [
            'servers' => $servers,
            'state' => $state,
        ]);
    }
}
