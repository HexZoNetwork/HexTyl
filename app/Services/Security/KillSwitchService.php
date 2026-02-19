<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Cache;

class KillSwitchService
{
    /**
     * Enable API Kill Switch.
     * Blocks all API access except for whitelisted IPs.
     */
    public function enable(array $whitelistIps = []): void
    {
        Cache::forever('api:kill_switch:enabled', true);
        Cache::forever('api:kill_switch:whitelist', $whitelistIps);
        
        \Illuminate\Support\Facades\Log::emergency("API KILL SWITCH ENABLED. Only whitelisted IPs can access.");
    }

    /**
     * Disable API Kill Switch.
     */
    public function disable(): void
    {
        Cache::forget('api:kill_switch:enabled');
        Cache::forget('api:kill_switch:whitelist');
        
        \Illuminate\Support\Facades\Log::info("API Kill Switch disabled.");
    }

    /**
     * Check if request should be blocked.
     */
    public function shouldBlock(string $ip): bool
    {
        if (!Cache::get('api:kill_switch:enabled')) {
            return false;
        }

        $whitelist = Cache::get('api:kill_switch:whitelist', []);
        
        return !in_array($ip, $whitelist);
    }
}
