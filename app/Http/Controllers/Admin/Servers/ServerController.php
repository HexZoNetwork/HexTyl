<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Pterodactyl\Models\Server;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Filters\AdminServerFilter;
use Pterodactyl\Services\Admins\AdminScopeService;

class ServerController extends Controller
{
    public function __construct(private AdminScopeService $scopeService)
    {
    }

    /**
     * Returns all the servers that exist on the system using a paginated result set. If
     * a query is passed along in the request it is also passed to the repository function.
     */
    public function index(Request $request): View
    {
        $actor = $request->user();
        $this->scopeService->ensureCanReadServers($actor);

        $query = QueryBuilder::for(Server::query()->with('node', 'user', 'allocation'))
            ->allowedFilters([
                AllowedFilter::exact('owner_id'),
                AllowedFilter::custom('*', new AdminServerFilter()),
            ]);

        if (!$actor->isRoot() && !$actor->hasScope('server:private:view')) {
            $query->where('servers.visibility', Server::VISIBILITY_PUBLIC);
        }

        $state = strtolower((string) $request->query('state', ''));
        if ($state === 'off' || $state === 'offline') {
            $query->whereNotNull('status');
        } elseif ($state === 'on' || $state === 'online') {
            $query->whereNull('status');
        }

        $servers = $query->paginate(config()->get('pterodactyl.paginate.admin.servers'));
        $hideServerCreation = filter_var(
            (string) Cache::remember('system:hide_server_creation', 30, function () {
                return (string) (DB::table('system_settings')->where('key', 'hide_server_creation')->value('value') ?? 'false');
            }),
            FILTER_VALIDATE_BOOLEAN
        );

        return view('admin.servers.index', [
            'servers' => $servers,
            'state' => $state,
            'hideServerCreation' => $hideServerCreation,
        ]);
    }
}
