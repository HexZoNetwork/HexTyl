<?php

namespace Pterodactyl\Http\Controllers\Api\RootApplication;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerReputation;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\EventBusEvent;
use Pterodactyl\Models\WebhookSubscription;
use Pterodactyl\Services\Maintenance\GlobalMaintenanceService;
use Pterodactyl\Models\NodeHealthScore;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Models\ServerHealthScore;
use Pterodactyl\Models\SecretVaultVersion;
use Pterodactyl\Services\Nodes\NodeAutoBalancerService;
use Pterodactyl\Services\Security\ThreatIntelligenceService;
use Pterodactyl\Services\Observability\RootAuditTimelineService;
use Pterodactyl\Services\Observability\ServerHealthScoringService;
use Pterodactyl\Services\Ide\IdeSessionService;
use Pterodactyl\Services\Ecosystem\EventBusService;
use Pterodactyl\Services\Security\AdaptiveInfrastructureService;
use Pterodactyl\Services\Security\NodeContainerPolicyService;
use Pterodactyl\Services\Security\NodeSecureModeService;
use Pterodactyl\Services\Security\ProgressiveSecurityModeService;
use Pterodactyl\Services\Security\ReputationNetworkService;
use Pterodactyl\Services\Security\SecuritySimulationService;
use Pterodactyl\Services\Security\TrustAutomationService;
use Pterodactyl\Services\Security\OutboundTargetGuardService;

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
            ->with(['user:id,username', 'node:id,name', 'allocation:id,server_id,ip_alias,ip,port'])
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
                'root_emergency_mode' => $this->boolSetting('root_emergency_mode'),
                'ptla_write_disabled' => $this->boolSetting('ptla_write_disabled'),
                'chat_incident_mode' => $this->boolSetting('chat_incident_mode'),
                'hide_server_creation' => $this->boolSetting('hide_server_creation'),
                'ddos_lockdown_mode' => $this->boolSetting('ddos_lockdown_mode'),
                'ddos_whitelist_ips' => (string) (DB::table('system_settings')->where('key', 'ddos_whitelist_ips')->value('value') ?? ''),
                'ddos_rate_web_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_web_per_minute')->value('value') ?? config('ddos.rate_limits.web_per_minute')),
                'ddos_rate_api_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_api_per_minute')->value('value') ?? config('ddos.rate_limits.api_per_minute')),
                'ddos_rate_login_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_login_per_minute')->value('value') ?? config('ddos.rate_limits.login_per_minute')),
                'ddos_rate_write_per_minute' => (int) (DB::table('system_settings')->where('key', 'ddos_rate_write_per_minute')->value('value') ?? config('ddos.rate_limits.write_per_minute')),
                'ddos_burst_threshold_10s' => (int) (DB::table('system_settings')->where('key', 'ddos_burst_threshold_10s')->value('value') ?? config('ddos.burst_threshold_10s')),
                'ddos_temp_block_minutes' => (int) (DB::table('system_settings')->where('key', 'ddos_temp_block_minutes')->value('value') ?? config('ddos.temporary_block_minutes')),
                'trust_automation_enabled' => $this->boolSetting('trust_automation_enabled', true),
                'trust_automation_elevated_threshold' => $this->intSetting('trust_automation_elevated_threshold', 50),
                'trust_automation_quarantine_threshold' => $this->intSetting('trust_automation_quarantine_threshold', 30),
                'trust_automation_drop_threshold' => $this->intSetting('trust_automation_drop_threshold', 20),
                'trust_automation_drop_window_minutes' => $this->intSetting('trust_automation_drop_window_minutes', 10),
                'trust_automation_quarantine_minutes' => $this->intSetting('trust_automation_quarantine_minutes', 30),
                'trust_automation_profile_cooldown_minutes' => $this->intSetting('trust_automation_profile_cooldown_minutes', 5),
                'trust_automation_lockdown_cooldown_minutes' => $this->intSetting('trust_automation_lockdown_cooldown_minutes', 10),
                'ide_connect_enabled' => $this->boolSetting('ide_connect_enabled', false),
                'ide_block_during_emergency' => $this->boolSetting('ide_block_during_emergency', true),
                'ide_session_ttl_minutes' => $this->intSetting('ide_session_ttl_minutes', 10),
                'ide_connect_url_template' => (string) (DB::table('system_settings')->where('key', 'ide_connect_url_template')->value('value') ?? ''),
                'adaptive_alpha' => (float) ((string) (DB::table('system_settings')->where('key', 'adaptive_alpha')->value('value') ?? '0.2')),
                'adaptive_z_threshold' => (float) ((string) (DB::table('system_settings')->where('key', 'adaptive_z_threshold')->value('value') ?? '2.5')),
                'reputation_network_enabled' => $this->boolSetting('reputation_network_enabled', false),
                'reputation_network_allow_pull' => $this->boolSetting('reputation_network_allow_pull', true),
                'reputation_network_allow_push' => $this->boolSetting('reputation_network_allow_push', true),
                'reputation_network_endpoint' => (string) (DB::table('system_settings')->where('key', 'reputation_network_endpoint')->value('value') ?? ''),
                'node_secure_mode_enabled' => $this->boolSetting('node_secure_mode_enabled', false),
                'node_secure_discord_quarantine_enabled' => $this->boolSetting('node_secure_discord_quarantine_enabled', true),
                'node_secure_discord_quarantine_minutes' => $this->intSetting('node_secure_discord_quarantine_minutes', 30),
                'node_secure_npm_block_high' => $this->boolSetting('node_secure_npm_block_high', true),
                'node_secure_per_app_rate_per_minute' => $this->intSetting('node_secure_per_app_rate_per_minute', 240),
                'node_secure_per_app_write_rate_per_minute' => $this->intSetting('node_secure_per_app_write_rate_per_minute', 90),
                'node_secure_scan_max_files' => $this->intSetting('node_secure_scan_max_files', 180),
                'node_secure_chat_block_secret' => $this->boolSetting('node_secure_chat_block_secret', true),
                'node_secure_deploy_gate_enabled' => $this->boolSetting('node_secure_deploy_gate_enabled', true),
                'node_secure_deploy_block_critical_patterns' => $this->boolSetting('node_secure_deploy_block_critical_patterns', false),
                'node_secure_container_policy_enabled' => $this->boolSetting('node_secure_container_policy_enabled', false),
                'node_secure_container_block_deprecated' => $this->boolSetting('node_secure_container_block_deprecated', true),
                'node_secure_container_allow_non_node' => $this->boolSetting('node_secure_container_allow_non_node', true),
                'node_secure_container_min_major' => $this->intSetting('node_secure_container_min_major', 18),
                'node_secure_container_preferred_major' => $this->intSetting('node_secure_container_preferred_major', 22),
                'api_rate_limit_ptla_period_minutes' => $this->intSetting('api_rate_limit_ptla_period_minutes', (int) config('http.rate_limit.application_period', 1)),
                'api_rate_limit_ptla_per_period' => $this->intSetting('api_rate_limit_ptla_per_period', (int) config('http.rate_limit.application', 256)),
                'api_rate_limit_ptlc_period_minutes' => $this->intSetting('api_rate_limit_ptlc_period_minutes', (int) config('http.rate_limit.client_period', 1)),
                'api_rate_limit_ptlc_per_period' => $this->intSetting('api_rate_limit_ptlc_per_period', (int) config('http.rate_limit.client', 256)),
            ],
        ]);
    }

    public function setSecuritySetting(
        Request $request,
        GlobalMaintenanceService $maintenanceService,
        ProgressiveSecurityModeService $progressiveSecurityModeService,
        OutboundTargetGuardService $outboundTargetGuardService
    ): JsonResponse
    {
        $data = $request->validate([
            'panic_mode' => 'nullable|boolean',
            'maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:255',
            'silent_defense_mode' => 'nullable|boolean',
            'kill_switch_mode' => 'nullable|boolean',
            'root_emergency_mode' => 'nullable|boolean',
            'ptla_write_disabled' => 'nullable|boolean',
            'chat_incident_mode' => 'nullable|boolean',
            'hide_server_creation' => 'nullable|boolean',
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
            'trust_automation_enabled' => 'nullable|boolean',
            'trust_automation_elevated_threshold' => 'nullable|integer|min:1|max:100',
            'trust_automation_quarantine_threshold' => 'nullable|integer|min:0|max:99',
            'trust_automation_drop_threshold' => 'nullable|integer|min:1|max:100',
            'trust_automation_drop_window_minutes' => 'nullable|integer|min:1|max:120',
            'trust_automation_quarantine_minutes' => 'nullable|integer|min:1|max:1440',
            'trust_automation_profile_cooldown_minutes' => 'nullable|integer|min:1|max:120',
            'trust_automation_lockdown_cooldown_minutes' => 'nullable|integer|min:1|max:180',
            'ide_connect_enabled' => 'nullable|boolean',
            'ide_block_during_emergency' => 'nullable|boolean',
            'ide_session_ttl_minutes' => 'nullable|integer|min:1|max:120',
            'ide_connect_url_template' => 'nullable|string|max:1024',
            'adaptive_alpha' => 'nullable|numeric|min:0.05|max:0.8',
            'adaptive_z_threshold' => 'nullable|numeric|min:1.2|max:8',
            'reputation_network_enabled' => 'nullable|boolean',
            'reputation_network_allow_pull' => 'nullable|boolean',
            'reputation_network_allow_push' => 'nullable|boolean',
            'reputation_network_endpoint' => 'nullable|string|max:1024',
            'reputation_network_token' => 'nullable|string|max:255',
            'node_secure_mode_enabled' => 'nullable|boolean',
            'node_secure_discord_quarantine_enabled' => 'nullable|boolean',
            'node_secure_discord_quarantine_minutes' => 'nullable|integer|min:5|max:1440',
            'node_secure_npm_block_high' => 'nullable|boolean',
            'node_secure_per_app_rate_per_minute' => 'nullable|integer|min:30|max:3000',
            'node_secure_per_app_write_rate_per_minute' => 'nullable|integer|min:10|max:1500',
            'node_secure_scan_max_files' => 'nullable|integer|min:20|max:500',
            'node_secure_chat_block_secret' => 'nullable|boolean',
            'node_secure_deploy_gate_enabled' => 'nullable|boolean',
            'node_secure_deploy_block_critical_patterns' => 'nullable|boolean',
            'node_secure_container_policy_enabled' => 'nullable|boolean',
            'node_secure_container_block_deprecated' => 'nullable|boolean',
            'node_secure_container_allow_non_node' => 'nullable|boolean',
            'node_secure_container_min_major' => 'nullable|integer|min:12|max:30',
            'node_secure_container_preferred_major' => 'nullable|integer|min:12|max:30',
            'api_rate_limit_ptla_period_minutes' => 'nullable|integer|min:1|max:60',
            'api_rate_limit_ptla_per_period' => 'nullable|integer|min:10|max:200000',
            'api_rate_limit_ptlc_period_minutes' => 'nullable|integer|min:1|max:60',
            'api_rate_limit_ptlc_per_period' => 'nullable|integer|min:10|max:200000',
        ]);

        if (array_key_exists('maintenance_mode', $data)) {
            if ($data['maintenance_mode']) {
                $maintenanceService->enable($data['maintenance_message'] ?? 'System Maintenance');
            } else {
                $maintenanceService->disable();
            }
        }

        foreach ([
            'panic_mode',
            'silent_defense_mode',
            'kill_switch_mode',
            'ddos_lockdown_mode',
            'root_emergency_mode',
            'ptla_write_disabled',
            'chat_incident_mode',
            'hide_server_creation',
            'trust_automation_enabled',
            'ide_connect_enabled',
            'ide_block_during_emergency',
            'reputation_network_enabled',
            'reputation_network_allow_pull',
            'reputation_network_allow_push',
            'node_secure_mode_enabled',
            'node_secure_discord_quarantine_enabled',
            'node_secure_npm_block_high',
            'node_secure_chat_block_secret',
            'node_secure_deploy_gate_enabled',
            'node_secure_deploy_block_critical_patterns',
            'node_secure_container_policy_enabled',
            'node_secure_container_block_deprecated',
            'node_secure_container_allow_non_node',
        ] as $boolKey) {
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
            'trust_automation_elevated_threshold',
            'trust_automation_quarantine_threshold',
            'trust_automation_drop_threshold',
            'trust_automation_drop_window_minutes',
            'trust_automation_quarantine_minutes',
            'trust_automation_profile_cooldown_minutes',
            'trust_automation_lockdown_cooldown_minutes',
            'ide_session_ttl_minutes',
            'node_secure_discord_quarantine_minutes',
            'node_secure_per_app_rate_per_minute',
            'node_secure_per_app_write_rate_per_minute',
            'node_secure_scan_max_files',
            'node_secure_container_min_major',
            'node_secure_container_preferred_major',
            'api_rate_limit_ptla_period_minutes',
            'api_rate_limit_ptla_per_period',
            'api_rate_limit_ptlc_period_minutes',
            'api_rate_limit_ptlc_per_period',
        ] as $intKey) {
            if (array_key_exists($intKey, $data)) {
                $this->setSetting($intKey, (string) (int) $data[$intKey]);
            }
        }
        if (array_key_exists('ide_connect_url_template', $data)) {
            $template = trim((string) $data['ide_connect_url_template']);
            if ($template !== '') {
                $check = $outboundTargetGuardService->inspect($template);
                if (($check['ok'] ?? false) !== true) {
                    throw ValidationException::withMessages([
                        'ide_connect_url_template' => [(string) ($check['reason'] ?? 'Invalid outbound target URL.')],
                    ]);
                }
            }
            $this->setSetting('ide_connect_url_template', $template);
        }
        if (array_key_exists('reputation_network_endpoint', $data)) {
            $endpoint = trim((string) $data['reputation_network_endpoint']);
            if ($endpoint !== '') {
                $check = $outboundTargetGuardService->inspect($endpoint);
                if (($check['ok'] ?? false) !== true) {
                    throw ValidationException::withMessages([
                        'reputation_network_endpoint' => [(string) ($check['reason'] ?? 'Invalid outbound target URL.')],
                    ]);
                }
            }
            $this->setSetting('reputation_network_endpoint', $endpoint);
        }
        if (array_key_exists('reputation_network_token', $data) && trim((string) $data['reputation_network_token']) !== '') {
            $this->setSetting('reputation_network_token', trim((string) $data['reputation_network_token']));
        }
        if (array_key_exists('adaptive_alpha', $data)) {
            $this->setSetting('adaptive_alpha', (string) ((float) $data['adaptive_alpha']));
        }
        if (array_key_exists('adaptive_z_threshold', $data)) {
            $this->setSetting('adaptive_z_threshold', (string) ((float) $data['adaptive_z_threshold']));
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
            'system:root_emergency_mode',
            'system:ptla_write_disabled',
            'system:chat_incident_mode',
            'system:hide_server_creation',
            'system:ddos_lockdown_mode',
            'system:ddos_whitelist_ips',
            'system:ddos_rate_web_per_minute',
            'system:ddos_rate_api_per_minute',
            'system:ddos_rate_login_per_minute',
            'system:ddos_rate_write_per_minute',
            'system:ddos_burst_threshold_10s',
            'system:ddos_temp_block_minutes',
            'system:trust_automation_enabled',
            'system:trust_automation_elevated_threshold',
            'system:trust_automation_quarantine_threshold',
            'system:trust_automation_drop_threshold',
            'system:trust_automation_drop_window_minutes',
            'system:trust_automation_quarantine_minutes',
            'system:trust_automation_profile_cooldown_minutes',
            'system:trust_automation_lockdown_cooldown_minutes',
            'system:ide_connect_enabled',
            'system:ide_block_during_emergency',
            'system:ide_session_ttl_minutes',
            'system:ide_connect_url_template',
            'system:adaptive_alpha',
            'system:adaptive_z_threshold',
            'system:reputation_network_enabled',
            'system:reputation_network_allow_pull',
            'system:reputation_network_allow_push',
            'system:reputation_network_endpoint',
            'system:reputation_network_token',
            'system:node_secure_mode_enabled',
            'system:node_secure_discord_quarantine_enabled',
            'system:node_secure_discord_quarantine_minutes',
            'system:node_secure_npm_block_high',
            'system:node_secure_per_app_rate_per_minute',
            'system:node_secure_per_app_write_rate_per_minute',
            'system:node_secure_scan_max_files',
            'system:node_secure_chat_block_secret',
            'system:node_secure_deploy_gate_enabled',
            'system:node_secure_deploy_block_critical_patterns',
            'system:node_secure_container_policy_enabled',
            'system:node_secure_container_block_deprecated',
            'system:node_secure_container_allow_non_node',
            'system:node_secure_container_min_major',
            'system:node_secure_container_preferred_major',
            'system:api_rate_limit_ptla_period_minutes',
            'system:api_rate_limit_ptla_per_period',
            'system:api_rate_limit_ptlc_period_minutes',
            'system:api_rate_limit_ptlc_per_period',
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

    public function setEmergencyMode(
        Request $request,
        ProgressiveSecurityModeService $progressiveSecurityModeService
    ): JsonResponse {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'reason' => 'nullable|string|max:255',
        ]);

        $enabled = (bool) $data['enabled'];
        $reason = trim((string) ($data['reason'] ?? ''));
        $now = now();

        $baseSettings = [
            'root_emergency_mode' => $enabled ? 'true' : 'false',
            'panic_mode' => $enabled ? 'true' : 'false',
            'ptla_write_disabled' => $enabled ? 'true' : 'false',
            'chat_incident_mode' => $enabled ? 'true' : 'false',
            'hide_server_creation' => $enabled ? 'true' : 'false',
            'kill_switch_mode' => $enabled ? 'true' : 'false',
        ];
        foreach ($baseSettings as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => $now, 'updated_at' => $now]
            );
            Cache::forget("system:{$key}");
        }

        $profile = $enabled ? 'under_attack' : 'normal';
        Artisan::call('security:ddos-profile', ['profile' => $profile]);
        $progressiveSecurityModeService->applyMode($enabled ? 'lockdown' : 'normal');
        if ($enabled) {
            app(IdeSessionService::class)->revokeSessions(null, null, optional($request->user())->id, (string) $request->ip());
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('api:rootapplication.security.emergency_mode', [
            'actor_user_id' => optional($request->user())->id,
            'ip' => $request->ip(),
            'risk_level' => $enabled ? 'critical' : 'medium',
            'meta' => [
                'enabled' => $enabled,
                'reason' => $reason !== '' ? $reason : null,
                'profile' => $profile,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => $enabled ? 'Emergency mode enabled.' : 'Emergency mode disabled.',
            'state' => [
                'enabled' => $enabled,
                'profile' => $profile,
            ],
        ]);
    }

    public function runTrustAutomation(Request $request, TrustAutomationService $trustAutomationService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'nullable|integer|min:1',
            'force' => 'nullable|boolean',
        ]);

        $summary = $trustAutomationService->runCycle(
            isset($data['server_id']) ? (int) $data['server_id'] : null,
            (bool) ($data['force'] ?? false)
        );

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    public function ideSessionsStats(Request $request, IdeSessionService $ideSessionService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'nullable|integer|min:1',
        ]);

        return response()->json([
            'success' => true,
            'stats' => $ideSessionService->stats(isset($data['server_id']) ? (int) $data['server_id'] : null),
        ]);
    }

    public function ideValidateToken(Request $request, IdeSessionService $ideSessionService): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|min:20|max:256',
            'consume' => 'nullable|boolean',
            'server_identifier' => 'nullable|string|max:64',
        ]);

        try {
            $session = $ideSessionService->validateToken(
                (string) $data['token'],
                (bool) ($data['consume'] ?? false),
                isset($data['server_identifier']) ? (string) $data['server_identifier'] : null,
                (string) $request->ip()
            );
        } catch (\RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'session' => $session,
        ]);
    }

    public function ideRevokeSessions(Request $request, IdeSessionService $ideSessionService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'nullable|integer|min:1',
            'token_hash' => 'nullable|string|size:64',
        ]);

        $count = $ideSessionService->revokeSessions(
            isset($data['server_id']) ? (int) $data['server_id'] : null,
            isset($data['token_hash']) ? (string) $data['token_hash'] : null,
            optional($request->user())->id,
            (string) $request->ip()
        );

        return response()->json([
            'success' => true,
            'revoked' => $count,
        ]);
    }

    public function adaptiveOverview(AdaptiveInfrastructureService $adaptiveInfrastructureService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'adaptive' => $adaptiveInfrastructureService->overview(),
        ]);
    }

    public function adaptiveRun(AdaptiveInfrastructureService $adaptiveInfrastructureService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'summary' => $adaptiveInfrastructureService->runCycle(),
        ]);
    }

    public function topologyMap(AdaptiveInfrastructureService $adaptiveInfrastructureService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'topology' => $adaptiveInfrastructureService->topologyMap(),
        ]);
    }

    public function runSecuritySimulation(Request $request, SecuritySimulationService $securitySimulationService): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|in:bruteforce,api_abuse,burst,priv_escalation',
            'intensity' => 'nullable|integer|min:1|max:1000',
        ]);

        return response()->json([
            'success' => true,
            'result' => $securitySimulationService->run((string) $data['type'], (int) ($data['intensity'] ?? 100)),
        ]);
    }

    public function reputationNetworkStatus(ReputationNetworkService $reputationNetworkService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'network' => $reputationNetworkService->status(),
        ]);
    }

    public function reputationNetworkSync(ReputationNetworkService $reputationNetworkService): JsonResponse
    {
        return response()->json([
            'success' => true,
            'result' => $reputationNetworkService->sync(),
        ]);
    }

    public function ecosystemEvents(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $events = EventBusEvent::query()->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'success' => true,
            'events' => $events,
        ]);
    }

    public function ecosystemWebhooks(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'webhooks' => WebhookSubscription::query()->orderBy('id')->get(),
        ]);
    }

    public function createEcosystemWebhook(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'url' => 'required|url|max:1024',
            'event_pattern' => 'nullable|string|max:140',
            'secret' => 'nullable|string|max:191',
            'enabled' => 'nullable|boolean',
        ]);

        $url = trim((string) $data['url']);
        $urlCheck = app(OutboundTargetGuardService::class)->inspect($url);
        if (($urlCheck['ok'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'url' => [(string) ($urlCheck['reason'] ?? 'Invalid outbound target URL.')],
            ]);
        }

        $webhook = WebhookSubscription::query()->create([
            'name' => trim((string) $data['name']),
            'url' => $url,
            'event_pattern' => trim((string) ($data['event_pattern'] ?? '*')),
            'secret' => (string) ($data['secret'] ?? ''),
            'enabled' => (bool) ($data['enabled'] ?? true),
            'created_by' => optional($request->user())->id,
        ]);

        app(EventBusService::class)->emit('ecosystem.webhook.created', [
            'webhook_id' => $webhook->id,
            'name' => $webhook->name,
        ], 'ecosystem', null, optional($request->user())->id);

        return response()->json([
            'success' => true,
            'webhook' => $webhook,
        ], 201);
    }

    public function toggleEcosystemWebhook(Request $request, int $webhookId): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $webhook = WebhookSubscription::query()->findOrFail($webhookId);
        $webhook->forceFill([
            'enabled' => (bool) $data['enabled'],
            'updated_at' => now(),
        ])->save();

        app(EventBusService::class)->emit('ecosystem.webhook.toggled', [
            'webhook_id' => $webhook->id,
            'enabled' => $webhook->enabled,
        ], 'ecosystem', null, optional($request->user())->id);

        return response()->json([
            'success' => true,
            'webhook' => $webhook,
        ]);
    }

    public function securityTimeline(Request $request): JsonResponse
    {
        $perPage = max(1, min(100, (int) $request->query('per_page', 50)));
        $windowMinutes = max(5, min(10080, (int) $request->query('window_minutes', 1440)));
        $baseQuery = SecurityEvent::query()->where('created_at', '>=', now()->subMinutes($windowMinutes));

        if ($request->filled('server_id')) {
            $baseQuery->where('server_id', (int) $request->query('server_id'));
        }

        if ($request->filled('risk_level')) {
            $baseQuery->where('risk_level', (string) $request->query('risk_level'));
        }

        if ($request->filled('event_type')) {
            $baseQuery->where('event_type', 'like', '%' . trim((string) $request->query('event_type')) . '%');
        }

        $events = (clone $baseQuery)
            ->with(['actor:id,username', 'server:id,name,uuid'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $severity = (clone $baseQuery)
            ->select('risk_level', DB::raw('COUNT(*) as total'))
            ->groupBy('risk_level')
            ->pluck('total', 'risk_level');

        $perServer = (clone $baseQuery)
            ->select('server_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('server_id')
            ->groupBy('server_id')
            ->orderByDesc('total')
            ->limit(20)
            ->get()
            ->map(function ($row) {
                $server = Server::query()->find((int) $row->server_id, ['id', 'name', 'uuid']);

                return [
                    'server_id' => (int) $row->server_id,
                    'server_name' => $server?->name,
                    'server_uuid' => $server?->uuid,
                    'total' => (int) $row->total,
                ];
            });

        $fingerprints = (clone $baseQuery)->get(['event_type', 'ip', 'meta'])
            ->map(function (SecurityEvent $event) {
                $meta = is_array($event->meta) ? $event->meta : [];
                $reason = trim((string) Arr::get($meta, 'reason', Arr::get($meta, 'path', '')));
                $seed = sprintf('%s|%s|%s', $event->event_type, (string) ($event->ip ?? '-'), $reason);

                return [
                    'fingerprint' => substr(hash('sha1', $seed), 0, 16),
                    'event_type' => $event->event_type,
                    'ip' => $event->ip,
                    'reason' => $reason !== '' ? $reason : null,
                ];
            })
            ->groupBy('fingerprint')
            ->map(function ($group, $fingerprint) {
                $first = $group->first();

                return [
                    'fingerprint' => $fingerprint,
                    'event_type' => $first['event_type'],
                    'ip' => $first['ip'],
                    'reason' => $first['reason'],
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(25)
            ->values();

        return response()->json([
            'success' => true,
            'window_minutes' => $windowMinutes,
            'events' => $events,
            'severity' => $severity,
            'per_server' => $perServer,
            'fingerprints' => $fingerprints,
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
                    ->get(['id', 'server_id', 'version', 'access_count', 'last_accessed_at']),
            ],
        ]);
    }

    public function nodeSafeDeployScan(Request $request, NodeSecureModeService $nodeSecureModeService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'nullable|integer|min:1',
            'path' => 'nullable|string|max:2048',
        ]);

        $server = isset($data['server_id']) ? Server::query()->with('node')->find((int) $data['server_id']) : null;
        if (isset($data['server_id']) && !$server) {
            return response()->json(['success' => false, 'message' => 'Server not found.'], 404);
        }

        $result = $nodeSecureModeService->runSafeDeployScan($server, isset($data['path']) ? (string) $data['path'] : null);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function nodeNpmAudit(Request $request, NodeSecureModeService $nodeSecureModeService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'nullable|integer|min:1',
            'path' => 'nullable|string|max:2048',
        ]);

        $server = isset($data['server_id']) ? Server::query()->with('node')->find((int) $data['server_id']) : null;
        if (isset($data['server_id']) && !$server) {
            return response()->json(['success' => false, 'message' => 'Server not found.'], 404);
        }

        $result = $nodeSecureModeService->runNpmAudit($server, isset($data['path']) ? (string) $data['path'] : null);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function nodeRuntimeSample(Request $request, NodeSecureModeService $nodeSecureModeService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'required|integer|min:1|exists:servers,id',
            'rss_mb' => 'required|numeric|min:1|max:1048576',
            'heap_used_mb' => 'required|numeric|min:1|max:1048576',
            'heap_total_mb' => 'required|numeric|min:1|max:1048576',
            'gc_reclaimed_mb' => 'nullable|numeric|min:0|max:1048576',
        ]);

        $result = $nodeSecureModeService->ingestRuntimeSample((int) $data['server_id'], $data);

        return response()->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function nodeRuntimeSummary(Request $request, NodeSecureModeService $nodeSecureModeService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'required|integer|min:1|exists:servers,id',
        ]);

        return response()->json([
            'success' => true,
            'summary' => $nodeSecureModeService->runtimeSummary((int) $data['server_id']),
        ]);
    }

    public function nodeSecurityScore(Request $request, NodeSecureModeService $nodeSecureModeService): JsonResponse
    {
        $data = $request->validate([
            'server_id' => 'required|integer|min:1|exists:servers,id',
        ]);

        return response()->json([
            'success' => true,
            'score' => $nodeSecureModeService->securityScore((int) $data['server_id']),
        ]);
    }

    public function nodeContainerPolicyCheck(Request $request, NodeContainerPolicyService $nodeContainerPolicyService): JsonResponse
    {
        $data = $request->validate([
            'image' => 'required|string|max:191',
            'server_id' => 'nullable|integer|min:1|exists:servers,id',
        ]);

        $server = isset($data['server_id']) ? Server::query()->find((int) $data['server_id']) : null;
        try {
            $evaluation = $nodeContainerPolicyService->enforceImagePolicy(
                (string) $data['image'],
                $server,
                optional($request->user())->id,
                (string) $request->ip()
            );
        } catch (\Throwable $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'evaluation' => $nodeContainerPolicyService->evaluateImage((string) $data['image']),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'evaluation' => $evaluation,
        ]);
    }

    private function boolSetting(string $key, bool $default = false): bool
    {
        $value = DB::table('system_settings')->where('key', $key)->value('value');
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function intSetting(string $key, int $default): int
    {
        $value = DB::table('system_settings')->where('key', $key)->value('value');
        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    private function setSetting(string $key, string $value): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
