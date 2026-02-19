<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Support\Collection;
use Illuminate\Filesystem\Filesystem;

class LogStreamService
{
    public function __construct(private Filesystem $filesystem)
    {
    }

    /**
     * Read and filter server logs.
     * 
     * @param Server $server
     * @param string $keyword
     * @param bool $errorsOnly
     * @param int $lines
     * @return Collection
     */
    public function getFilteredLogs(Server $server, string $keyword = '', bool $errorsOnly = false, int $lines = 100): Collection
    {
        // In a real scenario, this would interface with the Wings daemon which holds the logs.
        // For this backend simulation, we assume we might have local access or proxy the request.
        // This is a logic placeholder for the "Filter" feature requested.
        
        // Mock log data for demonstration if actual source isn't reachable
        $rawLogs = [
            '[INFO] Server started.',
            '[INFO] Loading map...',
            '[ERROR] Texture missing: grass.png',
            '[WARN] High latency detected.',
            '[INFO] Player joined: Hexzo',
            '[ERROR] Connection lost.',
        ];

        return collect($rawLogs)
            ->filter(function ($line) use ($keyword, $errorsOnly) {
                if ($errorsOnly && !str_contains($line, '[ERROR]')) {
                    return false;
                }
                
                if (!empty($keyword) && !str_contains($line, $keyword)) {
                    return false;
                }
                
                return true;
            })
            ->take(-$lines);
    }
}
