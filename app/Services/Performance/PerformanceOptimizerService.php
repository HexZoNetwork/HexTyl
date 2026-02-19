<?php

namespace Pterodactyl\Services\Performance;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Request;

class PerformanceOptimizerService
{
    /**
     * Update Node.js version for a server.
     */
    public function updateNodeVersion(Server $server, string $version): void
    {
        // Valid versions: 16, 18, 20
        // In reality, updates the docker image tag or startup command env var.
        
        $image = match ($version) {
            '16' => 'ghcr.io/parkervcp/yolks:nodejs_16',
            '18' => 'ghcr.io/parkervcp/yolks:nodejs_18',
            '20' => 'ghcr.io/parkervcp/yolks:nodejs_20',
            default => null,
        };

        if ($image) {
            $server->image = $image;
            $server->save();
            
            // Trigger rebuild logic
        }
    }

    /**
     * Enable/Disable PM2 Cluster Mode.
     */
    public function togglePM2(Server $server, bool $enable): void
    {
        // Adjust startup command to include pm2
        // "pm2 start index.js -i max" vs "node index.js"
        
        $startup = $server->startup;
        
        if ($enable && !str_contains($startup, 'pm2')) {
            // Very simpler heuristic replacement
            $server->startup = str_replace('node', 'pm2 start', $startup) . ' -i max';
        } else if (!$enable) {
            $server->startup = str_replace(['pm2 start', ' -i max'], ['node', ''], $startup);
        }
        
        $server->save();
    }
}
