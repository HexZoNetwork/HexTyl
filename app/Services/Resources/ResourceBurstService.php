<?php

namespace Pterodactyl\Services\Resources;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;

class ResourceBurstService
{
    /**
     * Activate burst mode for a server (e.g., +200% CPU for 5 minutes).
     */
    public function activate(Server $server): void
    {
        $key = "server:burst:{$server->id}";
        
        if (Cache::has($key)) {
            throw new \Exception("Burst mode is already active or in cooldown.");
        }

        // Apply burst limits to container
        // $this->daemonRepository->updateLimits($server, ['cpu' => 400]); // Mock 400%
        
        // Cache expiration handles the duration logic, but we need a job to revert it.
        Cache::put($key, true, now()->addMinutes(5));
        
        // Dispatch delayed job to revert limits
        // RevertResouceBurstJob::dispatch($server)->delay(now()->addMinutes(5));
        
        \Log::info("Activated resource burst for server {$server->uuid}");
    }

    /**
     * Revert burst limits.
     */
    public function revert(Server $server): void
    {
        // $this->daemonRepository->updateLimits($server, ['cpu' => $server->cpu]);
        Cache::forget("server:burst:{$server->id}");
        \Log::info("Reverted resource burst for server {$server->uuid}");
    }
}
