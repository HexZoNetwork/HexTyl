<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\Repositories\Wings\DaemonPowerRepository;
use Pterodactyl\Repositories\Wings\DaemonServerRepository;
use Pterodactyl\Services\Servers\ServerDeletionService;
use Pterodactyl\Services\Servers\SuspensionService;
use Pterodactyl\Services\Users\UserDeletionService;

class ResourceSafetyOrchestratorService
{
    public function __construct(
        private DaemonServerRepository $daemonServerRepository,
        private DaemonPowerRepository $daemonPowerRepository,
        private SuspensionService $suspensionService,
        private ServerDeletionService $serverDeletionService,
        private UserDeletionService $userDeletionService,
        private PermanentIpBlockService $permanentIpBlockService,
        private SecurityEventService $securityEventService
    ) {
    }

    public function runCycle(?int $targetServerId = null, bool $force = false): array
    {
        $rules = $this->rules();
        $summary = [
            'enabled' => $rules['enabled'],
            'checked' => 0,
            'violations' => 0,
            'wings_incidents' => 0,
            'enforced' => 0,
            'stopped' => 0,
            'suspended' => 0,
            'deleted_servers' => 0,
            'deleted_users' => 0,
            'permanent_ip_bans' => 0,
            'errors' => 0,
            'skipped' => false,
        ];

        if (!$force && !$rules['enabled']) {
            $summary['skipped'] = true;

            return $summary;
        }

        $query = Server::query()->with('user');
        if ($targetServerId !== null) {
            $query->where('id', $targetServerId);
        }

        $query->orderBy('id')->chunkById(50, function ($servers) use (&$summary, $rules) {
            foreach ($servers as $server) {
                $summary['checked']++;
                if ($server->isSuspended()) {
                    continue;
                }

                try {
                    $details = $this->daemonServerRepository->setServer($server)->getDetails();
                } catch (\Throwable $exception) {
                    $summary['errors']++;
                    $this->securityEventService->log('security:resource_safety.stats_fetch_failed', [
                        'server_id' => $server->id,
                        'risk_level' => 'high',
                        'meta' => ['message' => $exception->getMessage()],
                    ]);
                    continue;
                }

                $util = (array) data_get($details, 'utilization', []);
                $cpu = (float) data_get($util, 'cpu_absolute', 0);
                $memoryBytes = (int) data_get($util, 'memory_bytes', 0);
                $diskBytes = (int) data_get($util, 'disk_bytes', 0);
                $lastDiskKey = "security:resource_safety:last_disk_bytes:server:{$server->id}";
                $previousDiskBytes = (int) Cache::get($lastDiskKey, 0);
                Cache::put($lastDiskKey, $diskBytes, now()->addDay());
                $diskJumpBytes = max(0, $diskBytes - $previousDiskBytes);
                $diskJumpGb = round($diskJumpBytes / (1024 * 1024 * 1024), 2);
                $memoryPct = $this->percent($memoryBytes, max(0, (int) $server->memory) * 1024 * 1024);
                $diskPct = $this->percent($diskBytes, max(0, (int) $server->disk) * 1024 * 1024);

                $reasons = [];
                if ($cpu >= $rules['cpu_percent_threshold']) {
                    $reasons[] = 'cpu_spike';
                }

                $cpuSuperNow = $cpu >= $rules['cpu_super_cores_threshold_percent']
                    || $cpu >= $rules['cpu_super_all_cores_threshold_percent'];
                $cpuSuperCyclesKey = "security:resource_safety:cpu_super_cycles:server:{$server->id}";
                $cpuSuperSinceKey = "security:resource_safety:cpu_super_since:server:{$server->id}";
                $cpuSuperCycles = 0;
                $cpuSuperElapsedSeconds = 0;
                if ($cpuSuperNow) {
                    Cache::add($cpuSuperCyclesKey, 0, now()->addMinutes(30));
                    $cpuSuperCycles = (int) Cache::increment($cpuSuperCyclesKey);
                    Cache::put($cpuSuperCyclesKey, $cpuSuperCycles, now()->addMinutes(30));

                    $nowTs = time();
                    $firstSeen = (int) Cache::get($cpuSuperSinceKey, 0);
                    if ($firstSeen <= 0) {
                        $firstSeen = $nowTs;
                        Cache::put($cpuSuperSinceKey, $firstSeen, now()->addMinutes(30));
                    }
                    $cpuSuperElapsedSeconds = max(0, $nowTs - $firstSeen);
                } else {
                    Cache::forget($cpuSuperCyclesKey);
                    Cache::forget($cpuSuperSinceKey);
                }
                $cpuSuperSustainedTriggered = $cpuSuperCycles >= $rules['cpu_super_consecutive_cycles_threshold']
                    || $cpuSuperElapsedSeconds >= $rules['cpu_super_sustained_seconds'];
                if ($cpuSuperSustainedTriggered) {
                    $reasons[] = 'cpu_super_sustained_spike';
                }
                if ($memoryPct >= $rules['memory_percent_threshold']) {
                    $reasons[] = 'memory_spike';
                }
                if ($diskPct >= $rules['disk_percent_threshold']) {
                    $reasons[] = 'disk_spike';
                }
                $jumpThresholdBytes = (int) round($rules['storage_jump_gb_threshold'] * 1024 * 1024 * 1024);
                $jumpMultiplierTriggered = $previousDiskBytes > 0
                    && ((float) $diskBytes / (float) $previousDiskBytes) >= $rules['storage_jump_multiplier_threshold'];
                if ($diskJumpBytes >= $jumpThresholdBytes || $jumpMultiplierTriggered) {
                    $reasons[] = 'disk_jump_spike';
                }

                if ($reasons === []) {
                    // Fast-path sync from Wings-side guard via activity feed.
                    $hasWingsCpuSuperIncident = $this->hasRecentWingsCpuSuperIncident($server);
                    if (!$hasWingsCpuSuperIncident) {
                        continue;
                    }

                    $reasons[] = 'cpu_super_sustained_spike';
                    $summary['wings_incidents']++;
                }

                $summary['violations']++;
                $cacheKey = "security:resource_safety:violations:server:{$server->id}";
                Cache::add($cacheKey, 0, now()->addSeconds($rules['violation_window_seconds']));
                $violations = (int) Cache::increment($cacheKey);
                Cache::put($cacheKey, $violations, now()->addSeconds($rules['violation_window_seconds']));

                $this->securityEventService->log('security:resource_safety.violation', [
                    'server_id' => $server->id,
                    'risk_level' => 'high',
                    'meta' => [
                        'reasons' => $reasons,
                        'cpu_percent' => $cpu,
                        'memory_percent' => $memoryPct,
                        'disk_percent' => $diskPct,
                        'disk_jump_gb' => $diskJumpGb,
                        'violation_count' => $violations,
                        'threshold' => $rules['violation_threshold'],
                        'cpu_super_cycles' => $cpuSuperCycles,
                        'cpu_super_cycles_threshold' => $rules['cpu_super_consecutive_cycles_threshold'],
                        'cpu_super_elapsed_seconds' => $cpuSuperElapsedSeconds,
                        'cpu_super_sustained_seconds' => $rules['cpu_super_sustained_seconds'],
                    ],
                ]);

                if ($violations < $rules['violation_threshold']) {
                    continue;
                }

                $summary['enforced']++;
                $storageTriggered = in_array('disk_spike', $reasons, true) || in_array('disk_jump_spike', $reasons, true);
                $cpuSuperTriggered = in_array('cpu_super_sustained_spike', $reasons, true);
                $allowPermanentActions = !$rules['permanent_actions_only_on_storage_spike']
                    || $storageTriggered
                    || ($cpuSuperTriggered && $rules['cpu_super_force_permanent_actions']);
                $this->enforceForServer($server, $reasons, $rules, $summary, $allowPermanentActions, $cpuSuperTriggered);
            }
        });

        return $summary;
    }

