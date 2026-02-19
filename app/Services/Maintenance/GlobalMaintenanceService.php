<?php

namespace Pterodactyl\Services\Maintenance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GlobalMaintenanceService
{
    /**
     * Enable Maintenance Mode.
     * Actions: Lock panel, Stop public servers (optional), Banner.
     */
    public function enable(string $message = 'System Maintenance', bool $stopServers = false): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'maintenance_mode'],
            ['value' => true]
        );
        
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'maintenance_message'],
            ['value' => $message]
        );

        Cache::put('system:maintenance_mode', true);
        Cache::put('system:maintenance_message', $message);

        if ($stopServers) {
            // Dispatch job to stop all non-admin servers
            // StopPublicServersJob::dispatch();
        }
    }

    /**
     * Disable Maintenance Mode.
     */
    public function disable(): void
    {
        DB::table('system_settings')->where('key', 'maintenance_mode')->update(['value' => false]);
        Cache::forget('system:maintenance_mode');
    }

    /**
     * Check if active.
     */
    public function isActive(): bool
    {
        return Cache::get('system:maintenance_mode', false);
    }
}
