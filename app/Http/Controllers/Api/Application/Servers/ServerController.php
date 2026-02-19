<?php

namespace Pterodactyl\Http\Controllers\Api\Application\Servers;

use Illuminate\Http\Response;
use Pterodactyl\Models\Server;
use Illuminate\Http\JsonResponse;
use Spatie\QueryBuilder\QueryBuilder;
use Pterodactyl\Services\Admins\AdminScopeService;
use Pterodactyl\Services\Servers\ServerCreationService;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Transformers\Api\Application\ServerTransformer;
use Pterodactyl\Http\Requests\Api\Application\Servers\GetServerRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\GetServersRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\ServerWriteRequest;
use Pterodactyl\Http\Requests\Api\Application\Servers\StoreServerRequest;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;

class ServerController extends ApplicationApiController
{
    /**
     * ServerController constructor.
     */
    public function __construct(
        private AdminScopeService $scopeService,
        private ServerCreationService $creationService,
        private ServerDeletionService $deletionService,
    ) {
        parent::__construct();
    }

    /**
     * Return all the servers that currently exist on the Panel.
     */
    public function index(GetServersRequest $request): array
    {
        $actor = $request->user();
        $this->scopeService->ensureCanReadServers($actor);

        $query = QueryBuilder::for(Server::query())
            ->allowedFilters(['uuid', 'uuidShort', 'name', 'description', 'image', 'external_id'])
            ->allowedSorts(['id', 'uuid']);

        if (!$actor->isRoot() && !$actor->hasScope('server:private:view')) {
            $query->where('servers.visibility', Server::VISIBILITY_PUBLIC);
        }

        $state = strtolower((string) $request->query('state', ''));
        if ($state === 'off' || $state === 'offline') {
            $query->whereNotNull('status');
        } elseif ($state === 'on' || $state === 'online') {
            $query->whereNull('status');
        }

        $servers = $query->paginate($request->query('per_page') ?? 50);

        return $this->fractal->collection($servers)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }

    /**
     * Find offline servers quickly (for application keys / PTLA usage).
     */
    public function offline(GetServersRequest $request): array
    {
        $actor = $request->user();
        $this->scopeService->ensureCanReadServers($actor);

        $servers = QueryBuilder::for(Server::query()->whereNotNull('status'))
            ->allowedFilters(['uuid', 'uuidShort', 'name', 'description', 'image', 'external_id'])
            ->allowedSorts(['id', 'uuid'])
            ->when(
                !$actor->isRoot() && !$actor->hasScope('server:private:view'),
                fn ($query) => $query->where('servers.visibility', Server::VISIBILITY_PUBLIC)
            )
            ->paginate($request->query('per_page') ?? 50);

        return $this->fractal->collection($servers)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }

    /**
     * Create a new server on the system.
     *
     * @throws \Throwable
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     * @throws \Pterodactyl\Exceptions\Repository\RecordNotFoundException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableAllocationException
     * @throws \Pterodactyl\Exceptions\Service\Deployment\NoViableNodeException
     */
    public function store(StoreServerRequest $request): JsonResponse
    {
        $visibility = (string) ($request->validated()['visibility'] ?? Server::VISIBILITY_PRIVATE);
        $this->scopeService->ensureCanCreateWithVisibility($request->user(), $visibility);

        $server = $this->creationService->handle($request->validated(), $request->getDeploymentObject());

        return $this->fractal->item($server)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->respond(201);
    }

    /**
     * Show a single server transformed for the application API.
     */
    public function view(GetServerRequest $request, Server $server): array
    {
        $this->scopeService->ensureCanViewServer($request->user(), $server);

        return $this->fractal->item($server)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }

    /**
     * Deletes a server.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     */
    public function delete(ServerWriteRequest $request, Server $server, string $force = ''): Response
    {
        $actor = $request->user();
        $this->scopeService->ensureCanViewServer($actor, $server);
        $this->scopeService->ensureCanDeleteServers($actor);

        $this->deletionService->withForce($force === 'force')->handle($server);

        return $this->returnNoContent();
    }
}