    private function enforceForServer(Server $server, array $reasons, array $rules, array &$summary, bool $allowPermanentActions, bool $cpuSuperTriggered): void
    {
        $enforcementMeta = [
            'reasons' => $reasons,
            'quarantine_minutes' => $rules['quarantine_minutes'],
            'permanent_actions_allowed' => $allowPermanentActions,
            'cpu_super_triggered' => $cpuSuperTriggered,
        ];

        Cache::put("quarantine:server:{$server->id}", true, now()->addMinutes($rules['quarantine_minutes']));
        $list = collect(Cache::get('quarantine:servers:list', []))
            ->map(fn ($id) => (int) $id)
            ->push($server->id)
            ->unique()
            ->values()
            ->all();
        Cache::put('quarantine:servers:list', $list, now()->addDays(2));

        try {
            $this->daemonPowerRepository->setServer($server)->send('stop');
            $summary['stopped']++;
        } catch (\Throwable) {
            try {
                $this->daemonPowerRepository->setServer($server)->send('kill');
                $summary['stopped']++;
                $enforcementMeta['forced_kill'] = true;
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $enforcementMeta['stop_error'] = $exception->getMessage();
            }
        }

        if ($rules['suspend_on_trigger']) {
            try {
                $this->suspensionService->toggle($server, SuspensionService::ACTION_SUSPEND);
                $summary['suspended']++;
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $enforcementMeta['suspend_error'] = $exception->getMessage();
            }
        }

        if ($rules['apply_ddos_under_attack_profile']) {
            try {
                Artisan::call('security:ddos-profile', ['profile' => 'under_attack']);
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $enforcementMeta['ddos_profile_error'] = $exception->getMessage();
            }
        }

        if ($allowPermanentActions && $rules['ban_last_activity_ip_permanently']) {
            $ip = (string) ($server->activity()
                ->whereNotIn('ip', ['127.0.0.1', '::1', ''])
                ->orderByDesc('timestamp')
                ->value('ip') ?? '');

            if ($ip !== '' && $this->permanentIpBlockService->blockForever($ip, [
                'source' => 'resource_safety',
                'server_id' => $server->id,
            ])) {
                $summary['permanent_ip_bans']++;
            }
        }

        $shouldDeleteServer = $allowPermanentActions && ($rules['delete_server_on_trigger'] || ($cpuSuperTriggered && $rules['cpu_super_force_delete_server']));
        $shouldDeleteOwner = $allowPermanentActions && ($rules['delete_user_after_server_deletion'] || ($cpuSuperTriggered && $rules['cpu_super_force_delete_owner']));

        if ($shouldDeleteServer) {
            try {
                $ownerId = (int) $server->owner_id;
                $this->serverDeletionService->withForce(true)->handle($server);
                $summary['deleted_servers']++;

                if ($shouldDeleteOwner) {
                    $this->forceDeleteOwnerAndServers($ownerId, $summary, $enforcementMeta);
                }
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $enforcementMeta['delete_server_error'] = $exception->getMessage();
            }
        }

        $this->securityEventService->log('security:resource_safety.enforced', [
            'server_id' => $server->id,
            'risk_level' => 'critical',
            'meta' => $enforcementMeta,
        ]);

        // Reset violation counter after enforcement to avoid immediate repeat loops.
        Cache::forget("security:resource_safety:violations:server:{$server->id}");
    }

