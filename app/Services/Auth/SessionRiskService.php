<?php

namespace Pterodactyl\Services\Auth;

use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Facades\Activity;

class SessionRiskService
{
    /**
     * Check if the login request is from a known IP address.
     * Uses the activity log to find previous successful logins.
     */
    public function isKnownIp(User $user, string $ip): bool
    {
        // Simple check: Has this user logged in from this IP before?
        // We check the activity logs for 'auth:login' or 'auth:checkpoint' events.
        
        $exists = DB::table('activity_logs')
            ->where('actor_id', $user->id)
            ->where('ip', $ip)
            ->whereIn('event', ['auth:login', 'auth:checkpoint', 'auth:success'])
            ->exists();

        return $exists;
    }

    /**
     * Handle risk detection.
     * Returns true if risk is detected (e.g., new IP), false otherwise.
     */
    public function handle(User $user, Request $request): bool
    {
        $ip = $request->ip();

        if (!$this->isKnownIp($user, $ip)) {
            // Log the new IP detection
            Activity::event('auth:risk.new_ip')
                ->actor($user)
                ->subject($user)
                ->property('ip', $ip)
                ->log('Detected login from new IP address.');

            // In a full implementation, we might trigger an email verification here.
            // For now, we flag it.
            return true;
        }

        return false;
    }
}
