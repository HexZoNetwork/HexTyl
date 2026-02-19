<?php

namespace Pterodactyl\Http\Controllers\Api\Application;

use Pterodactyl\Models\Server;
use Illuminate\Http\Request;
use Pterodactyl\Services\Admins\AdminScopeService;
use Pterodactyl\Transformers\Api\Application\ServerTransformer;

class GlobalSearchController extends ApplicationApiController
{
    public function __construct(private AdminScopeService $scopeService)
    {
        parent::__construct();
    }

    /**
     * Search servers across the platform.
     */
    public function index(Request $request)
    {
        $query = $request->input('query');
        $user = $request->user();

        // Base query
        $servers = Server::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('uuid', 'LIKE', "%{$query}%");
            
        // Filter based on visibility/scope using Service
        // This is a bit inefficient for SQL, so we should scope the query directly.
        
        if (!$user->isRoot()) {
            $servers->where(function ($q) use ($user) {
                // Public servers
                $q->where('visibility', 'public');
                
                // OR private servers IF user has scope
                if ($user->hasScope('server:private:view')) {
                    $q->orWhere('visibility', 'private');
                }
            });
        }
        
        return $this->fractal->collection($servers->paginate(20))
            ->transformWith($this->getTransformer(ServerTransformer::class))
            ->respond();
    }
}
