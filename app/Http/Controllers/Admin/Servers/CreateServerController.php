<?php

namespace Pterodactyl\Http\Controllers\Admin\Servers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Server;
use Illuminate\Http\RedirectResponse;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Repositories\Eloquent\NestRepository;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Http\Requests\Admin\ServerFormRequest;
use Pterodactyl\Services\Admins\AdminScopeService;
use Pterodactyl\Services\Servers\ServerCreationService;

class CreateServerController extends Controller
{
    /**
     * CreateServerController constructor.
     */
    public function __construct(
        private AlertsMessageBag $alert,
        private NestRepository $nestRepository,
        private NodeRepository $nodeRepository,
        private AdminScopeService $scopeService,
        private ServerCreationService $creationService,
    ) {
    }

    /**
     * Displays the create server page.
     *
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     */
    public function index(): View|RedirectResponse
    {
        $this->scopeService->ensureCanCreateServers(request()->user());
        if ($this->isServerCreationHidden() && !request()->user()->isRoot()) {
            $this->alert->warning('Server creation is temporarily hidden by emergency policy.')->flash();

            return redirect()->route('admin.servers');
        }

        $nodes = Node::all();
        if (count($nodes) < 1) {
            $this->alert->warning(trans('admin/server.alerts.node_required'))->flash();

            return redirect()->route('admin.nodes');
        }

        $nests = $this->nestRepository->getWithEggs();

        \JavaScript::put([
            'nodeData' => $this->nodeRepository->getNodesForServerCreation(),
            'nests' => $nests->map(function (Nest $item) {
                return array_merge($item->toArray(), [
                    'eggs' => $item->eggs->keyBy('id')->toArray(),
                ]);
            })->keyBy('id'),
        ]);

        return view('admin.servers.new', [
            'locations' => Location::all(),
            'nests' => $nests,
        ]);
    }

    /**
     * Create a new server on the remote system.
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException
     * @throws \Throwable
     */
    public function store(ServerFormRequest $request): RedirectResponse
    {
        if ($this->isServerCreationHidden() && !$request->user()->isRoot()) {
            $this->alert->danger('Server creation is temporarily disabled by emergency policy.')->flash();

            return redirect()->route('admin.servers');
        }

        $visibility = (string) ($request->input('visibility') ?: Server::VISIBILITY_PRIVATE);
        $this->scopeService->ensureCanCreateWithVisibility($request->user(), $visibility);

        $data = $request->except(['_token']);
        if (!empty($data['custom_image'])) {
            $data['image'] = $data['custom_image'];
            unset($data['custom_image']);
        }

        $server = $this->creationService->handle($data);

        $this->alert->success(trans('admin/server.alerts.server_created'))->flash();

        if (!$this->scopeService->canViewServer($request->user(), $server)) {
            return redirect()->route('admin.servers');
        }

        return new RedirectResponse('/admin/servers/view/' . $server->id);
    }

    private function isServerCreationHidden(): bool
    {
        return filter_var(
            (string) Cache::remember('system:hide_server_creation', 30, function () {
                return (string) (DB::table('system_settings')->where('key', 'hide_server_creation')->value('value') ?? 'false');
            }),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
