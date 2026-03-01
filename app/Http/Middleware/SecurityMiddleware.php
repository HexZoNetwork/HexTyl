<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Services\Security\BehavioralScoreService;
use Pterodactyl\Services\Security\NodeSecureModeService;
use Pterodactyl\Services\Security\ProgressiveSecurityModeService;
use Pterodactyl\Services\Security\SecurityEventService;
use Pterodactyl\Services\Security\SilentDefenseService;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SecurityMiddleware
{
    /**
     * Request-scoped settings memo to avoid repeated cache/disk reads.
     *
     * @var array<string, string>
     */
    private array $settingsMemo = [];
    private bool $resolvedApiKeyLoaded = false;
    private ?ApiKey $resolvedApiKey = null;

    public function __construct(
        private BehavioralScoreService $riskService,
        private SilentDefenseService $silentDefenseService,
        private ProgressiveSecurityModeService $progressiveSecurityModeService,
        private NodeSecureModeService $nodeSecureModeService,
    )
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $path = $request->path();
        $this->progressiveSecurityModeService->evaluateSystemMode();

        if ($this->shouldBlockContainerLateralRequest($request)) {
            app(SecurityEventService::class)->log('security:container_lateral_guard.blocked', [
                'actor_user_id' => optional($request->user())->id,
                'ip' => $ip,
                'risk_level' => 'high',
                'meta' => [
                    'path' => '/' . ltrim($path, '/'),
                    'method' => strtoupper((string) $request->method()),
                    'ua' => substr((string) $request->userAgent(), 0, 180),
                ],
            ]);
            throw new HttpException(403, 'Blocked by internal lateral request guard.');
        }

        if ($this->shouldBypassDdosProtection($request)) {
            return $next($request);
        }

        $this->enforceInfrastructurePressureGuard($request);

        if ($this->nodeSecureModeService->isSecureModeEnabled() && $this->nodeSecureModeService->shouldBlockSensitivePath($path)) {
            app(SecurityEventService::class)->log('security:node.dotenv.protected', [
                'actor_user_id' => optional($request->user())->id,
                'ip' => $ip,
                'risk_level' => 'high',
                'meta' => ['path' => '/' . ltrim($path, '/')],
            ]);
            throw new HttpException(403, 'Access to sensitive file paths is blocked.');
        }

        if ($this->isDdosBanned($ip)) {
            $window = (int) floor(time() / 60);
            $this->logEventOnce(
                "security:ddos.banned_hit:{$ip}:{$window}",
                'security:ddos.banned_request_blocked',
                [
                    'actor_user_id' => optional($request->user())->id,
                    'ip' => $ip,
                    'risk_level' => 'high',
                    'meta' => [
                        'path' => '/' . ltrim($path, '/'),
                        'method' => strtoupper((string) $request->method()),
                    ],
                ],
                90
            );
            throw new HttpException(403, 'Access temporarily blocked by anti-DDoS policy.');
        }

        if ($this->isDdosLockdownBlocking($request)) {
            $window = (int) floor(time() / 60);
            $this->logEventOnce(
                "security:ddos.lockdown_hit:{$ip}:{$window}",
                'security:ddos.lockdown_request_blocked',
                [
                    'actor_user_id' => optional($request->user())->id,
                    'ip' => $ip,
                    'risk_level' => 'medium',
                    'meta' => [
                        'path' => '/' . ltrim($path, '/'),
                        'method' => strtoupper((string) $request->method()),
                    ],
                ],
                90
            );
            throw new HttpException(503, 'Temporarily restricted by security policy.');
        }

        $this->enforceAdaptiveRateLimit($request);

        if ($this->isKillSwitchBlocking($ip, $path)) {
            $window = (int) floor(time() / 60);
            $this->logEventOnce(
                "security:killswitch_hit:{$ip}:{$window}",
                'security:kill_switch.request_blocked',
                [
                    'actor_user_id' => optional($request->user())->id,
                    'ip' => $ip,
                    'risk_level' => 'high',
                    'meta' => [
                        'path' => '/' . ltrim($path, '/'),
                        'method' => strtoupper((string) $request->method()),
                    ],
                ],
                90
            );
            throw new HttpException(503, 'API temporarily unavailable.');
        }

        if ($this->isQuarantinedServerRequest($path)) {
            usleep(1500000);
            throw new HttpException(429, 'Request rate is temporarily limited.');
        }

        $payloadInspection = $this->nodeSecureModeService->inspectRequestPayload($request);
        if (($payloadInspection['block_request'] ?? false) === true) {
            throw new HttpException(403, 'Blocked by node secure container policy.');
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
        if (str_starts_with($path, 'api/remote')) {
            return false;
        }

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
        if (Str::startsWith($path, 'api/remote')) {
            return;
        }
        $method = strtoupper((string) $request->method());

        // Wings <-> Panel remote API traffic is already authenticated by daemon credentials
        // and can spike during mass server operations. Do not apply generic panel throttles here.
        if (Str::startsWith($path, 'api/remote')) {
            return;
        }

        $resolvedApiKey = $this->resolveBearerApiKey($request);
        $token = $request->user()?->currentAccessToken();
        $isRootKeyRequest = ($token instanceof ApiKey && $token->isRootKey())
            || ($resolvedApiKey instanceof ApiKey && $resolvedApiKey->isRootKey());
        $isRootApplicationPath = Str::startsWith($path, 'api/rootapplication');

        // API requests with a valid API key should be governed by Laravel route limiter
        // (api.application / api.client) and not by aggressive adaptive guest heuristics.
        if ($resolvedApiKey instanceof ApiKey && Str::startsWith($path, ['api/application', 'api/client'])) {
            return;
        }

        $skipAuthenticatedLimits = filter_var(
            $this->settingValue(
                'ddos_skip_authenticated_limits',
                config('ddos.skip_authenticated_limits', false) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (
            $skipAuthenticatedLimits
            && ($request->user() !== null || $resolvedApiKey instanceof ApiKey)
            && !$isRootApplicationPath
            && !$isRootKeyRequest
        ) {
            return;
        }

        $this->detectDirectIpHostFlood($request, $ip, $path, $method);
        $this->detectDirectIpPortProbe($request, $ip, $path, $method);
        $this->detectSuspiciousUnauthenticatedFlood($request, $ip, $path, $method);
        $this->enforceHardBurstRateLimit($request, $ip, $path, $method);

        $bucket = 'web';
        $limit = (int) $this->settingValue('ddos_rate_web_per_minute', config('ddos.rate_limits.web_per_minute', 180));

        if (Str::startsWith($path, ['auth/login', 'auth/login/totp'])) {
            $bucket = 'login';
            $limit = (int) $this->settingValue('ddos_rate_login_per_minute', config('ddos.rate_limits.login_per_minute', 20));
        } elseif (Str::startsWith($path, 'api/application')) {
            $bucket = 'ptla';
            $limit = (int) $this->settingValue('api_rate_limit_ptla_per_period', (string) config('http.rate_limit.application', 256));
        } elseif (Str::startsWith($path, 'api/client')) {
            $bucket = 'ptlc';
            $limit = (int) $this->settingValue('api_rate_limit_ptlc_per_period', (string) config('http.rate_limit.client', 256));
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
            $this->applyDdosBan($ip, 'burst_threshold', [
                'burst' => $burst,
                'burst_threshold' => $burstThreshold,
                'path' => $path,
            ], true);
            throw new HttpException(403, 'Access temporarily blocked by anti-DDoS policy.');
        }

        $repeatPathThreshold = (int) $this->settingValue(
            'ddos_repeat_path_threshold_10s',
            config('ddos.repeat_path_threshold_10s', 80)
        );
        if ($repeatPathThreshold > 0 && $this->shouldEscalateRepeatPathBan($request, $path, $method)) {
            $pathWindow = (int) floor(time() / 10);
            $pathHash = md5($path);
            $pathKey = "ddos:path:{$ip}:{$pathHash}:{$pathWindow}";
            Cache::add($pathKey, 0, 20);
            $pathHits = (int) Cache::increment($pathKey);
            Cache::put($pathKey, $pathHits, 20);

            if ($pathHits > $repeatPathThreshold) {
                $this->applyDdosBan($ip, 'repeat_path', [
                    'path' => $path,
                    'hits_10s' => $pathHits,
                    'threshold_10s' => $repeatPathThreshold,
                ]);
                throw new HttpException(403, 'Access temporarily blocked by anti-DDoS policy.');
            }
        }

        if ($count > $limit) {
            $this->recordRateLimitViolation($request, $ip, $bucket, $path, $count, $limit);
            throw new HttpException(429, 'Rate limited.');
        }

        $this->enforcePerAppRateLimit($request, $ip, $path, $method);

        if ($count > (int) floor($limit * 0.85)) {
            usleep(180000);
        }
    }

    private function enforceHardBurstRateLimit(Request $request, string $ip, string $path, string $method): void
    {
        $threshold = (int) $this->settingValue(
            'ddos_hard_burst_threshold_1s',
            config('ddos.hard_burst_threshold_1s', 100)
        );
        if ($threshold < 1) {
            return;
        }

        $window = (int) floor(microtime(true));
        $key = "ddos:burst:1s:{$ip}:{$window}";
        Cache::add($key, 0, 3);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 3);

        if ($count <= $threshold) {
            return;
        }

        $this->applyDdosBan($ip, 'hard_burst_1s', [
            'path' => $path,
            'method' => $method,
            'hits_1s' => $count,
            'threshold_1s' => $threshold,
        ], true);

        throw new HttpException(429, 'Too many requests.');
    }

    private function enforcePerAppRateLimit(Request $request, string $ip, string $path, string $method): void
    {
        if (!$this->nodeSecureModeService->isSecureModeEnabled()) {
            return;
        }

        $serverId = $this->nodeSecureModeService->extractServerIdFromPath($path);
        if ($serverId === null) {
            return;
        }

        $limits = $this->nodeSecureModeService->perAppRateLimits();
        $baseLimit = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $limits['write'] : $limits['base'];
        $limit = $this->nodeSecureModeService->adjustDynamicRateLimit($serverId, $baseLimit);

        $window = now()->format('YmdHi');
        $key = "node:app:rl:{$serverId}:{$ip}:{$window}";
        Cache::add($key, 0, 120);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 120);

        if ($count <= $limit) {
            return;
        }

        $this->nodeSecureModeService->raiseRateLimitPenalty($serverId);
        app(SecurityEventService::class)->log('security:node.rate_limit.per_app.hit', [
            'actor_user_id' => optional($request->user())->id,
            'server_id' => $serverId,
            'ip' => $ip,
            'risk_level' => $count >= ($limit * 2) ? 'high' : 'medium',
            'meta' => [
                'path' => '/' . ltrim($path, '/'),
                'method' => $method,
                'count' => $count,
                'limit' => $limit,
            ],
        ]);

        throw new HttpException(429, 'Per-app rate limited.');
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
        if (Str::startsWith($path, '/api/remote')) {
            return false;
        }

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
            if (str_contains($entry, '/') && IpUtils::checkIp($ip, $entry)) {
                return true;
            }
        }

        return false;
    }

    private function isDdosBanned(string $ip): bool
    {
        return Cache::has("ddos:ban:{$ip}") || Cache::has("ddos:temp_block:{$ip}");
    }

    private function activateAutoUnderAttackIfNeeded(string $triggeringIp): void
    {
        $enabled = filter_var(
            $this->settingValue(
                'ddos_auto_under_attack_enabled',
                config('ddos.auto_under_attack.enabled', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled) {
            return;
        }

        $lockdownEnabled = filter_var(
            $this->settingValue('ddos_lockdown_mode', config('ddos.lockdown_mode', false) ? 'true' : 'false'),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($lockdownEnabled) {
            return;
        }

        $signalWindow = (int) floor(time() / 30);
        $signalKey = "ddos:auto_under_attack:signal:{$signalWindow}";
        Cache::add($signalKey, 0, 45);
        $signalCount = (int) Cache::increment($signalKey);
        Cache::put($signalKey, $signalCount, 45);

        $triggerThreshold = (int) $this->settingValue(
            'ddos_auto_under_attack_trigger_30s',
            config('ddos.auto_under_attack.trigger_30s', 25)
        );
        if ($signalCount < max(1, $triggerThreshold)) {
            return;
        }

        $cooldownMinutes = (int) $this->settingValue(
            'ddos_auto_under_attack_cooldown_minutes',
            config('ddos.auto_under_attack.cooldown_minutes', 15)
        );
        $cooldownExpiresAt = now()->addMinutes(max(1, $cooldownMinutes));
        if (!Cache::add('ddos:auto_under_attack:cooldown', true, $cooldownExpiresAt)) {
            return;
        }

        $ddosWhitelistRaw = (string) $this->settingValue('ddos_whitelist_ips', config('ddos.whitelist_ips', ''));
        $killSwitchWhitelistRaw = (string) $this->settingValue('kill_switch_whitelist_ips', '');
        $whitelist = $this->normalizedWhitelist(array_merge(
            ['127.0.0.1', '::1'],
            $this->explodeWhitelist($ddosWhitelistRaw),
            $this->explodeWhitelist($killSwitchWhitelistRaw)
        ));
        if ($whitelist === []) {
            $whitelist = ['127.0.0.1', '::1'];
        }

        while (strlen(implode(',', $whitelist)) > 3000 && count($whitelist) > 2) {
            array_pop($whitelist);
        }
        if (strlen(implode(',', $whitelist)) > 3000) {
            $whitelist = ['127.0.0.1', '::1'];
        }

        $now = now();
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'ddos_whitelist_ips'],
            ['value' => implode(',', $whitelist), 'created_at' => $now, 'updated_at' => $now]
        );
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'ddos_lockdown_mode'],
            ['value' => 'true', 'created_at' => $now, 'updated_at' => $now]
        );
        Cache::forget('system:ddos_whitelist_ips');
        Cache::forget('system:ddos_lockdown_mode');

        app(SecurityEventService::class)->log('security:ddos.auto_under_attack', [
            'ip' => $triggeringIp,
            'risk_level' => 'critical',
            'meta' => [
                'signal_count_30s' => $signalCount,
                'trigger_threshold_30s' => max(1, $triggerThreshold),
            ],
        ]);
    }

    private function recordRateLimitViolation(Request $request, string $ip, string $bucket, string $path, int $count, int $limit): void
    {
        app(SecurityEventService::class)->log('security:rate_limit.hit', [
            'actor_user_id' => optional($request->user())->id,
            'ip' => $ip,
            'risk_level' => 'medium',
            'meta' => [
                'bucket' => $bucket,
                'path' => $path,
                'count' => $count,
                'limit' => $limit,
            ],
        ]);

        $isWrite = str_contains($bucket, ':write');
        $isLogin = str_starts_with($bucket, 'login');
        $isApi = str_starts_with($bucket, 'api');
        $isDocumentation = $this->isDocumentationPath($path);
        $isAuthenticated = $request->user() !== null;

        // Keep normal panel browsing on 429 only; escalate to ban for clearly abusive patterns.
        if (!$isLogin && !$isWrite && !($isApi && !$isAuthenticated) && !($isDocumentation && !$isAuthenticated)) {
            return;
        }

        $window = (int) floor(time() / 300);
        $key = "ddos:violation:{$ip}:{$window}";
        Cache::add($key, 0, 360);
        $violations = (int) Cache::increment($key);
        Cache::put($key, $violations, 360);

        $violationThreshold = (int) $this->settingValue(
            'ddos_violation_threshold_5m',
            config('ddos.violation_threshold_5m', 3)
        );

        if ($violations >= max(1, $violationThreshold)) {
            $this->applyDdosBan($ip, 'repeated_rate_limit_violations', [
                'bucket' => $bucket,
                'path' => $path,
                'count' => $count,
                'limit' => $limit,
                'violations_5m' => $violations,
                'threshold_5m' => max(1, $violationThreshold),
            ]);
        }
    }

    private function applyDdosBan(string $ip, string $reason, array $meta = [], bool $triggerAutoUnderAttack = false): void
    {
        $minutes = (int) $this->settingValue('ddos_temp_block_minutes', config('ddos.temporary_block_minutes', 10));
        Cache::put("ddos:ban:{$ip}", true, now()->addMinutes(max(1, $minutes)));
        Cache::put("ddos:temp_block:{$ip}", true, now()->addMinutes(max(1, $minutes)));
        $this->blockIpAtFirewall($ip, max(1, $minutes));
        $this->riskService->incrementRisk($ip, 'spam_api');
        if ($triggerAutoUnderAttack) {
            $this->activateAutoUnderAttackIfNeeded($ip);
        }

        app(SecurityEventService::class)->log('security:ddos.temp_block', [
            'ip' => $ip,
            'risk_level' => 'high',
            'meta' => array_merge([
                'reason' => $reason,
                'block_minutes' => max(1, $minutes),
            ], $meta),
        ]);
    }

    private function blockIpAtFirewall(string $ip, int $minutes): void
    {
        $enabled = filter_var(
            $this->settingValue(
                'ddos_firewall_block_enabled',
                config('ddos.firewall_block_enabled', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return;
        }

        if (!$this->canRunElevatedFirewallCommand()) {
            return;
        }

        $seconds = max(60, min(86400, $minutes * 60));
        $process = new Process([
            'sudo',
            '-n',
            'nft',
            'add',
            'element',
            'inet',
            'hextyl_ddos',
            'blocklist',
            "{ {$ip} timeout {$seconds}s }",
        ]);
        $process->setTimeout(5);
        $process->run();

        if ($process->isSuccessful()) {
            return;
        }

        $stderr = trim((string) $process->getErrorOutput());
        if (str_contains(strtolower($stderr), 'no such file') || str_contains(strtolower($stderr), 'no such table')) {
            $bootstrap = new Process(['sudo', '-n', 'nft', '-f', '/etc/nftables.d/hextyl-ddos.nft']);
            $bootstrap->setTimeout(5);
            $bootstrap->run();
            if ($bootstrap->isSuccessful()) {
                $retry = new Process([
                    'sudo',
                    '-n',
                    'nft',
                    'add',
                    'element',
                    'inet',
                    'hextyl_ddos',
                    'blocklist',
                    "{ {$ip} timeout {$seconds}s }",
                ]);
                $retry->setTimeout(5);
                $retry->run();
                if ($retry->isSuccessful()) {
                    return;
                }
                $stderr = trim((string) $retry->getErrorOutput());
            }
        }

        app(SecurityEventService::class)->log('security:ddos.firewall_block_failed', [
            'ip' => $ip,
            'risk_level' => 'high',
            'meta' => [
                'minutes' => $minutes,
                'stderr' => $stderr,
            ],
        ]);
    }

    private function canRunElevatedFirewallCommand(): bool
    {
        static $canRun = null;
        if ($canRun !== null) {
            return $canRun;
        }

        $check = Process::fromShellCommandline('sudo -n true');
        $check->setTimeout(3);
        $check->run();
        $canRun = $check->isSuccessful();

        return $canRun;
    }

    private function shouldBypassDdosProtection(Request $request): bool
    {
        $ip = (string) $request->ip();
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        $whitelistRaw = (string) $this->settingValue('ddos_whitelist_ips', config('ddos.whitelist_ips', ''));
        $whitelist = $this->explodeWhitelist($whitelistRaw);

        return $this->ipMatchesWhitelist($ip, $whitelist);
    }

    private function shouldBlockContainerLateralRequest(Request $request): bool
    {
        $enabled = filter_var(
            $this->settingValue(
                'ddos_container_lateral_guard_enabled',
                config('ddos.container_lateral_guard.enabled', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled) {
            return false;
        }

        $path = '/' . ltrim((string) $request->path(), '/');
        $isRootApplicationPath = Str::startsWith($path, '/api/rootapplication');
        $isAuthPath = Str::startsWith($path, ['/auth/login', '/auth/login/totp']);
        $isPanelApiPath = Str::startsWith($path, ['/api/client', '/api/application']);

        if (!$isRootApplicationPath && !$isAuthPath && !$isPanelApiPath) {
            return false;
        }

        // Never block remote node API lane from this guard.
        if (Str::startsWith($path, '/api/remote')) {
            return false;
        }

        $ip = (string) $request->ip();
        if ($ip === '' || $ip === '127.0.0.1' || $ip === '::1') {
            return false;
        }

        $whitelist = array_merge(
            ['127.0.0.1', '::1'],
            $this->explodeWhitelist((string) $this->settingValue(
                'ddos_container_lateral_guard_whitelist_ips',
                (string) config('ddos.container_lateral_guard.whitelist_ips', '')
            )),
            $this->explodeWhitelist((string) $this->settingValue('ddos_whitelist_ips', config('ddos.whitelist_ips', '')))
        );
        if ($this->ipMatchesWhitelist($ip, $whitelist)) {
            return false;
        }

        $cidrs = $this->explodeWhitelist((string) $this->settingValue(
            'ddos_container_lateral_guard_cidrs',
            (string) config('ddos.container_lateral_guard.cidrs', '172.16.0.0/12,100.64.0.0/10')
        ));
        $matchesContainerCidr = false;
        foreach ($cidrs as $cidr) {
            if (str_contains($cidr, '/') && IpUtils::checkIp($ip, $cidr)) {
                $matchesContainerCidr = true;
                break;
            }
        }
        if (!$matchesContainerCidr) {
            return false;
        }

        $blockRootApplication = filter_var(
            $this->settingValue(
                'ddos_container_lateral_guard_block_rootapplication',
                config('ddos.container_lateral_guard.block_rootapplication', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($isRootApplicationPath && $blockRootApplication) {
            return true;
        }

        $blockAuth = filter_var(
            $this->settingValue(
                'ddos_container_lateral_guard_block_auth',
                config('ddos.container_lateral_guard.block_auth', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($isAuthPath && $blockAuth) {
            return true;
        }

        if (!$isPanelApiPath) {
            return false;
        }

        $allowValidPanelApiKeys = filter_var(
            $this->settingValue(
                'ddos_container_lateral_guard_allow_valid_panel_api_keys',
                config('ddos.container_lateral_guard.allow_valid_panel_api_keys', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        $resolvedApiKey = $this->resolveBearerApiKey($request);
        if (
            $allowValidPanelApiKeys
            && $resolvedApiKey instanceof ApiKey
            && in_array($resolvedApiKey->key_type, [ApiKey::TYPE_APPLICATION, ApiKey::TYPE_ACCOUNT], true)
        ) {
            return false;
        }

        $authorization = trim((string) $request->header('Authorization', ''));
        $hasBearer = str_starts_with($authorization, 'Bearer ');
        $ua = strtolower(trim((string) $request->header('User-Agent', '')));
        $suspiciousUa = $ua === ''
            || preg_match('/(curl|wget|python|go-http-client|axios|node-fetch|okhttp|powershell|libwww-perl)/i', $ua) === 1;
        $isWriteMethod = in_array(strtoupper((string) $request->method()), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        // Fallback block rule for container-origin panel API without valid PTLA/PTLC token.
        return $hasBearer || $suspiciousUa || $isWriteMethod;
    }

    private function logEventOnce(string $key, string $eventType, array $payload, int $ttlSeconds = 60): void
    {
        if (!Cache::add($key, 1, now()->addSeconds(max(1, $ttlSeconds)))) {
            return;
        }

        app(SecurityEventService::class)->log($eventType, $payload);
    }

    private function isSensitivePathForDdos(string $path): bool
    {
        return Str::startsWith($path, ['api/', 'auth/login', 'admin/'])
            || $this->isDocumentationPath($path);
    }

    private function isDocumentationPath(string $path): bool
    {
        $normalized = '/' . ltrim($path, '/');

        return in_array($normalized, ['/doc', '/documentation'], true);
    }

    private function detectDirectIpHostFlood(Request $request, string $ip, string $path, string $method): void
    {
        $enabled = filter_var(
            $this->settingValue(
                'ddos_direct_ip_host_protection_enabled',
                config('ddos.direct_ip_host_protection.enabled', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled) {
            return;
        }

        if ($request->user() !== null) {
            return;
        }

        if (!$this->isDirectIpHostRequest($request)) {
            return;
        }

        $window = (int) floor(time() / 30);
        $key = "ddos:direct_ip_host:{$ip}:{$window}";
        Cache::add($key, 0, 45);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 45);

        $threshold = (int) $this->settingValue(
            'ddos_direct_ip_host_threshold_30s',
            config('ddos.direct_ip_host_protection.threshold_30s', 12)
        );
        if ($count < max(1, $threshold)) {
            return;
        }

        $this->applyDdosBan($ip, 'direct_ip_host_flood', [
            'path' => $path,
            'method' => $method,
            'host' => (string) $request->getHost(),
            'hits_30s' => $count,
            'threshold_30s' => max(1, $threshold),
        ], true);

        throw new HttpException(403, 'Access temporarily blocked by anti-DDoS policy.');
    }

    private function detectDirectIpPortProbe(Request $request, string $ip, string $path, string $method): void
    {
        $enabled = filter_var(
            $this->settingValue(
                'ddos_direct_ip_host_port_protection_enabled',
                config('ddos.direct_ip_host_protection.port_probe.enabled', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled) {
            return;
        }

        if (!$this->isDirectIpHostRequest($request) || !$this->isDirectIpWithExplicitPort($request)) {
            return;
        }

        $isSensitivePath = $this->isSensitivePathForDdos($path);
        if ($request->user() !== null && !$isSensitivePath) {
            return;
        }

        $window = (int) floor(time() / 10);
        $key = "ddos:direct_ip_port:{$ip}:{$window}";
        Cache::add($key, 0, 20);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 20);

        $threshold = (int) $this->settingValue(
            'ddos_direct_ip_host_port_threshold_10s',
            config('ddos.direct_ip_host_protection.port_probe.threshold_10s', 4)
        );
        $threshold = max(1, $threshold);

        if ($count < $threshold) {
            return;
        }

        $this->applyDdosBan($ip, 'direct_ip_port_probe', [
            'path' => $path,
            'method' => $method,
            'host' => (string) $request->getHost(),
            'hits_10s' => $count,
            'threshold_10s' => $threshold,
        ], true);

        throw new HttpException(403, 'Access temporarily blocked by anti-DDoS policy.');
    }

    private function isDirectIpHostRequest(Request $request): bool
    {
        $host = trim((string) $request->getHost());
        if ($host === '') {
            return false;
        }

        $normalizedHost = trim($host, '[]');
        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $appHost = trim((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        if ($appHost !== '' && strcasecmp($normalizedHost, trim($appHost, '[]')) === 0) {
            return false;
        }

        return true;
    }

    private function isDirectIpWithExplicitPort(Request $request): bool
    {
        $host = trim((string) $request->getHttpHost());
        if ($host === '') {
            return false;
        }

        $port = null;
        if (preg_match('/^\[(.+)\]:(\d{1,5})$/', $host, $matches) === 1) {
            $port = (int) $matches[2];
        } elseif (preg_match('/^([^:]+):(\d{1,5})$/', $host, $matches) === 1) {
            $port = (int) $matches[2];
        }

        if ($port === null || $port < 1 || $port > 65535) {
            return false;
        }

        return !in_array($port, [80, 443], true);
    }

    private function enforceInfrastructurePressureGuard(Request $request): void
    {
        $enabled = filter_var(
            $this->settingValue(
                'ddos_infra_pressure_guard_enabled',
                config('ddos.infrastructure_pressure_guard.enabled', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled) {
            return;
        }

        $snapshot = $this->infrastructurePressureSnapshot();
        if (!$snapshot['pressure']) {
            return;
        }

        // Keep daemon lane alive to avoid control-plane deadlocks with Wings.
        $path = '/' . ltrim((string) $request->path(), '/');
        if (Str::startsWith($path, '/api/remote')) {
            return;
        }

        $isSensitive = Str::startsWith($path, ['/api/', '/auth/login', '/admin/']);
        if (!$isSensitive) {
            return;
        }

        if ($request->user()?->isRoot()) {
            return;
        }

        $ip = (string) $request->ip();
        $window = (int) floor(time() / 5);
        $counterKey = "security:infra_pressure:block_counter:{$ip}:{$window}";
        Cache::add($counterKey, 0, 10);
        $count = (int) Cache::increment($counterKey);
        Cache::put($counterKey, $count, 10);

        if ($request->user() === null && $count >= 5) {
            $this->applyDdosBan($ip, 'infra_pressure_hot_source', [
                'path' => $path,
                'count_5s' => $count,
                'reasons' => $snapshot['reasons'],
            ], true);
        }

        $windowMinute = (int) floor(time() / 60);
        $this->logEventOnce(
            "security:infra_pressure:block_log:{$ip}:{$windowMinute}",
            'security:infra_pressure.request_blocked',
            [
                'actor_user_id' => optional($request->user())->id,
                'ip' => $ip,
                'risk_level' => 'high',
                'meta' => [
                    'path' => $path,
                    'method' => strtoupper((string) $request->method()),
                    'reasons' => $snapshot['reasons'],
                    'metrics' => $snapshot['metrics'],
                    'count_5s' => $count,
                ],
            ],
            75
        );

        throw new HttpException(503, 'Temporarily restricted by infrastructure pressure guard.');
    }

    private function infrastructurePressureSnapshot(): array
    {
        $activeKey = 'security:infra_pressure:active';
        $active = Cache::get($activeKey);
        if (is_array($active) && ($active['pressure'] ?? false) === true) {
            return $active;
        }

        $sample = Cache::remember('security:infra_pressure:sample', 2, function () {
            $reasons = [];
            $metrics = [
                'load_per_core_1m' => null,
                'memory_used_percent' => null,
                'disk_used_percent' => null,
            ];

            $loadPerCore = $this->loadPerCore();
            if ($loadPerCore !== null) {
                $metrics['load_per_core_1m'] = round($loadPerCore, 3);
                $loadThreshold = (float) $this->settingValue(
                    'ddos_infra_load_per_core_threshold',
                    (string) config('ddos.infrastructure_pressure_guard.load_per_core_threshold', 1.8)
                );
                if ($loadPerCore >= max(0.1, $loadThreshold)) {
                    $reasons[] = 'cpu_load_pressure';
                }
            }

            $memoryUsed = $this->memoryUsedPercent();
            if ($memoryUsed !== null) {
                $metrics['memory_used_percent'] = round($memoryUsed, 2);
                $memoryThreshold = (float) $this->settingValue(
                    'ddos_infra_memory_used_percent_threshold',
                    (string) config('ddos.infrastructure_pressure_guard.memory_used_percent_threshold', 92)
                );
                if ($memoryUsed >= max(1, $memoryThreshold)) {
                    $reasons[] = 'memory_pressure';
                }
            }

            $diskUsed = $this->diskUsedPercent(base_path());
            if ($diskUsed !== null) {
                $metrics['disk_used_percent'] = round($diskUsed, 2);
                $diskThreshold = (float) $this->settingValue(
                    'ddos_infra_disk_used_percent_threshold',
                    (string) config('ddos.infrastructure_pressure_guard.disk_used_percent_threshold', 95)
                );
                if ($diskUsed >= max(1, $diskThreshold)) {
                    $reasons[] = 'disk_pressure';
                }
            }

            return [
                'pressure' => $reasons !== [],
                'reasons' => array_values(array_unique($reasons)),
                'metrics' => $metrics,
            ];
        });

        if (!is_array($sample)) {
            return ['pressure' => false, 'reasons' => [], 'metrics' => []];
        }

        if (($sample['pressure'] ?? false) === true) {
            $holdSeconds = (int) $this->settingValue(
                'ddos_infra_pressure_hold_seconds',
                (string) config('ddos.infrastructure_pressure_guard.hold_seconds', 45)
            );
            Cache::put($activeKey, $sample, now()->addSeconds(max(5, $holdSeconds)));
            $minuteWindow = (int) floor(time() / 60);
            $this->logEventOnce(
                "security:infra_pressure:detected:{$minuteWindow}",
                'security:infra_pressure.detected',
                [
                    'risk_level' => 'high',
                    'meta' => [
                        'reasons' => (array) ($sample['reasons'] ?? []),
                        'metrics' => (array) ($sample['metrics'] ?? []),
                    ],
                ],
                75
            );
        }

        return $sample;
    }

    private function loadPerCore(): ?float
    {
        if (!function_exists('sys_getloadavg')) {
            return null;
        }
        $loads = @sys_getloadavg();
        if (!is_array($loads) || !isset($loads[0])) {
            return null;
        }

        $cores = (int) Cache::remember('security:infra_pressure:cpu_cores', 3600, function () {
            $content = @file_get_contents('/proc/cpuinfo');
            if (!is_string($content) || $content === '') {
                return 1;
            }

            $count = preg_match_all('/^processor\s*:/m', $content);

            return max(1, (int) $count);
        });

        return ((float) $loads[0]) / max(1, $cores);
    }

    private function memoryUsedPercent(): ?float
    {
        $content = @file_get_contents('/proc/meminfo');
        if (!is_string($content) || $content === '') {
            return null;
        }

        if (preg_match('/^MemTotal:\s+(\d+)/m', $content, $totalMatches) !== 1) {
            return null;
        }

        $total = (float) $totalMatches[1];
        if ($total <= 0) {
            return null;
        }

        $available = null;
        if (preg_match('/^MemAvailable:\s+(\d+)/m', $content, $availableMatches) === 1) {
            $available = (float) $availableMatches[1];
        } elseif (preg_match('/^MemFree:\s+(\d+)/m', $content, $freeMatches) === 1) {
            $available = (float) $freeMatches[1];
        }

        if ($available === null) {
            return null;
        }

        return max(0.0, min(100.0, (($total - $available) / $total) * 100));
    }

    private function diskUsedPercent(string $path): ?float
    {
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        if (!is_numeric($total) || !is_numeric($free) || (float) $total <= 0) {
            return null;
        }

        return max(0.0, min(100.0, (((float) $total - (float) $free) / (float) $total) * 100));
    }

    private function detectSuspiciousUnauthenticatedFlood(Request $request, string $ip, string $path, string $method): void
    {
        if (!$this->isSensitivePathForDdos($path)) {
            return;
        }

        if ($request->user() !== null) {
            return;
        }

        if (!$this->isSuspiciousHeaderFingerprint($request, $method)) {
            return;
        }

        $window = (int) floor(time() / 30);
        $key = "ddos:suspicious_headers:{$ip}:{$window}";
        Cache::add($key, 0, 45);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 45);

        $threshold = (int) $this->settingValue(
            'ddos_suspicious_header_threshold_30s',
            config('ddos.suspicious_header_threshold_30s', 20)
        );
        if ($count < max(1, $threshold)) {
            return;
        }

        $this->applyDdosBan($ip, 'suspicious_unauthenticated_header_flood', [
            'path' => $path,
            'method' => $method,
            'suspicious_count_30s' => $count,
            'threshold_30s' => max(1, $threshold),
        ], true);

        throw new HttpException(403, 'Access temporarily blocked by anti-DDoS policy.');
    }

    private function isSuspiciousHeaderFingerprint(Request $request, string $method): bool
    {
        $authorization = trim((string) $request->header('Authorization', ''));
        $apiKey = trim((string) $request->header('X-API-Key', ''));
        if ($authorization !== '' || $apiKey !== '') {
            return false;
        }

        $ua = trim((string) $request->header('User-Agent', ''));
        $accept = trim((string) $request->header('Accept', ''));
        $contentType = trim((string) $request->header('Content-Type', ''));

        if ($ua === '' || strlen($ua) < 8) {
            return true;
        }

        if (preg_match('/(curl|wget|python|go-http-client|libwww-perl|httpclient|scrapy|sqlmap)/i', $ua) === 1) {
            return true;
        }

        if ($accept === '' && $method !== 'OPTIONS') {
            return true;
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $contentType === '') {
            return true;
        }

        return false;
    }

    private function shouldEscalateRepeatPathBan(Request $request, string $path, string $method): bool
    {
        if (!$this->isSensitivePathForDdos($path)) {
            return false;
        }

        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $isLogin = Str::startsWith($path, ['auth/login', 'auth/login/totp']);
        $isApi = Str::startsWith($path, 'api/');
        $isAuthenticated = $request->user() !== null;

        if ($isLogin || $isWrite) {
            return true;
        }

        // Authenticated API GET bursts are often from normal dashboard multi-tab behavior.
        if ($isApi && $isAuthenticated) {
            return false;
        }

        return true;
    }

    private function explodeWhitelist(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $entry) => trim($entry))
            ->filter()
            ->values()
            ->all();
    }

    private function normalizedWhitelist(array $entries): array
    {
        $entries = array_values(array_unique(array_map('trim', $entries)));

        return array_values(array_filter($entries, fn (string $entry) => $this->isValidWhitelistEntry($entry)));
    }

    private function isValidWhitelistEntry(string $entry): bool
    {
        if ($entry === '*') {
            return true;
        }

        if (str_contains($entry, '/')) {
            return $this->isValidCidr($entry);
        }

        return filter_var($entry, FILTER_VALIDATE_IP) !== false;
    }

    private function isValidCidr(string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $network = trim($parts[0]);
        $prefix = trim($parts[1]);
        if ($network === '' || $prefix === '' || !ctype_digit($prefix)) {
            return false;
        }

        $prefixInt = (int) $prefix;
        if (filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $prefixInt >= 0 && $prefixInt <= 32;
        }

        if (filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $prefixInt >= 0 && $prefixInt <= 128;
        }

        return false;
    }

    private function settingValue(string $key, string|int|bool $default): string
    {
        if (array_key_exists($key, $this->settingsMemo)) {
            return $this->settingsMemo[$key];
        }

        $cacheKey = "system:{$key}";
        $value = (string) Cache::remember($cacheKey, 30, function () use ($key, $default) {
            $value = DB::table('system_settings')->where('key', $key)->value('value');
            if ($value === null || $value === '') {
                return (string) $default;
            }

            return (string) $value;
        });

        $this->settingsMemo[$key] = $value;

        return $value;
    }

    private function trackWriteBurstBehavior(Request $request): void
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        if (!str_contains($request->path(), '/files/write')) {
            return;
        }

        if (!preg_match('#/api/client/servers/([a-z0-9-]{8}|[a-z0-9-]{36})/#i', '/' . ltrim($request->path(), '/'), $match)) {
            return;
        }

        $identifier = strtolower($match[1]);
        $server = Server::query()
            ->select(['id', 'uuid', 'uuidShort'])
            ->where(function ($query) use ($identifier) {
                $query->where('uuid', $identifier)
                    ->orWhere('uuidShort', $identifier);
            })
            ->first();
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
        if (!preg_match('#/api/client/servers/([a-z0-9-]{8}|[a-z0-9-]{36})(/|$)#i', '/' . ltrim($path, '/'), $match)) {
            return false;
        }

        $identifier = strtolower($match[1]);
        $serverId = Server::query()
            ->where(function ($query) use ($identifier) {
                $query->where('uuid', $identifier)
                    ->orWhere('uuidShort', $identifier);
            })
            ->value('id');
        if (!$serverId) {
            return false;
        }

        return Cache::has("quarantine:server:{$serverId}");
    }

    private function resolveBearerApiKey(Request $request): ?ApiKey
    {
        if ($this->resolvedApiKeyLoaded) {
            return $this->resolvedApiKey;
        }
        $this->resolvedApiKeyLoaded = true;

        $auth = trim((string) $request->header('Authorization', ''));
        if (!str_starts_with($auth, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($auth, 7));
        if ($token === '') {
            return null;
        }

        try {
            $this->resolvedApiKey = ApiKey::findToken($token);
        } catch (\Throwable) {
            $this->resolvedApiKey = null;
        }

        return $this->resolvedApiKey;
    }
}
