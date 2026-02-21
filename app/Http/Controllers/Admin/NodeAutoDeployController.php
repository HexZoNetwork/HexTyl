<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Pterodactyl\Models\Node;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodes\NodeBootstrapPayloadService;

class NodeAutoDeployController extends Controller
{
    /**
     * NodeAutoDeployController constructor.
     */
    public function __construct(
        private NodeBootstrapPayloadService $payloadService,
    ) {
    }

    /**
     * Generates a new API key for the logged-in user with only permission to read
     * nodes, and returns that as the deployment key for a node.
     *
     * @throws \Pterodactyl\Exceptions\Model\DataValidationException
     */
    public function __invoke(Request $request, Node $node): JsonResponse
    {
        return new JsonResponse($this->payloadService->forUserAndNode($request->user(), $node));
    }
}
