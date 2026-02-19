<?php

namespace Pterodactyl\Http\Controllers\Root;

use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Location;
use Pterodactyl\Models\Nest;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\ServerReputation;
use Pterodactyl\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Maintenance\GlobalMaintenanceService;
use Pterodactyl\Services\Servers\ServerReputationService;
use Pterodactyl\Services\Testing\AbuseSimulationService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        ];

        return view('root.dashboard', compact('stats'));
    }

    /** Root Users Management */
    public function users(Request $request)
    {
        $this->requireRoot($request);
        $users = User::withCount(['servers'])->orderBy('id')->paginate(50);
        return view('root.users', compact('users'));
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

        $servers = $query->paginate(50)->appends($request->query());

        $reputationService = app(ServerReputationService::class);
        foreach ($servers as $server) {
            if (!$server->reputation || !$server->reputation->last_calculated_at || $server->reputation->last_calculated_at->lt(now()->subHour())) {
                $server->setRelation('reputation', $reputationService->recalculate($server));
            }
        }

        return view('root.servers', compact('servers', 'minTrust'));
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
        return redirect()->route('root.api_keys')->with('success', 'API key revoked.');
    }

    /** Suspend / unsuspend a user */
    public function toggleUserSuspension(Request $request, User $user)
    {
        $this->requireRoot($request);
        $user->update(['suspended' => !$user->suspended]);
        return redirect()->route('root.users')->with('success', 'User suspension state toggled.');
    }

    /** Force delete a server */
    public function deleteServer(Request $request, Server $server)
    {
        $this->requireRoot($request);
        $server->delete();
        return redirect()->route('root.servers')->with('success', 'Server deleted.');
    }

    public function security(Request $request)
    {
        $this->requireRoot($request);

        $settings = [
            'maintenance_mode' => $this->boolSetting('maintenance_mode'),
            'panic_mode' => $this->boolSetting('panic_mode'),
            'silent_defense_mode' => $this->boolSetting('silent_defense_mode'),
            'kill_switch_mode' => $this->boolSetting('kill_switch_mode'),
            'kill_switch_whitelist_ips' => (string) DB::table('system_settings')->where('key', 'kill_switch_whitelist_ips')->value('value'),
            'maintenance_message' => (string) DB::table('system_settings')->where('key', 'maintenance_message')->value('value'),
        ];

        $topRisk = collect();
        $reputationStats = [
            'avg_trust' => round((float) ServerReputation::query()->avg('trust_score'), 1),
            'low_trust' => ServerReputation::query()->where('trust_score', '<', 40)->count(),
            'high_trust' => ServerReputation::query()->where('trust_score', '>=', 80)->count(),
        ];

        return view('root.security', compact('settings', 'reputationStats', 'topRisk'));
    }

    public function updateSecuritySettings(Request $request, GlobalMaintenanceService $maintenanceService)
    {
        $this->requireRoot($request);

        $data = $request->validate([
            'maintenance_mode' => 'nullable|boolean',
            'panic_mode' => 'nullable|boolean',
            'silent_defense_mode' => 'nullable|boolean',
            'kill_switch_mode' => 'nullable|boolean',
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
        $this->setSetting('kill_switch_whitelist_ips', trim((string) ($data['kill_switch_whitelist_ips'] ?? '')));
        $this->setSetting('maintenance_message', trim((string) ($data['maintenance_message'] ?? 'System Maintenance')));

        Cache::forget('system:panic_mode');
        Cache::forget('system:silent_defense_mode');
        Cache::forget('system:kill_switch_mode');
        Cache::forget('system:kill_switch_whitelist');
        Cache::forget('system:maintenance_mode');
        Cache::forget('system:maintenance_message');

        return redirect()->route('root.security')->with('success', 'Security settings updated.');
    }

    public function simulateAbuse(Request $request, AbuseSimulationService $simulationService)
    {
        $this->requireRoot($request);
        $requests = max(1, min(1000, (int) $request->input('requests', 100)));
        $result = $simulationService->simulateHttpFlood($requests);

        return redirect()->route('root.security')->with(
            'success',
            "Abuse simulation completed. Success: {$result['success']}, Failed: {$result['failed']}."
        );
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

    private function asBoolString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
