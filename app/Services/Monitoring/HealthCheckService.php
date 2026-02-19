<?php

namespace Pterodactyl\Services\Monitoring;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Http;

class HealthCheckService
{
    /**
     * Perform health check on a server.
     */
    public function check(Server $server): bool
    {
        // Try to ping the server's allocation (IP:Port)
        // If it's a web service, we might check an HTTP endpoint.
        // If it's a game/bot, we might check TCP connectivity.
        
        $allocation = $server->allocation;
        $ip = $allocation->ip;
        $port = $allocation->port;

        $connection = @fsockopen($ip, $port, $errno, $errstr, 2);

        if (is_resource($connection)) {
            fclose($connection);
            return true;
        }

        return false;
    }

    /**
     * Handle failed health check.
     */
    public function handleFailure(Server $server): void
    {
        // Increment failure count
        // If > 3 failures, suspend or restart.
        
        $failures = \Illuminate\Support\Facades\Cache::increment("server:health_failures:{$server->id}");

        if ($failures >= 3) {
            // Take action
            // $this->suspendService->handle($server);
            \Illuminate\Support\Facades\Cache::forget("server:health_failures:{$server->id}");
        }
    }
}
