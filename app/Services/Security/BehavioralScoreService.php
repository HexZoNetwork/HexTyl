<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\RiskSnapshot;
use Pterodactyl\Models\SecurityEvent;

class BehavioralScoreService
{
    /**
     * Increment risk score for a user/IP.
     * Actions: 'spam_api', 'login_fail', 'crash_spam'
     */
    public function incrementRisk(string $identifier, string $action): int
    {
        $key = "risk_score:{$identifier}";
        $score = Cache::get($key, 0);

        $points = match ($action) {
            'honeypot_hit' => 100, // Instant ban threshold
            'login_fail' => 5,
            'spam_api' => 2,
            'crash_spam' => 10,
            default => 1,
        };

        $newScore = $score + $points;
        Cache::put($key, $newScore, now()->addHours(24));

        $mode = $newScore >= 80 ? 'critical' : ($newScore >= 50 ? 'high' : ($newScore >= 20 ? 'elevated' : 'normal'));
        RiskSnapshot::query()->updateOrCreate(
            ['identifier' => $identifier],
            [
                'risk_score' => $newScore,
                'risk_mode' => $mode,
                'last_seen_at' => now(),
            ]
        );

        app(SecurityEventService::class)->log("risk:{$action}", [
            'ip' => $identifier,
            'risk_level' => $newScore >= 80 ? SecurityEvent::RISK_CRITICAL : ($newScore >= 50 ? SecurityEvent::RISK_HIGH : SecurityEvent::RISK_MEDIUM),
            'meta' => [
                'action' => $action,
                'score' => $newScore,
            ],
        ]);

        return $newScore;
    }

    /**
     * Get current risk score.
     */
    public function getScore(string $identifier): int
    {
        return Cache::get("risk_score:{$identifier}", 0);
    }

    /**
     * Check if user should be blocked/throttled.
     */
    public function getRestrictionLevel(string $identifier): string
    {
        $score = $this->getScore($identifier);

        if ($score >= 100) return 'block';
        if ($score >= 50) return 'throttle_heavy';
        if ($score >= 20) return 'throttle_light';

        return 'none';
    }
}