    private function rules(): array
    {
        return [
            'enabled' => $this->boolSetting('resource_safety_enabled', config('resource_safety.enabled', true)),
            'violation_window_seconds' => $this->intSetting('resource_safety_violation_window_seconds', (int) config('resource_safety.violation_window_seconds', 300), 10, 3600),
            'violation_threshold' => $this->intSetting('resource_safety_violation_threshold', (int) config('resource_safety.violation_threshold', 3), 1, 20),
            'cpu_percent_threshold' => $this->floatSetting('resource_safety_cpu_percent_threshold', (float) config('resource_safety.cpu_percent_threshold', 95), 1, 1000),
            'cpu_super_cores_threshold_percent' => $this->floatSetting('resource_safety_cpu_super_cores_threshold_percent', (float) config('resource_safety.cpu_super_cores_threshold_percent', 500), 100, 6400),
            'cpu_super_all_cores_threshold_percent' => $this->floatSetting('resource_safety_cpu_super_all_cores_threshold_percent', (float) config('resource_safety.cpu_super_all_cores_threshold_percent', 900), 100, 12800),
            'cpu_super_consecutive_cycles_threshold' => $this->intSetting('resource_safety_cpu_super_consecutive_cycles_threshold', (int) config('resource_safety.cpu_super_consecutive_cycles_threshold', 5), 1, 120),
            'cpu_super_sustained_seconds' => $this->intSetting('resource_safety_cpu_super_sustained_seconds', (int) config('resource_safety.cpu_super_sustained_seconds', 10), 10, 3600),
            'memory_percent_threshold' => $this->floatSetting('resource_safety_memory_percent_threshold', (float) config('resource_safety.memory_percent_threshold', 95), 1, 1000),
            'disk_percent_threshold' => $this->floatSetting('resource_safety_disk_percent_threshold', (float) config('resource_safety.disk_percent_threshold', 98), 1, 1000),
            'quarantine_minutes' => $this->intSetting('resource_safety_quarantine_minutes', (int) config('resource_safety.quarantine_minutes', 60), 1, 10080),
            'suspend_on_trigger' => $this->boolSetting('resource_safety_suspend_on_trigger', (bool) config('resource_safety.suspend_on_trigger', true)),
            'apply_ddos_under_attack_profile' => $this->boolSetting('resource_safety_apply_ddos_profile', (bool) config('resource_safety.apply_ddos_under_attack_profile', true)),
            'storage_jump_gb_threshold' => $this->floatSetting('resource_safety_storage_jump_gb_threshold', (float) config('resource_safety.storage_jump_gb_threshold', 20), 1, 2048),
            'storage_jump_multiplier_threshold' => $this->floatSetting('resource_safety_storage_jump_multiplier_threshold', (float) config('resource_safety.storage_jump_multiplier_threshold', 3), 1.1, 100),
            'permanent_actions_only_on_storage_spike' => $this->boolSetting('resource_safety_permanent_only_storage_spike', (bool) config('resource_safety.permanent_actions_only_on_storage_spike', true)),
            'cpu_super_force_permanent_actions' => $this->boolSetting('resource_safety_cpu_super_force_permanent_actions', (bool) config('resource_safety.cpu_super_force_permanent_actions', true)),
            'cpu_super_force_delete_server' => $this->boolSetting('resource_safety_cpu_super_force_delete_server', (bool) config('resource_safety.cpu_super_force_delete_server', true)),
            'cpu_super_force_delete_owner' => $this->boolSetting('resource_safety_cpu_super_force_delete_owner', (bool) config('resource_safety.cpu_super_force_delete_owner', true)),
            'delete_server_on_trigger' => $this->boolSetting('resource_safety_delete_server_on_trigger', (bool) config('resource_safety.delete_server_on_trigger', true)),
            'delete_user_after_server_deletion' => $this->boolSetting('resource_safety_delete_user_after_server_deletion', (bool) config('resource_safety.delete_user_after_server_deletion', true)),
            'ban_last_activity_ip_permanently' => $this->boolSetting('resource_safety_ban_last_ip_permanently', (bool) config('resource_safety.ban_last_activity_ip_permanently', true)),
        ];
    }

