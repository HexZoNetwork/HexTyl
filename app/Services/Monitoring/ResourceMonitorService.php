<?php

namespace Pterodactyl\Services\Monitoring;

use Pterodactyl\Models\Server;
use Pterodactyl\Facades\Activity;
use Illuminate\Support\Facades\Log;

class ResourceMonitorService
{
    /**
     * Check server resources and trigger actions if thresholds are exceeded.
     * This would typically be called by a scheduled job or listener.
     */
    public function check(Server $server, array $stats): void
    {
        // $stats = ['memory_bytes' => 1024, 'cpu_absolute' => 50, ...]
        
        $memoryLimit = $server->memory * 1024 * 1024; // MB to Bytes
        
        // 1. Auto-Restart Policy: Restart if RAM > 90%
        if ($memoryLimit > 0 && ($stats['memory_bytes'] / $memoryLimit) > 0.9) {
            $this->triggerRestart($server, 'Memory usage exceeded 90%');
        }

        // 2. Resource Advisor
        // "CPU spike tiap jam 12" logic would go here (storing metrics)
    }

    protected function triggerRestart(Server $server, string $reason): void
    {
        Log::info("Auto-restarting server {$server->uuid}: $reason");
        
        Activity::event('server:auto_restart')
            ->subject($server)
            ->property('reason', $reason)
            ->log('Server auto-restarted by monitor.');

        // Dispatch job to restart server via Wings
        // Request::post(.../power, 'restart')
    }
}
