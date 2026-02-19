<?php

namespace Pterodactyl\Http\Controllers\Api\Application\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Services\Admins\AdminScopeService;
use Pterodactyl\Transformers\Api\Application\ServerTransformer;
use Pterodactyl\Http\Controllers\Api\Application\ApplicationApiController;
use Pterodactyl\Http\Requests\Api\Application\Servers\GetExternalServerRequest;

class ExternalServerController extends ApplicationApiController
{
    public function __construct(private AdminScopeService $scopeService)
    {
        parent::__construct();
    }

    /**
     * Retrieve a specific server from the database using its external ID.
     */
    public function index(GetExternalServerRequest $request, string $external_id): array
    {
        $server = Server::query()->where('external_id', $external_id)->firstOrFail();
        $this->scopeService->ensureCanViewServer($request->user(), $server);

        return $this->fractal->item($server)
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->toArray();
    }
}