    private function forceDeleteOwnerAndServers(int $ownerId, array &$summary, array &$enforcementMeta): void
    {
        $remainingServers = Server::query()
            ->where('owner_id', $ownerId)
            ->get();

        foreach ($remainingServers as $remainingServer) {
            try {
                $this->serverDeletionService->withForce(true)->handle($remainingServer);
                $summary['deleted_servers']++;
            } catch (\Throwable $exception) {
                $summary['errors']++;
                $enforcementMeta['delete_owner_remaining_server_error'] = $exception->getMessage();
            }
        }

        try {
            $this->userDeletionService->handle($ownerId);
            $summary['deleted_users']++;
        } catch (\Throwable $exception) {
            $summary['errors']++;
            $enforcementMeta['delete_user_error'] = $exception->getMessage();
        }
    }

    private function hasRecentWingsCpuSuperIncident(Server $server): bool
    {
        $last = $server->activity()
            ->where('event', 'server:security.cpu-super-sustained')
            ->where('timestamp', '>=', now()->subMinutes(15))
            ->latest('timestamp')
            ->first();

        if ($last === null) {
            return false;
        }

        $dedupeKey = "security:resource_safety:wings_cpu_super_seen:server:{$server->id}:activity:{$last->id}";

        return Cache::add($dedupeKey, true, now()->addMinutes(30));
    }

    private function percent(int|float $value, int|float $limit): float
    {
        if ($limit <= 0) {
            return 0.0;
        }

        return round(($value / $limit) * 100, 2);
    }

    private function boolSetting(string $key, bool $default): bool
    {
        return filter_var($this->settingValue($key, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    private function intSetting(string $key, int $default, int $min, int $max): int
    {
        $value = (int) $this->settingValue($key, (string) $default);

        return max($min, min($max, $value));
    }

    private function floatSetting(string $key, float $default, float $min, float $max): float
    {
        $value = (float) $this->settingValue($key, (string) $default);

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
