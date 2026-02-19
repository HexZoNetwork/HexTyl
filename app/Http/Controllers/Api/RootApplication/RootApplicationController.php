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
            ],
        ]);
    }

    public function setSecuritySetting(Request $request, GlobalMaintenanceService $maintenanceService): JsonResponse
    {
        $data = $request->validate([
            'panic_mode' => 'nullable|boolean',
            'maintenance_mode' => 'nullable|boolean',
            'maintenance_message' => 'nullable|string|max:255',
            'silent_defense_mode' => 'nullable|boolean',
            'kill_switch_mode' => 'nullable|boolean',
            'kill_switch_whitelist_ips' => 'nullable|string|max:3000',
        ]);

        if (array_key_exists('maintenance_mode', $data)) {
            if ($data['maintenance_mode']) {
                $maintenanceService->enable($data['maintenance_message'] ?? 'System Maintenance');
            } else {
                $maintenanceService->disable();
            }
        }

        foreach (['panic_mode', 'silent_defense_mode', 'kill_switch_mode'] as $boolKey) {
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

        foreach ([
            'system:panic_mode',
            'system:maintenance_mode',
            'system:maintenance_message',
            'system:silent_defense_mode',
            'system:kill_switch_mode',
            'system:kill_switch_whitelist',
        ] as $cacheKey) {
            Cache::forget($cacheKey);
        }

        return response()->json([
            'success' => true,
            'message' => 'Security setting updated.',
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
