<?php

namespace Pterodactyl\Http\Controllers\Api\RootApplication;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerReputation;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Services\Maintenance\GlobalMaintenanceService;
use Pterodactyl\Models\NodeHealthScore;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Models\ServerHealthScore;
use Pterodactyl\Models\SecretVaultVersion;
use Pterodactyl\Services\Nodes\NodeAutoBalancerService;
use Pterodactyl\Services\Security\ThreatIntelligenceService;
use Pterodactyl\Services\Observability\RootAuditTimelineService;
use Pterodactyl\Services\Observability\ServerHealthScoringService;
use Pterodactyl\Services\Security\ProgressiveSecurityModeService;

class RootApplicationController extends Controller
{
    public function overview(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'overview' => [
                'users_total' => User::query()->count(),
                'servers_total' => Server::query()->count(),
                'servers_online' => Server::query()->whereNull('status')->count(),
                'servers_offline' => Server::query()->whereNotNull('status')->count(),
                'nodes_total' => Node::query()->count(),
                'api_keys_application' => ApiKey::query()->where('key_type', ApiKey::TYPE_APPLICATION)->count(),
                'api_keys_root' => ApiKey::query()->where('key_type', ApiKey::TYPE_ROOT)->count(),
                'modes' => [
                    'panic' => $this->boolSetting('panic_mode'),
                    'maintenance' => $this->boolSetting('maintenance_mode'),
                    'silent_defense' => $this->boolSetting('silent_defense_mode'),
                    'kill_switch' => $this->boolSetting('kill_switch_mode'),
                    'ddos_lockdown' => $this->boolSetting('ddos_lockdown_mode'),
                ],
            ],
        ]);
    }

    public function offlineServers(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $servers = Server::query()
            ->with(['user:id,username', 'node:id,name', 'allocation:id,server_id,alias,ip,port'])
            ->whereNotNull('status')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'servers' => $servers,
        ]);
    }

    public function reputations(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $minTrust = max(0, min(100, (int) $request->query('min_trust', 0)));

        $query = ServerReputation::query()
            ->with(['server:id,uuid,name,status,owner_id,node_id']);

        if ($minTrust > 0) {
            $query->where('trust_score', '>=', $minTrust);
        }

        return response()->json([
            'success' => true,
            'reputations' => $query->orderByDesc('trust_score')->paginate($perPage),
        ]);
    }

    public function quarantinedServers(): JsonResponse
    {
        $quarantineIds = Cache::get('quarantine:servers:list', []);
        $activeIds = collect($quarantineIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => Cache::has("quarantine:server:{$id}"))
            ->values()
            ->all();

        $servers = Server::query()
            ->whereIn('id', $activeIds)
            ->with(['user:id,username', 'node:id,name'])
            ->get();

        return response()->json([
            'success' => true,
            'quarantined_total' => count($activeIds),
            'servers' => $servers,
        ]);
    }

    public function securitySettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'settings' => [
                'panic_mode' => $this->boolSetting('panic_mode'),
                'maintenance_mode' => $this->boolSetting('maintenance_mode'),
                'maintenance_message' => (string) (DB::table('system_settings')->where('key', 'maintenance_message')->value('value') ?? ''),
                'silent_defense_mode' => $this->boolSetting('silent_defense_mode'),
                'kill_switch_mode' => $this->boolSetting('kill_switch_mode'),
                'kill_switch_whitelist_ips' => (string) (DB::table('system_settings')->where('key', 'kill_switch_whitelist_ips')->value('value') ?? ''),
                'progressive_security_mode' => (string) (DB::table('system_settings')->where('key', 'progressive_security_mode')->value('value') ?? 'normal'),
                'ddos_lockdown_mode' => $this->boolSetting('ddos_lockdown_mode'),
                'ddos_whitelist_ips' => (string) (DB::table('system_settings')->where('key', 'ddos_whitelist_ips')->value('value') ?? ''),
                'ddos_rate_web_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_web_per_minute')->value('value') ?? config('ddos.rate_limits.web_per_minute')),
                'ddos_rate_api_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_api_per_minute')->value('value') ?? config('ddos.rate_limits.api_per_minute')),
                'ddos_rate_login_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_login_per_minute')->value('value') ?? config('ddos.rate_limits.login_per_minute')),
                'ddos_rate_write_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_write_per_minute')->value('value') ?? config('ddos.rate_limits.write_per_minute')),
                'ddos_burst_threshold_10s' => (int) (DB::table('system_settings')->where('key', 'ddos_burst_threshold_10s')->value('value') ?? config('ddos.burst_threshold_10s')),
                'ddos_temp_block_minutes' => (int) (DB::table('system_settings')->where('key', 'ddos_temp_block_minutes')->value('value') ?? config('ddos.temporary_block_minutes')),
            ],
        ]);
    }

    public function setSecuritySetting(
        Request $request,
        GlobalMaintenanceService $maintenanceService,
        ProgressiveSecurityModeService $progressiveSecurityModeService
    ): JsonResponse
    {
        $data = $request->validate([
            'panic_mode' => 'nullable|boolean',
            'maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:255',
            'silent_defense_mode' => 'nullable|boolean',
            'kill_switch_mode' => 'nullable|boolean',
            'progressive_security_mode' => 'nullable|string|in:normal,elevated,lockdown',
            'kill_switch_whitelist_ips' => 'nullable|string|max:3000',
            'ddos_lockdown_mode' => 'nullable|boolean',
            'ddos_whitelist_ips' => 'nullable|string|max:3000',
            'ddos_rate_web_per_minute' => 'nullable|integer|min:30|max:20000',
            'ddos_rate_api_per_minute' => 'nullable|integer|min:30|max:20000',
            'ddos_rate_login_per_minute' => 'nullable|integer|min:5|max:5000',
            'ddos_rate_write_per_minute' => 'nullable|integer|min:5|max:5000',
            'ddos_burst_threshold_10s' => 'nullable|integer|min:30|max:50000',
            'ddos_temp_block_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        if (array_key_exists('maintenance_mode', $data)) {
            if ($data['maintenance_mode']) {
                $maintenanceService->enable($data['maintenance_message'] ?? 'System Maintenance');
            } else {
                $maintenanceService->disable();
            }
        }

        foreach (['panic_mode', 'silent_defense_mode', 'kill_switch_mode', 'ddos_lockdown_mode'] as $boolKey) {
            if (array_key_exists($boolKey, $data)) {
                $this->setSetting($boolKey, $data[$boolKey] ? 'true' : 'false');
            }
        }
        if (array_key_exists('kill_switch_whitelist_ips', $data)) {
            $this->setSetting('kill_switch_whitelist_ips', trim($data['kill_switch_whitelist_ips']));
        }
        if (array_key_exists('maintenance_message', $data)) {
            $this->setSetting('maintenance_message', trim($data['maintenance_message']));
        }
        if (array_key_exists('ddos_whitelist_ips', $data)) {
            $this->setSetting('ddos_whitelist_ips', trim((string) $data['ddos_whitelist_ips']));
        }
        foreach ([
            'ddos_rate_web_per_minute',
            'ddos_rate_api_per_minute',
            'ddos_rate_login_per_minute',
            'ddos_rate_write_per_minute',
            'ddos_burst_threshold_10s',
            'ddos_temp_block_minutes',
        ] as $intKey) {
            if (array_key_exists($intKey, $data)) {
                $this->setSetting($intKey, (string) (int) $data[$intKey]);
            }
        }
        if (!empty($data['progressive_security_mode'])) {
            $progressiveSecurityModeService->applyMode($data['progressive_security_mode']);
        }

        foreach ([
            'system:panic_mode',
            'system:maintenance_mode',
            'system:maintenance_message',
            'system:silent_defense_mode',
            'system:kill_switch_mode',
            'system:kill_switch_whitelist',
            'system:ddos_lockdown_mode',
            'system:ddos_whitelist_ips',
            'system:ddos_rate_web_per_minute',
            'system:ddos_rate_api_per_minute',
            'system:ddos_rate_login_per_minute',
            'system:ddos_rate_write_per_minute',
            'system:ddos_burst_threshold_10s',
            'system:ddos_temp_block_minutes',
        ] as $cacheKey) {
            Cache::forget($cacheKey);
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('api:rootapplication.security.settings', [
            'actor_user_id' => optional($request->user())->id,
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'meta' => $data,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Security setting updated.',
        ]);
    }

    public function threatIntel(ThreatIntelligenceService $threatIntelligenceService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'intel' => $threatIntelligenceService->overview(),
        ]);
    }

    public function auditTimeline(Request $request, RootAuditTimelineService $timelineService): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $events = $timelineService->query($request->only(['user_id', 'server_id', 'risk_level', 'event_type']))
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'events' => $events,
        ]);
    }

    public function healthScores(Request $request, ServerHealthScoringService $serverHealthScoringService): JsonResponse
    {
        if ($request->boolean('recalculate')) {
            $serverHealthScoringService->recalculateAll();
        }

        return response()->json([
            'success' => true,
            'servers' => ServerHealthScore::query()->with('server:id,name,uuid,status')->orderBy('stability_index')->paginate(50),
        ]);
    }

    public function nodeBalancer(Request $request, NodeAutoBalancerService $nodeAutoBalancerService): JsonResponse
    {
        if ($request->boolean('recalculate')) {
            $nodeAutoBalancerService->recalculateAll();
        }

        return response()->json([
            'success' => true,
            'nodes' => NodeHealthScore::query()->with('node:id,name,fqdn')->orderBy('health_score')->paginate(50),
        ]);
    }

    public function securityMode(ProgressiveSecurityModeService $progressiveSecurityModeService): JsonResponse
    {
        $mode = $progressiveSecurityModeService->evaluateSystemMode();

        return response()->json([
            'success' => true,
            'mode' => $mode,
            'events_24h' => SecurityEvent::query()->where('created_at', '>=', now()->subDay())->count(),
        ]);
    }

    public function secretVaultStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'vault' => [
                'total_versions' => SecretVaultVersion::query()->count(),
                'expiring_7d' => SecretVaultVersion::query()
                    ->whereNotNull('expires_at')
                    ->whereBetween('expires_at', [now(), now()->addDays(7)])
                    ->count(),
                'rotation_due' => SecretVaultVersion::query()
                    ->whereNotNull('rotates_at')
                    ->where('rotates_at', '<=', now())
                    ->count(),
                'recent_access' => SecretVaultVersion::query()
                    ->whereNotNull('last_accessed_at')
                    ->orderByDesc('last_accessed_at')
                    ->limit(20)
                    ->get(['id', 'server_id', 'secret_key', 'version', 'access_count', 'last_accessed_at']),
            ],
        ]);
    }

    private function boolSetting(string $key): bool
    {
        $value = DB::table('system_settings')->where('key', $key)->value('value');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function setSetting(string $key, string $value): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
