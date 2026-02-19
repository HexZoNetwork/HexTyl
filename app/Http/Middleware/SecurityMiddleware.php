<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Services\Security\BehavioralScoreService;
use Pterodactyl\Services\Security\ProgressiveSecurityModeService;
use Pterodactyl\Services\Security\SecurityEventService;
use Pterodactyl\Services\Security\SilentDefenseService;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecurityMiddleware
{
    public function __construct(
        private BehavioralScoreService $riskService,
        private SilentDefenseService $silentDefenseService,
        private ProgressiveSecurityModeService $progressiveSecurityModeService,
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $path = $request->path();
        $this->progressiveSecurityModeService->evaluateSystemMode();

        if ($this->isDdosTempBlocked($ip)) {
            throw new HttpException(429, 'Too many requests.');
        }

        if ($this->isDdosLockdownBlocking($request)) {
            throw new HttpException(503, 'Temporarily restricted by security policy.');
        }

        $this->enforceAdaptiveRateLimit($request);

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
            Cache::put("security:auto_ban:{$ip}", true, now()->addMinutes(30));
            app(SecurityEventService::class)->log('security:auto_ban.triggered', [
                'ip' => $ip,
                'risk_level' => 'critical',
                'meta' => ['path' => $path],
            ]);

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

    private function enforceAdaptiveRateLimit(Request $request): void
    {
        $ip = (string) $request->ip();
        $path = (string) $request->path();
        $method = strtoupper((string) $request->method());

        $bucket = 'web';
        $limit = (int) $this->settingValue('ddos_rate_web_per_minute', config('ddos.rate_limits.web_per_minute', 180));

        if (Str::startsWith($path, ['auth/login', 'auth/login/totp'])) {
            $bucket = 'login';
            $limit = (int) $this->settingValue('ddos_rate_login_per_minute', config('ddos.rate_limits.login_per_minute', 20));
        } elseif (Str::startsWith($path, 'api/')) {
            $bucket = 'api';
            $limit = (int) $this->settingValue('ddos_rate_api_per_minute', config('ddos.rate_limits.api_per_minute', 120));
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $writeLimit = (int) $this->settingValue('ddos_rate_write_per_minute', config('ddos.rate_limits.write_per_minute', 40));
            $limit = min($limit, $writeLimit);
            $bucket .= ':write';
        }

        $window = now()->format('YmdHi');
        $key = "ddos:rl:{$bucket}:{$ip}:{$window}";
        Cache::add($key, 0, 120);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 120);

        // Lightweight burst tracking over 10s to auto-temp-block obvious floods.
        $burstWindow = (int) floor(time() / 10);
        $burstKey = "ddos:burst:{$ip}:{$burstWindow}";
        Cache::add($burstKey, 0, 20);
        $burst = (int) Cache::increment($burstKey);
        Cache::put($burstKey, $burst, 20);

        $burstThreshold = (int) $this->settingValue('ddos_burst_threshold_10s', config('ddos.burst_threshold_10s', 150));
        if ($burst > $burstThreshold) {
            $minutes = (int) $this->settingValue('ddos_temp_block_minutes', config('ddos.temporary_block_minutes', 10));
            Cache::put("ddos:temp_block:{$ip}", true, now()->addMinutes(max(1, $minutes)));
            $this->riskService->incrementRisk($ip, 'spam_api');
            throw new HttpException(429, 'Rate limited.');
        }

        if ($count > $limit) {
            throw new HttpException(429, 'Rate limited.');
        }

        if ($count > (int) floor($limit * 0.85)) {
            usleep(180000);
        }
    }

    private function isDdosLockdownBlocking(Request $request): bool
    {
        $enabled = filter_var(
            $this->settingValue('ddos_lockdown_mode', config('ddos.lockdown_mode', false) ? 'true' : 'false'),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$enabled) {
            return false;
        }

        $path = '/' . ltrim((string) $request->path(), '/');
        $guarded = Str::startsWith($path, ['/api/', '/auth/login', '/admin/']);
        if (!$guarded) {
            return false;
        }

        $whitelistRaw = (string) $this->settingValue('ddos_whitelist_ips', config('ddos.whitelist_ips', ''));
        $whitelist = collect(explode(',', $whitelistRaw))
            ->map(fn (string $v) => trim($v))
            ->filter()
            ->all();

        return !$this->ipMatchesWhitelist((string) $request->ip(), $whitelist);
    }

    private function ipMatchesWhitelist(string $ip, array $whitelist): bool
    {
        foreach ($whitelist as $entry) {
            if ($entry === '*' || $entry === $ip) {
                return true;
            }
            if (str_contains($entry, '/') && $this->ipv4InCidr($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private function ipv4InCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }
        [$subnet, $maskBits] = explode('/', $cidr, 2);
        if (!is_numeric($maskBits)) {
            return false;
        }
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($ipLong === false || $subnetLong === false) {
            return false;
        }
        $maskBits = (int) $maskBits;
        if ($maskBits < 0 || $maskBits > 32) {
            return false;
        }

        $mask = $maskBits === 0 ? 0 : (-1 << (32 - $maskBits));
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private function isDdosTempBlocked(string $ip): bool
    {
        return Cache::has("ddos:temp_block:{$ip}");
    }

    private function settingValue(string $key, string|int|bool $default): string
    {
        $cacheKey = "system:{$key}";
        return (string) Cache::remember($cacheKey, 30, function () use ($key, $default) {
            $value = DB::table('system_settings')->where('key', $key)->value('value');
            if ($value === null || $value === '') {
                return (string) $default;
            }

            return (string) $value;
        });
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
