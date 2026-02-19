<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Builder;

class StealthServerService
{
    /**
     * Apply stealth filter to a query.
     * Stealth servers should NOT appear in listing unless searched by exact ID/UUID.
     */
    public function applyVisibility(Builder $query, bool $isRoot = false): Builder
    {
        if ($isRoot) {
            return $query;
        }

        // Exclude servers marked as stealth
        // Assuming 'visibility' column has 'stealth' enum value now.
        return $query->where('visibility', '!=', 'stealth');
    }

    /**
     * Mark a server as stealth.
     */
    public function enableStealth(Server $server): void
    {
        $server->visibility = 'stealth';
        $server->save();
    }
}
