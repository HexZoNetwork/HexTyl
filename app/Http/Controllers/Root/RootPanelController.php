<?php

namespace Pterodactyl\Http\Controllers\Root;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\EggVariable;
use Pterodactyl\Models\Allocation;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Models\RiskSnapshot;
use Pterodactyl\Models\ServerHealthScore;
use Pterodactyl\Models\NodeHealthScore;
use Pterodactyl\Models\IdeSession;
use Pterodactyl\Models\ServerReputation;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Maintenance\GlobalMaintenanceService;
use Pterodactyl\Services\Nodes\NodeAutoBalancerService;
use Pterodactyl\Services\Security\ThreatIntelligenceService;
use Pterodactyl\Services\Observability\RootAuditTimelineService;
use Pterodactyl\Services\Observability\ServerHealthScoringService;
use Pterodactyl\Services\Ide\IdeSessionService;
use Pterodactyl\Services\Security\ProgressiveSecurityModeService;
use Pterodactyl\Services\Security\TrustAutomationService;
use Pterodactyl\Services\Servers\ServerReputationService;
use Pterodactyl\Services\Testing\AbuseSimulationService;
use Pterodactyl\Services\Users\UserDeletionService;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Illuminate\Support\Str;

class RootPanelController extends Controller
{
    public function __construct()
    {
    }

    /** Enforce root-only access for all root panel methods. */
    private function requireRoot(Request $request): void
    {
        if (!$request->user() || !$request->user()->isRoot()) {
            throw new AccessDeniedHttpException('Root panel access is restricted to the root account.');
        }
    }

    /** Root Panel Dashboard */
    public function index(Request $request)
    {
        $this->requireRoot($request);
        if ($request->boolean('recalculate')) {
            app(ServerHealthScoringService::class)->recalculateAll();
            app(NodeAutoBalancerService::class)->recalculateAll();
        }
        $securityMode = app(ProgressiveSecurityModeService::class)->evaluateSystemMode();

        $stats = [
            'users'   => User::count(),
            'servers' => Server::count(),
            'nodes'   => Node::count(),
            'nests'   => Nest::count(),
            'eggs'    => Egg::count(),
            'api_keys' => ApiKey::count(),
            'root_keys' => ApiKey::where('key_type', ApiKey::TYPE_ROOT)->count(),
            'public_servers' => Server::where('visibility', 'public')->count(),
            'private_servers' => Server::where('visibility', 'private')->count(),
            'suspended' => Server::where('status', 'suspended')->count(),
            'maintenance_mode' => $this->boolSetting('maintenance_mode'),
            'panic_mode' => $this->boolSetting('panic_mode'),
            'silent_defense_mode' => $this->boolSetting('silent_defense_mode'),
            'kill_switch_mode' => $this->boolSetting('kill_switch_mode'),
            'progressive_security_mode' => $securityMode,
            'security_events_24h' => SecurityEvent::query()->where('created_at', '>=', now()->subDay())->count(),
            'critical_risks' => RiskSnapshot::query()->where('risk_score', '>=', 80)->count(),
            'avg_server_health' => round((float) ServerHealthScore::query()->avg('stability_index'), 1),
            'avg_node_health' => round((float) NodeHealthScore::query()->avg('health_score'), 1),
        ];

        return view('root.dashboard', compact('stats'));
    }

    /** Root Users Management */
    public function users(Request $request)
    {
        $this->requireRoot($request);
        $users = User::with(['role'])->withCount(['servers'])->orderBy('id')->paginate(50);
        return view('root.users', compact('users'));
    }

