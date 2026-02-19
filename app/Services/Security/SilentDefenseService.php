<?php

namespace Pterodactyl\Services\Security;

use Pterodactyl\Models\User;
use Illuminate\Http\Request;

class SilentDefenseService
{
    /**
     * Check if request should be silently handled (Shadow Ban / Throttled).
     * Returns delay in seconds. 0 means no delay.
     */
    public function checkDelay(Request $request): int
    {
        // 1. Check Risk Score
        // $risk = $this->riskService->getScore($request->ip());
        
        // Mock Risk Score
        $risk = 0; 
        
        if ($risk > 80) {
            // High risk: Silent delay (Synthetic lag)
            // Attacker thinks server is slow, but API responds eventually.
            return rand(2, 5); 
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
}
