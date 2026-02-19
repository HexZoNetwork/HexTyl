<?php

namespace Pterodactyl\Services\Testing;

use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Server;

class AbuseSimulationService
{
    /**
     * Simulate a high-load HTTP flood attack on the panel itself (Localhost loopback).
     * WARNING: Only run in controlled environments.
     */
    public function simulateHttpFlood(int $requests = 100): array
    {
        $url = config('app.url');
        $success = 0;
        $failed = 0;
        
        // This is a synchronous simulation, not parallel. 
        // Real testing would use 'ab' or 'wrk'.
        
        for ($i = 0; $i < $requests; $i++) {
            try {
                $response = Http::timeout(1)->get($url);
                if ($response->successful()) {
                    $success++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Simulate a CPU spike on a server node.
     * This acts as a stress test trigger.
     */
    public function simulateCpuSpike(Server $server): void
    {
        // Trigger a stress tool inside the container
        // stress --cpu 8 --timeout 60s
        
        \Log::warning("Simulating CPU spike on server {$server->uuid}");
    }
}
