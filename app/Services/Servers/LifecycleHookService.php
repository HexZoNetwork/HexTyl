<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Storage;

class LifecycleHookService
{
    /**
     * Save a lifecycle hook script.
     * Types: before_start, after_stop, before_restart
     */
    public function saveHook(Server $server, string $type, string $scriptContent): void
    {
        // Validation of type
        if (!in_array($type, ['before_start', 'after_stop', 'before_restart'])) {
            throw new \InvalidArgumentException("Invalid hook type: {$type}");
        }

        // Store script safely
        // In reality, this might be stored in the database or passed to Wings
        // Storing in a special directory for now.
        
        $path = "servers/{$server->uuid}/hooks/{$type}.sh";
        Storage::disk('local')->put($path, $scriptContent);
    }

    /**
     * Execute a hook (Triggered by events).
     */
    public function executeHook(Server $server, string $type): void
    {
        $path = "servers/{$server->uuid}/hooks/{$type}.sh";
        
        if (Storage::disk('local')->exists($path)) {
            $script = Storage::disk('local')->get($path);
            
            // Dispatch to Wings to execute inside container context (or pre-container)
            // Wings::send($server, 'execute_hook', ['script' => $script]);
            
            \Log::info("Triggered {$type} hook for server {$server->uuid}");
        }
    }
}
