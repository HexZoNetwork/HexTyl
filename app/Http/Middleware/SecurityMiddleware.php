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

        if ($this->shouldBypassDdosProtection($request)) {
            return $next($request);
        }

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
            throw new HttpException(403, 'Access temporarily blocked by anti-DDoS policy.');
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

        $this->nodeSecureModeService->inspectRequestPayload($request);
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

        // Root master keys bypass adaptive API throttling on all API surfaces.
        $token = $request->user()?->currentAccessToken();
        if ($token instanceof ApiKey && $token->isRootKey()) {
            return;
        }

        // PTLR (root application API) should not be throttled by adaptive DDoS limits.
        if (Str::startsWith($path, 'api/rootapplication')) {
            return;
        }

        $skipAuthenticatedLimits = filter_var(
            $this->settingValue(
                'ddos_skip_authenticated_limits',
                config('ddos.skip_authenticated_limits', true) ? 'true' : 'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
        if ($skipAuthenticatedLimits && $request->user() !== null) {
            return;
        }

        $this->detectDirectIpHostFlood($request, $ip, $path, $method);
        $this->detectSuspiciousUnauthenticatedFlood($request, $ip, $path, $method);

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

        $user = $request->user();
        if ($user && $user->isRoot()) {
            return true;
        }

        $whitelistRaw = (string) $this->settingValue('ddos_whitelist_ips', config('ddos.whitelist_ips', ''));
        $whitelist = $this->explodeWhitelist($whitelistRaw);

        return $this->ipMatchesWhitelist($ip, $whitelist);
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
            return IpUtils::checkIp('127.0.0.1', $entry) || IpUtils::checkIp('::1', $entry);
        }

        return filter_var($entry, FILTER_VALIDATE_IP) !== false;
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
}