    public function createTester(Request $request)
    {
        $this->requireRoot($request);

        $testerRoleId = Role::query()->whereRaw('LOWER(name) = ?', ['tester'])->value('id');
        if (!$testerRoleId) {
            return redirect()->route('root.users')->with('error', 'Tester role is not installed yet. Run migrations first.');
        }

        $suffix = Str::lower(Str::random(8));
        $username = "tester-{$suffix}";
        $password = Str::random(20);

        $user = app(\Pterodactyl\Services\Users\UserCreationService::class)->handle([
            'email' => "{$username}@tester.local",
            'username' => $username,
            'name_first' => 'Security',
            'name_last' => 'Tester',
            'language' => config('app.locale', 'en'),
            'password' => $password,
            'role_id' => (int) $testerRoleId,
        ]);

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:user.tester_created', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'meta' => [
                'target_user_id' => $user->id,
                'username' => $username,
            ],
        ]);

        return redirect()->route('root.users')->with('success', "Tester created: {$username} / {$password}");
    }

    public function createQuickServer(Request $request, User $user)
    {
        $this->requireRoot($request);

        $requestedEggId = (int) $request->input('egg_id', $request->query('egg_id', 0));
        $eggQuery = Egg::query()->whereNotNull('startup')->where('startup', '!=', '');
        if ($requestedEggId > 0) {
            $eggQuery->where('id', $requestedEggId);
        }

        $egg = $eggQuery->orderBy('id')->first();
        if (!$egg) {
            if ($requestedEggId > 0) {
                return redirect()->route('root.users')->with('error', "Egg #{$requestedEggId} is not valid for quick create.");
            }

            return redirect()->route('root.users')->with('error', 'No valid egg found for quick server creation.');
        }

        $images = (array) ($egg->docker_images ?? []);
        $firstImage = (string) (count($images) > 0 ? reset($images) : '');
        $image = $this->normalizeEggImage($firstImage);
        if ($image === '') {
            return redirect()->route('root.users')->with('error', 'Selected egg does not provide a valid Docker image.');
        }

        $environment = EggVariable::query()
            ->where('egg_id', $egg->id)
            ->get()
            ->mapWithKeys(fn (EggVariable $variable) => [$variable->env_variable => (string) ($variable->default_value ?? '')])
            ->toArray();

        $requestedCount = (int) $request->input('count', $request->query('count', 1));
        $count = max(1, min(50, $requestedCount));
        $visibility = $user->isTester() ? Server::VISIBILITY_PUBLIC : Server::VISIBILITY_PRIVATE;
        $creationService = app(\Pterodactyl\Services\Servers\ServerCreationService::class);
        $created = 0;
        $errors = [];
        $lastServer = null;

        for ($i = 1; $i <= $count; $i++) {
            $allocation = Allocation::query()
                ->select('allocations.*')
                ->join('nodes', 'nodes.id', '=', 'allocations.node_id')
                ->whereNull('allocations.server_id')
                ->where('nodes.maintenance_mode', false)
                ->orderBy('allocations.id')
                ->first();

            if (!$allocation) {
                $errors[] = 'No free allocation left.';
                break;
            }

            $name = sprintf('quick-%s-%s', $user->username, Str::lower(Str::random(4)));
            $data = [
                'owner_id' => $user->id,
                'name' => $name,
                'description' => 'Quick-created by root panel',
                'visibility' => $visibility,
                'node_id' => $allocation->node_id,
                'allocation_id' => $allocation->id,
                'nest_id' => $egg->nest_id,
                'egg_id' => $egg->id,
                'startup' => (string) $egg->startup,
                'image' => $image,
                'environment' => $environment,
                'memory' => 0,
                'swap' => -1,
                'disk' => 0,
                'io' => 500,
                'cpu' => 0,
                'threads' => null,
                'oom_disabled' => true,
                // Effective "unlimited" profile for panel-side feature limits.
                'database_limit' => 999999,
                'allocation_limit' => 999999,
                'backup_limit' => 999999,
                'skip_scripts' => false,
                'start_on_completion' => false,
            ];

            try {
                $lastServer = $creationService->handle($data);
                $created++;
            } catch (\Throwable $exception) {
                $errors[] = mb_substr($exception->getMessage(), 0, 160);
            }
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:server.quick_created', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'server_id' => $lastServer?->id,
            'meta' => [
                'owner_user_id' => $user->id,
                'owner_username' => $user->username,
                'egg_id' => $egg->id,
                'requested_count' => $count,
                'created_count' => $created,
                'visibility' => $visibility,
                'errors' => $errors,
            ],
        ]);

        if ($created < 1) {
            return redirect()->route('root.users')->with('error', 'Bulk quick server failed: ' . ($errors[0] ?? 'Unknown error.'));
        }

        $message = "Created {$created}/{$count} quick server(s) for {$user->username}.";
        if ($user->isTester()) {
            $message .= ' Tester visibility mode: public.';
        }
        if (!empty($errors)) {
            $message .= ' Some failed.';
        }

        return redirect()->route('root.users')->with('success', $message);
    }

    /** Root Servers Management */
    public function servers(Request $request)
    {
        $this->requireRoot($request);
        $query = Server::query()->with(['user', 'node', 'nest', 'egg', 'reputation'])->orderBy('id');

        if ($request->boolean('public_only')) {
            $query->where('visibility', Server::VISIBILITY_PUBLIC);
        }

        $minTrust = max(0, (int) $request->query('min_trust', 0));
        if ($minTrust > 0) {
            $query->whereHas('reputation', fn ($q) => $q->where('trust_score', '>=', $minTrust));
        }

        $power = strtolower((string) $request->query('power', ''));
        if (in_array($power, ['off', 'offline'], true)) {
            $query->whereNotNull('status');
        } elseif (in_array($power, ['on', 'online'], true)) {
            $query->whereNull('status');
        } else {
            $power = '';
        }

        $servers = $query->paginate(50)->appends($request->query());
        $offlineCount = Server::query()->whereNotNull('status')->count();

        $reputationService = app(ServerReputationService::class);
        foreach ($servers as $server) {
            if (!$server->reputation || !$server->reputation->last_calculated_at || $server->reputation->last_calculated_at->lt(now()->subHour())) {
                $server->setRelation('reputation', $reputationService->recalculate($server));
            }
        }

        return view('root.servers', compact('servers', 'minTrust', 'offlineCount', 'power'));
    }

    /** Root Nodes Management */
    public function nodes(Request $request)
    {
        $this->requireRoot($request);
        $nodes = Node::withCount(['servers'])->with(['location'])->orderBy('id')->paginate(50);
        return view('root.nodes', compact('nodes'));
    }

    /** Root API Keys — both regular and root keys across all users */
    public function apiKeys(Request $request)
    {
        $this->requireRoot($request);
        $keys = ApiKey::with(['user'])->orderBy('created_at', 'desc')->paginate(100);
        return view('root.api_keys', compact('keys'));
    }

    /** Revoke any API key system-wide */
    public function revokeKey(Request $request, string $identifier)
    {
        $this->requireRoot($request);
        ApiKey::where('identifier', $identifier)->delete();
        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:api_key.revoked', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'meta' => ['identifier' => $identifier],
        ]);

        return redirect()->route('root.api_keys')->with('success', 'API key revoked.');
    }

    /** Suspend / unsuspend a user */
    public function toggleUserSuspension(Request $request, User $user)
    {
        $this->requireRoot($request);
        $nextState = !$user->suspended;
        if ($user->isTester()) {
            $nextState = false;
        }

        $user->update(['suspended' => $nextState]);

        $ownedServers = $user->servers()->get();
        $reinstallService = app(\Pterodactyl\Services\Servers\ReinstallServerService::class);
        $suspensionService = app(\Pterodactyl\Services\Servers\SuspensionService::class);

        $serverMeta = [
            'owned_servers' => $ownedServers->count(),
            'reinstalled' => 0,
            'suspended' => 0,
            'unsuspended' => 0,
            'errors' => [],
        ];

        foreach ($ownedServers as $server) {
            try {
                if ($nextState) {
                    // Wipe server contents first, then keep it suspended.
                    $reinstallService->handle($server);
                    $serverMeta['reinstalled']++;

                    $suspensionService->toggle($server, \Pterodactyl\Services\Servers\SuspensionService::ACTION_SUSPEND);
                    $serverMeta['suspended']++;
                } else {
                    $suspensionService->toggle($server, \Pterodactyl\Services\Servers\SuspensionService::ACTION_UNSUSPEND);
                    $serverMeta['unsuspended']++;
                }
            } catch (\Throwable $exception) {
                $serverMeta['errors'][] = [
                    'server_id' => $server->id,
                    'message' => mb_substr($exception->getMessage(), 0, 200),
                ];
            }
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:user.toggle_suspension', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'high',
            'meta' => [
                'target_user_id' => $user->id,
                'suspended' => $user->suspended,
                'servers' => $serverMeta,
            ],
        ]);

        if ($user->isTester()) {
            return redirect()->route('root.users')->with('success', 'Tester accounts cannot remain suspended. State normalized to active.');
        }

        if ($nextState) {
            $message = sprintf(
                'User suspended. %d/%d server(s) wiped and %d server(s) suspended.',
                (int) $serverMeta['reinstalled'],
                (int) $serverMeta['owned_servers'],
                (int) $serverMeta['suspended']
            );

            if (!empty($serverMeta['errors'])) {
                $message .= ' Some servers failed; check security timeline.';
            }

            return redirect()->route('root.users')->with('success', $message);
        }

        return redirect()->route('root.users')->with('success', sprintf(
            'User unsuspended. %d server(s) unsuspended.',
            (int) $serverMeta['unsuspended']
        ));
    }

    /** Delete user from root panel (without method spoof dependency). */
    public function deleteUser(Request $request, User $user)
    {
        $this->requireRoot($request);

        if ($request->user()->is($user)) {
            return redirect()->route('root.users')->with('error', 'You cannot delete your own root account.');
        }

        if ($user->isRoot()) {
            return redirect()->route('root.users')->with('error', 'Cannot delete system root user.');
        }

        try {
            app(UserDeletionService::class)->handle($user);
        } catch (\Throwable $exception) {
            return redirect()->route('root.users')->with('error', 'Delete user failed: ' . mb_substr($exception->getMessage(), 0, 180));
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:user.deleted', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'critical',
            'meta' => ['target_user_id' => $user->id],
        ]);

        return redirect()->route('root.users')->with('success', 'User deleted.');
    }

    private function normalizeEggImage(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }

        if (str_contains($value, '|')) {
            $parts = explode('|', $value, 2);
            $candidate = trim((string) ($parts[1] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $value;
    }

    /** Force delete a server */
    public function deleteServer(Request $request, Server $server)
    {
        $this->requireRoot($request);
        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:server.deleted', [
            'actor_user_id' => $request->user()->id,
            'server_id' => $server->id,
            'ip' => $request->ip(),
            'risk_level' => 'critical',
            'meta' => ['server_uuid' => $server->uuid],
        ]);
        $server->delete();

        return redirect()->route('root.servers')->with('success', 'Server deleted.');
    }

    public function deleteOfflineServers(Request $request)
    {
        $this->requireRoot($request);

        $offlineIds = Server::query()
            ->whereNotNull('status')
            ->pluck('id')
            ->all();

        $deleted = 0;
        if (!empty($offlineIds)) {
            $deleted = Server::query()->whereIn('id', $offlineIds)->delete();
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:server.bulk_delete_offline', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'critical',
            'meta' => [
                'deleted_count' => $deleted,
            ],
        ]);

        return redirect()->route('root.servers')->with('success', "Deleted {$deleted} offline server(s).");
    }

    public function deleteSelectedOfflineServers(Request $request)
    {
        $this->requireRoot($request);

        $validated = $request->validate([
            'selected_ids' => 'required|array|min:1',
            'selected_ids.*' => 'integer|min:1',
        ]);

        $selectedIds = collect($validated['selected_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $offlineIds = Server::query()
            ->whereIn('id', $selectedIds)
            ->whereNotNull('status')
            ->pluck('id')
            ->all();

        $deleted = 0;
        if (!empty($offlineIds)) {
            $deleted = Server::query()->whereIn('id', $offlineIds)->delete();
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:server.bulk_delete_selected_offline', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'critical',
            'meta' => [
                'selected_count' => count($selectedIds),
                'deleted_count' => $deleted,
            ],
        ]);

        return redirect()->route('root.servers')->with('success', "Deleted {$deleted} selected offline server(s).");
    }

    public function security(Request $request)
    {
        $this->requireRoot($request);

        $settings = [
            'maintenance_mode' => $this->boolSetting('maintenance_mode'),
            'panic_mode' => $this->boolSetting('panic_mode'),
            'silent_defense_mode' => $this->boolSetting('silent_defense_mode'),
            'kill_switch_mode' => $this->boolSetting('kill_switch_mode'),
            'root_emergency_mode' => $this->boolSetting('root_emergency_mode'),
            'ptla_write_disabled' => $this->boolSetting('ptla_write_disabled'),
            'chat_incident_mode' => $this->boolSetting('chat_incident_mode'),
            'hide_server_creation' => $this->boolSetting('hide_server_creation'),
            'ddos_lockdown_mode' => $this->boolSetting('ddos_lockdown_mode', false),
            'ddos_autoprofile_enabled' => $this->boolSetting('ddos_autoprofile_enabled', true),
            'ddos_runtime_profile' => (string) (DB::table('system_settings')->where('key', 'ddos_runtime_profile')->value('value') ?? 'normal'),
            'progressive_security_mode' => (string) (DB::table('system_settings')->where('key', 'progressive_security_mode')->value('value') ?? 'normal'),
            'kill_switch_whitelist_ips' => (string) DB::table('system_settings')->where('key', 'kill_switch_whitelist_ips')->value('value'),
            'maintenance_message' => (string) DB::table('system_settings')->where('key', 'maintenance_message')->value('value'),
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
        ];

        $topRisk = collect();
        $reputationStats = [
            'avg_trust' => round((float) ServerReputation::query()->avg('trust_score'), 1),
            'low_trust' => ServerReputation::query()->where('trust_score', '<', 40)->count(),
            'high_trust' => ServerReputation::query()->where('trust_score', '>=', 80)->count(),
        ];
        $ideStats = [
            'total' => IdeSession::query()->count(),
            'active' => IdeSession::query()->whereNull('revoked_at')->whereNull('consumed_at')->where('expires_at', '>=', now())->count(),
            'consumed_24h' => IdeSession::query()->whereNotNull('consumed_at')->where('consumed_at', '>=', now()->subDay())->count(),
            'revoked_24h' => IdeSession::query()->whereNotNull('revoked_at')->where('revoked_at', '>=', now()->subDay())->count(),
        ];

        return view('root.security', compact('settings', 'reputationStats', 'topRisk', 'ideStats'));
    }

    public function updateSecuritySettings(
        Request $request,
        GlobalMaintenanceService $maintenanceService,
        ProgressiveSecurityModeService $progressiveSecurityModeService
    )
    {
        $this->requireRoot($request);

        $data = $request->validate([
            'maintenance_mode' => 'nullable|boolean',
            'panic_mode' => 'nullable|boolean',
            'silent_defense_mode' => 'nullable|boolean',
            'kill_switch_mode' => 'nullable|boolean',
            'ptla_write_disabled' => 'nullable|boolean',
            'chat_incident_mode' => 'nullable|boolean',
            'hide_server_creation' => 'nullable|boolean',
            'ddos_lockdown_mode' => 'nullable|boolean',
            'ddos_autoprofile_enabled' => 'nullable|boolean',
            'ddos_runtime_profile' => 'nullable|string|in:normal,elevated,under_attack,internetwar',
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
            'progressive_security_mode' => 'nullable|string|in:normal,elevated,lockdown',
            'kill_switch_whitelist_ips' => 'nullable|string|max:2000',
            'maintenance_message' => 'nullable|string|max:255',
        ]);

        $maintenanceEnabled = (bool) ($data['maintenance_mode'] ?? false);
        if ($maintenanceEnabled) {
            $maintenanceService->enable($data['maintenance_message'] ?? 'System Maintenance');
        } else {
            $maintenanceService->disable();
        }

        $this->setSetting('panic_mode', $this->asBoolString($data['panic_mode'] ?? false));
        $this->setSetting('silent_defense_mode', $this->asBoolString($data['silent_defense_mode'] ?? false));
        $this->setSetting('kill_switch_mode', $this->asBoolString($data['kill_switch_mode'] ?? false));
        $this->setSetting('ptla_write_disabled', $this->asBoolString($data['ptla_write_disabled'] ?? false));
        $this->setSetting('chat_incident_mode', $this->asBoolString($data['chat_incident_mode'] ?? false));
        $this->setSetting('hide_server_creation', $this->asBoolString($data['hide_server_creation'] ?? false));
        $this->setSetting('ddos_lockdown_mode', $this->asBoolString($data['ddos_lockdown_mode'] ?? false));
        $this->setSetting('ddos_autoprofile_enabled', $this->asBoolString($data['ddos_autoprofile_enabled'] ?? true));
        $this->setSetting('trust_automation_enabled', $this->asBoolString($data['trust_automation_enabled'] ?? true));
        $this->setSetting('trust_automation_elevated_threshold', (string) (int) ($data['trust_automation_elevated_threshold'] ?? 50));
        $this->setSetting('trust_automation_quarantine_threshold', (string) (int) ($data['trust_automation_quarantine_threshold'] ?? 30));
        $this->setSetting('trust_automation_drop_threshold', (string) (int) ($data['trust_automation_drop_threshold'] ?? 20));
        $this->setSetting('trust_automation_drop_window_minutes', (string) (int) ($data['trust_automation_drop_window_minutes'] ?? 10));
        $this->setSetting('trust_automation_quarantine_minutes', (string) (int) ($data['trust_automation_quarantine_minutes'] ?? 30));
        $this->setSetting('trust_automation_profile_cooldown_minutes', (string) (int) ($data['trust_automation_profile_cooldown_minutes'] ?? 5));
        $this->setSetting('trust_automation_lockdown_cooldown_minutes', (string) (int) ($data['trust_automation_lockdown_cooldown_minutes'] ?? 10));
        $this->setSetting('ide_connect_enabled', $this->asBoolString($data['ide_connect_enabled'] ?? false));
        $this->setSetting('ide_block_during_emergency', $this->asBoolString($data['ide_block_during_emergency'] ?? true));
        $this->setSetting('ide_session_ttl_minutes', (string) (int) ($data['ide_session_ttl_minutes'] ?? 10));
        $this->setSetting('ide_connect_url_template', trim((string) ($data['ide_connect_url_template'] ?? '')));
        $this->setSetting('adaptive_alpha', (string) ((float) ($data['adaptive_alpha'] ?? 0.2)));
        $this->setSetting('adaptive_z_threshold', (string) ((float) ($data['adaptive_z_threshold'] ?? 2.5)));
        $this->setSetting('reputation_network_enabled', $this->asBoolString($data['reputation_network_enabled'] ?? false));
        $this->setSetting('reputation_network_allow_pull', $this->asBoolString($data['reputation_network_allow_pull'] ?? true));
        $this->setSetting('reputation_network_allow_push', $this->asBoolString($data['reputation_network_allow_push'] ?? true));
        $this->setSetting('reputation_network_endpoint', trim((string) ($data['reputation_network_endpoint'] ?? '')));
        $this->setSetting('node_secure_mode_enabled', $this->asBoolString($data['node_secure_mode_enabled'] ?? false));
        $this->setSetting('node_secure_discord_quarantine_enabled', $this->asBoolString($data['node_secure_discord_quarantine_enabled'] ?? true));
        $this->setSetting('node_secure_discord_quarantine_minutes', (string) (int) ($data['node_secure_discord_quarantine_minutes'] ?? 30));
        $this->setSetting('node_secure_npm_block_high', $this->asBoolString($data['node_secure_npm_block_high'] ?? true));
        $this->setSetting('node_secure_per_app_rate_per_minute', (string) (int) ($data['node_secure_per_app_rate_per_minute'] ?? 240));
        $this->setSetting('node_secure_per_app_write_rate_per_minute', (string) (int) ($data['node_secure_per_app_write_rate_per_minute'] ?? 90));
        $this->setSetting('node_secure_scan_max_files', (string) (int) ($data['node_secure_scan_max_files'] ?? 180));
        $this->setSetting('node_secure_chat_block_secret', $this->asBoolString($data['node_secure_chat_block_secret'] ?? true));
        $this->setSetting('node_secure_deploy_gate_enabled', $this->asBoolString($data['node_secure_deploy_gate_enabled'] ?? true));
        $this->setSetting('node_secure_deploy_block_critical_patterns', $this->asBoolString($data['node_secure_deploy_block_critical_patterns'] ?? false));
        $this->setSetting('node_secure_container_policy_enabled', $this->asBoolString($data['node_secure_container_policy_enabled'] ?? false));
        $this->setSetting('node_secure_container_block_deprecated', $this->asBoolString($data['node_secure_container_block_deprecated'] ?? true));
        $this->setSetting('node_secure_container_allow_non_node', $this->asBoolString($data['node_secure_container_allow_non_node'] ?? true));
        $this->setSetting('node_secure_container_min_major', (string) (int) ($data['node_secure_container_min_major'] ?? 18));
        $this->setSetting('node_secure_container_preferred_major', (string) (int) ($data['node_secure_container_preferred_major'] ?? 22));
        $this->setSetting('api_rate_limit_ptla_period_minutes', (string) (int) ($data['api_rate_limit_ptla_period_minutes'] ?? (int) config('http.rate_limit.application_period', 1)));
        $this->setSetting('api_rate_limit_ptla_per_period', (string) (int) ($data['api_rate_limit_ptla_per_period'] ?? (int) config('http.rate_limit.application', 256)));
        $this->setSetting('api_rate_limit_ptlc_period_minutes', (string) (int) ($data['api_rate_limit_ptlc_period_minutes'] ?? (int) config('http.rate_limit.client_period', 1)));
        $this->setSetting('api_rate_limit_ptlc_per_period', (string) (int) ($data['api_rate_limit_ptlc_per_period'] ?? (int) config('http.rate_limit.client', 256)));
        if (array_key_exists('reputation_network_token', $data) && trim((string) $data['reputation_network_token']) !== '') {
            $this->setSetting('reputation_network_token', trim((string) $data['reputation_network_token']));
        }
        $this->setSetting('kill_switch_whitelist_ips', trim((string) ($data['kill_switch_whitelist_ips'] ?? '')));
        $this->setSetting('maintenance_message', trim((string) ($data['maintenance_message'] ?? 'System Maintenance')));
        if (!empty($data['progressive_security_mode'])) {
            $progressiveSecurityModeService->applyMode($data['progressive_security_mode']);
        }
        if (!empty($data['ddos_runtime_profile'])) {
            $this->setSetting('ddos_runtime_profile', (string) $data['ddos_runtime_profile']);
            $this->applyDdosProfile((string) $data['ddos_runtime_profile']);
        }
        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:security.settings.updated', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'meta' => [
                'maintenance_mode' => (bool) ($data['maintenance_mode'] ?? false),
                'kill_switch_mode' => (bool) ($data['kill_switch_mode'] ?? false),
                'progressive_security_mode' => (string) ($data['progressive_security_mode'] ?? 'unchanged'),
                'ptla_write_disabled' => (bool) ($data['ptla_write_disabled'] ?? false),
                'chat_incident_mode' => (bool) ($data['chat_incident_mode'] ?? false),
                'hide_server_creation' => (bool) ($data['hide_server_creation'] ?? false),
                'ddos_lockdown_mode' => (bool) ($data['ddos_lockdown_mode'] ?? false),
                'ddos_autoprofile_enabled' => (bool) ($data['ddos_autoprofile_enabled'] ?? true),
                'ddos_runtime_profile' => (string) ($data['ddos_runtime_profile'] ?? 'unchanged'),
                'trust_automation_enabled' => (bool) ($data['trust_automation_enabled'] ?? true),
                'node_secure_mode_enabled' => (bool) ($data['node_secure_mode_enabled'] ?? false),
                'api_rate_limit_ptla_per_period' => (int) ($data['api_rate_limit_ptla_per_period'] ?? (int) config('http.rate_limit.application', 256)),
                'api_rate_limit_ptlc_per_period' => (int) ($data['api_rate_limit_ptlc_per_period'] ?? (int) config('http.rate_limit.client', 256)),
            ],
        ]);

        Cache::forget('system:panic_mode');
        Cache::forget('system:silent_defense_mode');
        Cache::forget('system:kill_switch_mode');
        Cache::forget('system:kill_switch_whitelist');
        Cache::forget('system:maintenance_mode');
        Cache::forget('system:maintenance_message');
        Cache::forget('system:root_emergency_mode');
        Cache::forget('system:ptla_write_disabled');
        Cache::forget('system:chat_incident_mode');
        Cache::forget('system:hide_server_creation');
        Cache::forget('system:ddos_lockdown_mode');
        Cache::forget('system:ddos_autoprofile_enabled');
        Cache::forget('system:ddos_runtime_profile');
        Cache::forget('system:trust_automation_enabled');
        Cache::forget('system:trust_automation_elevated_threshold');
        Cache::forget('system:trust_automation_quarantine_threshold');
        Cache::forget('system:trust_automation_drop_threshold');
        Cache::forget('system:trust_automation_drop_window_minutes');
        Cache::forget('system:trust_automation_quarantine_minutes');
        Cache::forget('system:trust_automation_profile_cooldown_minutes');
        Cache::forget('system:trust_automation_lockdown_cooldown_minutes');
        Cache::forget('system:ide_connect_enabled');
        Cache::forget('system:ide_block_during_emergency');
        Cache::forget('system:ide_session_ttl_minutes');
        Cache::forget('system:ide_connect_url_template');
        Cache::forget('system:adaptive_alpha');
        Cache::forget('system:adaptive_z_threshold');
        Cache::forget('system:reputation_network_enabled');
        Cache::forget('system:reputation_network_allow_pull');
        Cache::forget('system:reputation_network_allow_push');
        Cache::forget('system:reputation_network_endpoint');
        Cache::forget('system:reputation_network_token');
        Cache::forget('system:node_secure_mode_enabled');
        Cache::forget('system:node_secure_discord_quarantine_enabled');
        Cache::forget('system:node_secure_discord_quarantine_minutes');
        Cache::forget('system:node_secure_npm_block_high');
        Cache::forget('system:node_secure_per_app_rate_per_minute');
        Cache::forget('system:node_secure_per_app_write_rate_per_minute');
        Cache::forget('system:node_secure_scan_max_files');
        Cache::forget('system:node_secure_chat_block_secret');
        Cache::forget('system:node_secure_deploy_gate_enabled');
        Cache::forget('system:node_secure_deploy_block_critical_patterns');
        Cache::forget('system:node_secure_container_policy_enabled');
        Cache::forget('system:node_secure_container_block_deprecated');
        Cache::forget('system:node_secure_container_allow_non_node');
        Cache::forget('system:node_secure_container_min_major');
        Cache::forget('system:node_secure_container_preferred_major');
        Cache::forget('system:api_rate_limit_ptla_period_minutes');
        Cache::forget('system:api_rate_limit_ptla_per_period');
        Cache::forget('system:api_rate_limit_ptlc_period_minutes');
        Cache::forget('system:api_rate_limit_ptlc_per_period');

        return redirect()->route('root.security')->with('success', 'Security settings updated.');
    }

    public function simulateAbuse(Request $request, AbuseSimulationService $simulationService)
    {
        $this->requireRoot($request);
        $requests = max(1, min(1000, (int) $request->input('requests', 100)));
        $result = $simulationService->simulateHttpFlood($requests);
        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:abuse.simulation', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'low',
            'meta' => ['requests' => $requests, 'result' => $result],
        ]);

        return redirect()->route('root.security')->with(
            'success',
            "Abuse simulation completed. Success: {$result['success']}, Failed: {$result['failed']}."
        );
    }

    public function toggleEmergencyMode(Request $request, ProgressiveSecurityModeService $progressiveSecurityModeService)
    {
        $this->requireRoot($request);

        $data = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = (bool) $data['enabled'];
        $this->setSetting('root_emergency_mode', $this->asBoolString($enabled));
        $this->setSetting('panic_mode', $this->asBoolString($enabled));
        $this->setSetting('kill_switch_mode', $this->asBoolString($enabled));
        $this->setSetting('ptla_write_disabled', $this->asBoolString($enabled));
        $this->setSetting('chat_incident_mode', $this->asBoolString($enabled));
        $this->setSetting('hide_server_creation', $this->asBoolString($enabled));

        Artisan::call('security:ddos-profile', ['profile' => $enabled ? 'under_attack' : 'normal']);
        $progressiveSecurityModeService->applyMode($enabled ? 'lockdown' : 'normal');
        if ($enabled) {
            app(IdeSessionService::class)->revokeSessions(null, null, $request->user()->id, (string) $request->ip());
        }

        foreach ([
            'system:root_emergency_mode',
            'system:panic_mode',
            'system:kill_switch_mode',
            'system:ptla_write_disabled',
            'system:chat_incident_mode',
            'system:hide_server_creation',
        ] as $cacheKey) {
            Cache::forget($cacheKey);
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:security.emergency_mode', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => $enabled ? 'critical' : 'medium',
            'meta' => ['enabled' => $enabled],
        ]);

        return redirect()->route('root.security')->with('success', $enabled ? 'Emergency mode enabled.' : 'Emergency mode disabled.');
    }

    public function runTrustAutomation(Request $request, TrustAutomationService $trustAutomationService)
    {
        $this->requireRoot($request);

        $summary = $trustAutomationService->runCycle(null, true);

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('root:security.trust_automation.run', [
            'actor_user_id' => $request->user()->id,
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'meta' => $summary,
        ]);

        return redirect()->route('root.security')->with(
            'success',
            sprintf(
                'Trust automation run complete: checked=%d, elevated=%d, quarantined=%d, lockdown=%d.',
                (int) ($summary['checked'] ?? 0),
                (int) ($summary['elevated_applied'] ?? 0),
                (int) ($summary['quarantined'] ?? 0),
                (int) ($summary['lockdown_triggered'] ?? 0)
            )
        );
    }

    public function threatIntelligence(Request $request, ThreatIntelligenceService $threatIntelligenceService)
    {
        $this->requireRoot($request);
        $data = $threatIntelligenceService->overview();

        return view('root.threat_intelligence', compact('data'));
    }

    public function auditTimeline(Request $request, RootAuditTimelineService $timelineService)
    {
        $this->requireRoot($request);

        $filters = $request->only([
            'user_id',
            'server_id',
            'risk_level',
            'event_type',
            'ip',
            'q',
            'date_from',
            'date_to',
        ]);
        $perPage = max(20, min(200, (int) $request->input('per_page', 50)));
        $events = $timelineService->query($filters)->paginate($perPage)->appends($request->query());
        $summary = $timelineService->summary($filters);

        return view('root.audit_timeline', compact('events', 'filters', 'summary', 'perPage'));
    }

    public function healthCenter(Request $request)
    {
        $this->requireRoot($request);

        if ($request->boolean('recalculate')) {
            app(ServerHealthScoringService::class)->recalculateAll();
            app(NodeAutoBalancerService::class)->recalculateAll();

            return redirect()
                ->route('root.health_center')
                ->with('success', 'Health scores recalculated.');
        }

        $serverHealth = ServerHealthScore::query()->with('server:id,name,uuid,status')->orderBy('stability_index')->paginate(30, ['*'], 'servers');
        $nodeHealth = NodeHealthScore::query()->with('node:id,name,fqdn')->orderBy('health_score')->paginate(30, ['*'], 'nodes');

        return view('root.health_center', compact('serverHealth', 'nodeHealth'));
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

    private function applyDdosProfile(string $profile): void
    {
        $allowed = ['normal', 'elevated', 'under_attack', 'internetwar'];
        if (!in_array($profile, $allowed, true)) {
            return;
        }

        $script = base_path('scripts/set_antiddos_profile.sh');
        if (!is_file($script) || !is_executable($script)) {
            return;
        }

        if (!is_file('/usr/bin/sudo')) {
            return;
        }

        $process = new Process([
            '/usr/bin/sudo',
            '-n',
            '/bin/bash',
            $script,
            $profile,
            base_path(),
        ]);
        $process->setTimeout(45);
        $process->run();

        if (!$process->isSuccessful()) {
            logger()->warning('Failed to apply DDoS profile via root panel.', [
                'profile' => $profile,
                'exit_code' => $process->getExitCode(),
                'error' => $process->getErrorOutput(),
            ]);
        }
    }

    private function asBoolString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
