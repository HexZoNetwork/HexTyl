<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Servers\ServerReputationService;

class TrustAutomationService
{
    public function __construct(
        private ServerReputationService $serverReputationService,
        private ProgressiveSecurityModeService $progressiveSecurityModeService,
        private SecurityEventService $securityEventService
    ) {
    }

    public function runCycle(?int $targetServerId = null, bool $force = false): array
    {
        $rules = $this->rules();
        $summary = [
            'success' => true,
            'enabled' => $rules['enabled'],
            'target_server_id' => $targetServerId,
            'checked' => 0,
            'recalculated' => 0,
            'elevated_applied' => 0,
            'quarantined' => 0,
            'lockdown_triggered' => 0,
            'skipped' => false,
        ];

        if (!$force && !$rules['enabled']) {
            $summary['skipped'] = true;

            return $summary;
        }

        $query = Server::query()->with('reputation');
        if ($targetServerId !== null) {
            $query->where('id', $targetServerId);
        }

        $query->orderBy('id')->chunkById(100, function ($servers) use (&$summary, $rules) {
            foreach ($servers as $server) {
                $summary['checked']++;

                $reputation = $server->reputation;
                if (!$reputation || !$reputation->last_calculated_at || $reputation->last_calculated_at->lt(now()->subMinutes(10))) {
                    $reputation = $this->serverReputationService->recalculate($server);
                    $summary['recalculated']++;
                }

                $currentTrust = (int) $reputation->trust_score;
                $snapshotKey = "trust:auto:snapshot:server:{$server->id}";
                $snapshot = Cache::get($snapshotKey);

                if ($currentTrust < $rules['quarantine_threshold']) {
                    $this->quarantineServer($server->id, $rules['quarantine_minutes'], $currentTrust);
                    $summary['quarantined']++;
                } elseif ($currentTrust < $rules['elevated_threshold']) {
                    if ($this->applyDdosProfileWithCooldown('elevated', $rules['profile_cooldown_minutes'])) {
                        $summary['elevated_applied']++;
                    }

                    $this->securityEventService->log('security:trust_automation.elevated', [
                        'server_id' => $server->id,
                        'risk_level' => 'high',
                        'meta' => [
                            'trust_score' => $currentTrust,
                            'threshold' => $rules['elevated_threshold'],
                        ],
                    ]);
                }

                if (is_array($snapshot) && isset($snapshot['score'], $snapshot['captured_at'])) {
                    $previousScore = (int) $snapshot['score'];
                    $drop = $previousScore - $currentTrust;
                    $capturedAt = (int) $snapshot['captured_at'];
                    $withinWindow = $capturedAt >= now()->subMinutes($rules['drop_window_minutes'])->getTimestamp();

                    if ($withinWindow && $drop >= $rules['drop_threshold']) {
                        if ($this->triggerLockdown($server->id, $drop, $currentTrust, $rules['lockdown_cooldown_minutes'])) {
                            $summary['lockdown_triggered']++;
                        }
                    }
                }

                Cache::put($snapshotKey, [
                    'score' => $currentTrust,
                    'captured_at' => now()->getTimestamp(),
                ], now()->addDay());
            }
        });

        return $summary;
    }

    private function quarantineServer(int $serverId, int $minutes, int $trustScore): void
    {
        Cache::put("quarantine:server:{$serverId}", true, now()->addMinutes($minutes));

        $list = collect(Cache::get('quarantine:servers:list', []))
            ->map(fn ($id) => (int) $id)
            ->push($serverId)
            ->unique()
            ->values()
            ->all();
        Cache::put('quarantine:servers:list', $list, now()->addDays(3));

        $this->securityEventService->log('security:trust_automation.quarantine', [
            'server_id' => $serverId,
            'risk_level' => 'critical',
            'meta' => [
                'trust_score' => $trustScore,
                'quarantine_minutes' => $minutes,
            ],
        ]);
    }

    private function triggerLockdown(int $serverId, int $drop, int $trustScore, int $cooldownMinutes): bool
    {
        if (!Cache::add('trust:auto:lockdown:cooldown', true, now()->addMinutes($cooldownMinutes))) {
            return false;
        }

        $this->setSetting('root_emergency_mode', 'true');
        $this->setSetting('panic_mode', 'true');
        $this->setSetting('ptla_write_disabled', 'true');
        $this->setSetting('chat_incident_mode', 'true');
        $this->setSetting('hide_server_creation', 'true');

        $this->progressiveSecurityModeService->applyMode(ProgressiveSecurityModeService::MODE_LOCKDOWN);
        $this->applyDdosProfileWithCooldown('under_attack', $cooldownMinutes, true);

        $this->securityEventService->log('security:trust_automation.lockdown', [
            'server_id' => $serverId,
            'risk_level' => 'critical',
            'meta' => [
                'trust_score' => $trustScore,
                'drop_score' => $drop,
            ],
        ]);

        return true;
    }

    private function applyDdosProfileWithCooldown(string $profile, int $cooldownMinutes, bool $force = false): bool
    {
        $cacheKey = "trust:auto:ddos_profile:{$profile}:cooldown";
        if (!$force && !Cache::add($cacheKey, true, now()->addMinutes(max(1, $cooldownMinutes)))) {
            return false;
        }

        try {
            Artisan::call('security:ddos-profile', ['profile' => $profile]);
        } catch (\Throwable $exception) {
            $this->securityEventService->log('security:trust_automation.ddos_profile_failed', [
                'risk_level' => 'high',
                'meta' => [
                    'profile' => $profile,
                    'message' => $exception->getMessage(),
                ],
            ]);

            return false;
        }

        return true;
    }

    private function setSetting(string $key, string $value): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
        );

        Cache::forget("system:{$key}");
    }

    private function rules(): array
    {
        return [
            'enabled' => filter_var($this->settingValue('trust_automation_enabled', 'true'), FILTER_VALIDATE_BOOLEAN),
            'elevated_threshold' => $this->settingInt('trust_automation_elevated_threshold', 50, 1, 100),
            'quarantine_threshold' => $this->settingInt('trust_automation_quarantine_threshold', 30, 0, 99),
            'drop_threshold' => $this->settingInt('trust_automation_drop_threshold', 20, 1, 100),
            'drop_window_minutes' => $this->settingInt('trust_automation_drop_window_minutes', 10, 1, 120),
            'quarantine_minutes' => $this->settingInt('trust_automation_quarantine_minutes', 30, 1, 1440),
            'profile_cooldown_minutes' => $this->settingInt('trust_automation_profile_cooldown_minutes', 5, 1, 120),
            'lockdown_cooldown_minutes' => $this->settingInt('trust_automation_lockdown_cooldown_minutes', 10, 1, 180),
        ];
    }

    private function settingInt(string $key, int $default, int $min, int $max): int
    {
        $value = (int) $this->settingValue($key, (string) $default);

        return max($min, min($max, $value));
    }

    private function settingValue(string $key, string $default): string
    {
        return (string) Cache::remember("system:{$key}", 30, function () use ($key, $default) {
            $value = DB::table('system_settings')->where('key', $key)->value('value');

            return ($value === null || $value === '') ? $default : (string) $value;
        });
    }
}
