<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Services\Security\BehavioralScoreService;
use Pterodactyl\Services\Security\SilentDefenseService;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecurityMiddleware
{
    public function __construct(
        private BehavioralScoreService $riskService,
        private SilentDefenseService $silentDefenseService,
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $path = $request->path();

        if ($this->isKillSwitchBlocking($ip, $path)) {
            throw new HttpException(503, 'API temporarily unavailable.');
        }

        if ($this->isQuarantinedServerRequest($path)) {
            usleep(1500000);
            throw new HttpException(429, 'Request rate is temporarily limited.');
        }

        $this->trackWriteBurstBehavior($request);

        $restriction = $this->riskService->getRestrictionLevel($ip);
        $delaySeconds = $this->silentDefenseService->checkDelay($request);

        if ($delaySeconds > 0) {
            usleep($delaySeconds * 1000000);
        }

        if ($restriction === 'block') {
            // Silent defense: do not reveal hard blocks when enabled.
            if ($this->silentDefenseService->isEnabled()) {
                usleep(2000000);
            } else {
                throw new HttpException(403, 'Access denied.');
            }
        }

        if ($restriction === 'throttle_heavy') {
            usleep(1000000);
        } elseif ($restriction === 'throttle_light') {
            usleep(300000);
        }

        return $next($request);
    }

    private function isKillSwitchBlocking(string $ip, string $path): bool
    {
        if (!str_starts_with($path, 'api/')) {
            return false;
        }

        $enabled = Cache::remember('system:kill_switch_mode', 30, function () {
            $value = DB::table('system_settings')->where('key', 'kill_switch_mode')->value('value');

            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        });

        if (!$enabled) {
            return false;
        }

        $whitelistRaw = Cache::remember('system:kill_switch_whitelist', 30, function () {
            return DB::table('system_settings')->where('key', 'kill_switch_whitelist_ips')->value('value') ?? '';
        });
        $whitelist = collect(explode(',', $whitelistRaw))
            ->map(fn (string $v) => trim($v))
            ->filter()
            ->all();

        return !in_array($ip, $whitelist, true);
    }

    private function trackWriteBurstBehavior(Request $request): void
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (!str_contains($request->path(), '/files/write')) {
            return;
        }

        if (!preg_match('#/api/client/servers/([a-z0-9-]{36})/#i', '/' . ltrim($request->path(), '/'), $match)) {
            return;
        }

        $server = Server::query()->select(['id', 'uuid'])->where('uuid', $match[1])->first();
        if (!$server) {
            return;
        }

        $size = strlen((string) $request->getContent());
        $secondKey = now()->format('YmdHis');
        $baseKey = "write_burst:server:{$server->id}:{$secondKey}";
        $counter = (int) Cache::increment("{$baseKey}:count");
        Cache::put("{$baseKey}:count", $counter, 120);

        $signature = $size > 0 ? $size : -1;
        $same = (int) Cache::increment("{$baseKey}:size:{$signature}");
        Cache::put("{$baseKey}:size:{$signature}", $same, 120);

        if ($counter < 100) {
            return;
        }

        $sameRatio = $counter > 0 ? ($same / $counter) : 0;
        if ($sameRatio >= 0.5) {
            Cache::put("quarantine:server:{$server->id}", true, now()->addMinutes(30));
            $list = collect(Cache::get('quarantine:servers:list', []))
                ->map(fn ($id) => (int) $id)
                ->push($server->id)
                ->unique()
                ->values()
                ->all();
            Cache::put('quarantine:servers:list', $list, now()->addDays(1));
            $this->riskService->incrementRisk($request->ip(), 'spam_api');
        }
    }

    private function isQuarantinedServerRequest(string $path): bool
    {
        if (!preg_match('#/api/client/servers/([a-z0-9-]{36})(/|$)#i', '/' . ltrim($path, '/'), $match)) {
            return false;
        }

        $serverId = Server::query()->where('uuid', $match[1])->value('id');
        if (!$serverId) {
            return false;
        }

        return Cache::has("quarantine:server:{$serverId}");
    }
}
