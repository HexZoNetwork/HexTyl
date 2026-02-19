<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\RiskSnapshot;
use Pterodactyl\Models\SecurityEvent;

class ThreatIntelligenceService
{
    public function overview(): array
    {
        $windowStart = now()->subDays(7);

        $riskDistribution = [
            'normal' => RiskSnapshot::query()->where('risk_score', '<', 20)->count(),
            'elevated' => RiskSnapshot::query()->whereBetween('risk_score', [20, 49])->count(),
            'high' => RiskSnapshot::query()->whereBetween('risk_score', [50, 79])->count(),
            'critical' => RiskSnapshot::query()->where('risk_score', '>=', 80)->count(),
        ];

        $geoHeatmap = RiskSnapshot::query()
            ->select('geo_country', DB::raw('COUNT(*) as total'))
            ->groupBy('geo_country')
            ->orderByDesc('total')
            ->limit(15)
            ->get();

        $trend = SecurityEvent::query()
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $windowStart)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('day')
            ->get();

        $topIps = RiskSnapshot::query()->orderByDesc('risk_score')->limit(20)->get(['identifier', 'risk_score', 'risk_mode', 'geo_country', 'last_seen_at']);

        return [
            'risk_distribution' => $riskDistribution,
            'geo_heatmap' => $geoHeatmap,
            'risk_trend' => $trend,
            'top_risky_ips' => $topIps,
        ];
    }

    public function detectLoginAnomaly(string $ip, ?int $userId): bool
    {
        $hour = (int) now()->format('H');
        $oddHour = ($hour < 5 || $hour > 23);

        $recentSameIp = SecurityEvent::query()
            ->where('event_type', 'auth:login.success')
            ->where('ip', $ip)
            ->where('created_at', '>=', now()->subDays(14))
            ->exists();

        $newIp = !$recentSameIp;
        if ($newIp || $oddHour) {
            app(SecurityEventService::class)->log('auth:anomaly.login', [
                'actor_user_id' => $userId,
                'ip' => $ip,
                'risk_level' => SecurityEvent::RISK_MEDIUM,
                'meta' => [
                    'new_ip' => $newIp,
                    'odd_hour' => $oddHour,
                    'hour' => $hour,
                ],
            ]);
        }

        return $newIp || $oddHour;
    }
}
