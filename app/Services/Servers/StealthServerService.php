<?php

namespace Pterodactyl\Services\Servers;

use Pterodactyl\Models\Server;
use Illuminate\Database\Eloquent\Builder;

/**
 * StealthServerService handles server visibility filtering.
 *
 * NOTE: The DB enum for visibility only supports 'private' and 'public'.
 * There is no 'stealth' value — that concept was removed in favour of
 * the standard two-value visibility model. This service now safely
 * filters to only show public servers when not running as root.
 */
class StealthServerService
{
    /**
     * Apply visibility filter to a server query.
     * Non-root users only see public servers via this filter;
     * root users see everything.
     */
    public function applyVisibility(Builder $query, bool $isRoot = false): Builder
    {
        if ($isRoot) {
            return $query;
        }

        // Only show public servers to non-root callers.
        return $query->where('visibility', Server::VISIBILITY_PUBLIC);
    }

    /**
     * Make a server private (hide from public listing).
     * This is the only safe equivalent of the old "stealth" concept.
     */
    public function makePrivate(Server $server): void
    {
        $server->forceFill(['visibility' => Server::VISIBILITY_PRIVATE])->save();
    }

    /**
     * Make a server public.
     */
    public function makePublic(Server $server): void
    {
        $server->forceFill(['visibility' => Server::VISIBILITY_PUBLIC])->save();
    }
}
