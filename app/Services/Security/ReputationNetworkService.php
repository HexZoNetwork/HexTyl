<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\ReputationIndicator;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Services\Ecosystem\EventBusService;

class ReputationNetworkService
{
    public function __construct(
        private SecurityEventService $securityEventService,
        private EventBusService $eventBusService
    ) {
    }

    public function status(): array
    {
        return [
            'enabled' => $this->settingBool('reputation_network_enabled', false),
            'endpoint' => $this->settingString('reputation_network_endpoint', ''),
            'allow_pull' => $this->settingBool('reputation_network_allow_pull', true),
            'allow_push' => $this->settingBool('reputation_network_allow_push', true),
            'stored_indicators' => ReputationIndicator::query()->count(),
            'active_indicators' => ReputationIndicator::query()
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                })->count(),
        ];
    }

    public function sync(): array
    {
        if (!$this->settingBool('reputation_network_enabled', false)) {
            return ['success' => false, 'message' => 'Reputation network is disabled.'];
        }

        $endpoint = rtrim($this->settingString('reputation_network_endpoint', ''), '/');
        if ($endpoint === '') {
            return ['success' => false, 'message' => 'Reputation network endpoint is empty.'];
        }

        $token = $this->settingString('reputation_network_token', '');
        $headers = $token !== '' ? ['Authorization' => "Bearer {$token}"] : [];

        $pushed = 0;
        if ($this->settingBool('reputation_network_allow_push', true)) {
            $payload = $this->localIndicatorsPayload();
            try {
                $resp = Http::timeout(5)->withHeaders($headers)->post($endpoint . '/ingest', $payload);
                if ($resp->successful()) {
                    $pushed = count($payload['indicators']);
                }
            } catch (\Throwable) {
                // swallow and continue pull path
            }
        }

        $pulled = 0;
        if ($this->settingBool('reputation_network_allow_pull', true)) {
            try {
                $resp = Http::timeout(5)->withHeaders($headers)->get($endpoint . '/indicators');
                if ($resp->successful()) {
                    $data = $resp->json('indicators', []);
                    foreach ((array) $data as $row) {
                        if (empty($row['type']) || empty($row['value'])) {
                            continue;
                        }

                        ReputationIndicator::query()->updateOrCreate(
                            [
                                'indicator_type' => substr((string) $row['type'], 0, 40),
                                'indicator_value' => substr((string) $row['value'], 0, 191),
                                'source' => substr((string) ($row['source'] ?? 'network'), 0, 120),
                            ],
                            [
                                'confidence' => max(1, min(100, (int) ($row['confidence'] ?? 60))),
                                'risk_level' => (string) ($row['risk_level'] ?? 'medium'),
                                'meta' => (array) ($row['meta'] ?? []),
                                'last_seen_at' => now(),
                                'expires_at' => now()->addHours(24),
                            ]
                        );
                        $pulled++;
                    }
                }
            } catch (\Throwable) {
                // ignore for now
            }
        }

        $this->securityEventService->log('security:reputation_network.sync', [
            'risk_level' => 'low',
            'meta' => ['pushed' => $pushed, 'pulled' => $pulled],
        ]);
        $this->eventBusService->emit('reputation.network.sync', ['pushed' => $pushed, 'pulled' => $pulled], 'network');

        return ['success' => true, 'pushed' => $pushed, 'pulled' => $pulled];
    }

    private function localIndicatorsPayload(): array
    {
        $rows = SecurityEvent::query()
            ->whereNotNull('ip')
            ->where('created_at', '>=', now()->subDay())
            ->select('ip', 'event_type', DB::raw('COUNT(*) as total'))
            ->groupBy('ip', 'event_type')
            ->orderByDesc('total')
            ->limit(100)
            ->get();

        $indicators = [];
        foreach ($rows as $row) {
            $indicators[] = [
                'type' => 'ip',
                'value' => (string) $row->ip,
                'source' => config('app.url', 'local'),
                'confidence' => min(100, max(30, ((int) $row->total) * 5)),
                'risk_level' => ((int) $row->total) >= 15 ? 'high' : 'medium',
                'meta' => [
                    'event_type' => $row->event_type,
                    'hits_24h' => (int) $row->total,
                ],
            ];
        }

        return [
            'panel' => config('app.url', 'local'),
            'generated_at' => now()->toAtomString(),
            'indicators' => $indicators,
        ];
    }

    private function settingBool(string $key, bool $default): bool
    {
        $value = (string) (DB::table('system_settings')->where('key', $key)->value('value') ?? ($default ? 'true' : 'false'));

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function settingString(string $key, string $default): string
    {
        return (string) (DB::table('system_settings')->where('key', $key)->value('value') ?? $default);
    }
}
