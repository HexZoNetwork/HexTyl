<?php

namespace Pterodactyl\Services\Security;

use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Models\RiskSnapshot;
use Pterodactyl\Services\Ecosystem\EventBusService;

class SecurityEventService
{
    public function log(string $eventType, array $payload = []): SecurityEvent
    {
        $ip = $payload['ip'] ?? null;
        $event = SecurityEvent::query()->create([
            'actor_user_id' => $payload['actor_user_id'] ?? null,
            'server_id' => $payload['server_id'] ?? null,
            'ip' => $ip,
            'event_type' => $eventType,
            'risk_level' => $payload['risk_level'] ?? SecurityEvent::RISK_INFO,
            'meta' => $payload['meta'] ?? null,
            'created_at' => now(),
        ]);

        if ($ip) {
            $snapshot = RiskSnapshot::query()->firstOrCreate(['identifier' => $ip], [
                'risk_score' => 0,
                'risk_mode' => 'normal',
                'geo_country' => $this->geoCountry($payload),
                'last_seen_at' => now(),
            ]);
            $snapshot->forceFill([
                'last_seen_at' => now(),
                'geo_country' => $snapshot->geo_country ?: $this->geoCountry($payload),
            ])->save();
        }

        try {
            app(EventBusService::class)->emit('security.event.logged', [
                'event_type' => $eventType,
                'risk_level' => (string) ($payload['risk_level'] ?? SecurityEvent::RISK_INFO),
                'ip' => $ip,
                'meta' => $payload['meta'] ?? null,
            ], 'security_event', $payload['server_id'] ?? null, $payload['actor_user_id'] ?? null);
        } catch (\Throwable) {
            // Avoid breaking security logging path when ecosystem layer fails.
        }

        return $event;
    }

    private function geoCountry(array $payload): string
    {
        $country = (string) ($payload['geo_country'] ?? $payload['meta']['geo_country'] ?? 'UNK');

        return strtoupper(substr($country, 0, 10));
    }
}
