<?php

namespace Pterodactyl\Services\Security;

use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SilentDefenseService
{
    /**
     * Check if request should be silently handled (Shadow Ban / Throttled).
     * Returns delay in seconds. 0 means no delay.
     */
    public function checkDelay(Request $request): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        $risk = app(BehavioralScoreService::class)->getScore($request->ip());

        if ($risk > 80) {
            // High risk: synthetic delay so attacker cannot infer hard blocking logic.
            return random_int(2, 5);
        }

        if ($risk > 50) {
            return 1;
        }

        return 0;
    }

    /**
     * Get adaptive rate limit based on User Reputation.
     */
    public function getAdaptiveLimit(User $user): int
    {
        // New users (created < 7 days) -> Strict
        if ($user->created_at->diffInDays(now()) < 7) {
            return 60; // 60 req/min
        }

        // Veteran users -> Relaxed
        return 300; // 300 req/min
    }

    public function isEnabled(): bool
    {
        return Cache::remember('system:silent_defense_mode', 60, function () {
            $value = DB::table('system_settings')->where('key', 'silent_defense_mode')->value('value');

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        });
    }
}
