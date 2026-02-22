<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Server;
use Symfony\Component\Process\Process;

class NodeSecureModeService
{
    public function __construct(private SecurityEventService $securityEventService)
    {
    }

    public function isSecureModeEnabled(): bool
    {
        return $this->boolSetting('node_secure_mode_enabled', false);
    }

    public function shouldBlockSensitivePath(string $path): bool
    {
        $normalized = strtolower('/' . ltrim($path, '/'));

        foreach ([
            '/.env',
            '/.env.',
            '/.hconfig',
            '/.chconfig',
            '/.dockerenv',
            '/.git/',
            '/config/.env',
            '/public/.env',
            '/storage/.env',
        ] as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    public function inspectRequestPayload(Request $request): array
    {
        if (!$this->isSecureModeEnabled()) {
            return [
                'inspected' => false,
                'findings' => [],
                'quarantined' => false,
            ];
        }

        $path = '/' . ltrim((string) $request->path(), '/');
        if (!$this->isPayloadScanTarget($path, strtoupper((string) $request->method()))) {
            return [
                'inspected' => false,
                'findings' => [],
                'quarantined' => false,
            ];
        }

        $payload = (string) $request->getContent();
        if ($payload === '') {
            return [
                'inspected' => true,
                'findings' => [],
                'quarantined' => false,
            ];
        }

        // Avoid expensive regex scans for very large binary payloads.
        $payload = substr($payload, 0, 250000);
        $findings = $this->scanTextForSecrets($payload);
        $escapeFindings = $this->scanTextForEscapePatterns($payload);

        if (empty($findings) && empty($escapeFindings)) {
            return [
                'inspected' => true,
                'findings' => [],
                'quarantined' => false,
                'block_request' => false,
            ];
        }

        $serverId = $this->extractServerIdFromPath((string) $request->path());
        $discordLeak = collect($findings)->contains(fn (array $row) => $row['type'] === 'discord_token');
        $containerEscapeProbe = !empty($escapeFindings);
        $quarantined = false;
        $blockRequest = false;

        $risk = ($discordLeak || $containerEscapeProbe) ? 'critical' : 'high';
        if ($discordLeak && $serverId !== null && $this->boolSetting('node_secure_discord_quarantine_enabled', true)) {
            $quarantineMinutes = max(5, min(1440, $this->intSetting('node_secure_discord_quarantine_minutes', 30)));
            $this->quarantineServer($serverId, $quarantineMinutes);
            $quarantined = true;
        }
        if ($containerEscapeProbe && $this->boolSetting('node_secure_container_policy_enabled', true)) {
            $blockRequest = true;
        }

        $this->securityEventService->log('security:node.secret_leak.detected', [
            'actor_user_id' => optional($request->user())->id,
            'server_id' => $serverId,
            'ip' => (string) $request->ip(),
            'risk_level' => $risk,
            'meta' => [
                'path' => $path,
                'findings' => $findings,
                'container_escape_findings' => $escapeFindings,
                'quarantined' => $quarantined,
                'block_request' => $blockRequest,
            ],
        ]);

        return [
            'inspected' => true,
            'findings' => array_merge($findings, $escapeFindings),
            'quarantined' => $quarantined,
            'block_request' => $blockRequest,
        ];
    }

    /**
     * Inspect a chat message payload and optionally block, redact, or quarantine.
     */
    public function inspectChatMessage(?string $text, ?int $serverId, ?int $actorUserId, ?string $ip): array
    {
        $value = (string) ($text ?? '');
        if (!$this->isSecureModeEnabled() || trim($value) === '') {
            return [
                'allowed' => true,
                'value' => $text,
                'findings' => [],
                'blocked' => false,
                'quarantined' => false,
            ];
        }

        $findings = $this->scanTextForSecrets($value);
        if (empty($findings)) {
            return [
                'allowed' => true,
                'value' => $text,
                'findings' => [],
                'blocked' => false,
                'quarantined' => false,
            ];
        }

        $discordLeak = collect($findings)->contains(fn (array $row) => $row['type'] === 'discord_token');
        $quarantined = false;
        if ($discordLeak && $serverId !== null && $this->boolSetting('node_secure_discord_quarantine_enabled', true)) {
            $minutes = max(5, min(1440, $this->intSetting('node_secure_discord_quarantine_minutes', 30)));
            $this->quarantineServer($serverId, $minutes);
            $quarantined = true;
        }

        $blocked = $this->boolSetting('node_secure_chat_block_secret', true);
        $sanitized = $blocked ? null : $this->redactTextSecrets($value);

        $this->securityEventService->log('security:node.chat_secret.detected', [
            'actor_user_id' => $actorUserId,
            'server_id' => $serverId,
            'ip' => $ip,
            'risk_level' => $discordLeak ? 'critical' : 'high',
            'meta' => [
                'findings' => $findings,
                'blocked' => $blocked,
                'quarantined' => $quarantined,
            ],
        ]);

        return [
            'allowed' => !$blocked,
            'value' => $sanitized,
            'findings' => $findings,
            'blocked' => $blocked,
            'quarantined' => $quarantined,
        ];
    }

    public function scanTextForSecrets(string $text): array
    {
        $findings = [];

        $patterns = [
            'discord_token' => '/(?<![A-Za-z0-9])[MN][A-Za-z0-9_-]{23}\.[A-Za-z0-9_-]{6}\.[A-Za-z0-9_-]{27}(?![A-Za-z0-9])/m',
            'discord_webhook' => '/https:\/\/(?:canary\.|ptb\.)?discord(?:app)?\.com\/api\/webhooks\/[0-9]{16,20}\/[A-Za-z0-9_-]{30,}/i',
            'generic_secret' => '/(?:DISCORD_TOKEN|BOT_TOKEN|API_KEY|SECRET_KEY|PRIVATE_KEY|ACCESS_TOKEN|AUTH_TOKEN)\s*[:=]\s*["\']?[A-Za-z0-9_\-\.]{16,}/i',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches) > 0 && !empty($matches[0]) && is_array($matches[0])) {
                foreach (array_slice(array_values(array_unique($matches[0])), 0, 5) as $match) {
                    $findings[] = [
                        'type' => $type,
                        'sample' => $this->maskSecret((string) $match),
                    ];
                }
            }
        }

        return $findings;
    }

    public function runNpmAudit(?Server $server = null, ?string $customPath = null): array
    {
        $path = $this->resolveScanPath($server, $customPath);
        $packagePath = rtrim($path, '/') . '/package.json';

        if (!is_file($packagePath)) {
            return [
                'success' => true,
                'skipped' => true,
                'reason' => 'package.json not found',
                'path' => $path,
                'severity' => [],
                'block_deploy' => false,
            ];
        }

        $process = Process::fromShellCommandline('npm audit --json --omit=dev', $path);
        $process->setTimeout(45);

        try {
            $process->run();
        } catch (\Throwable $exception) {
            $this->securityEventService->log('security:node.npm_audit.failed', [
                'server_id' => $server?->id,
                'risk_level' => 'medium',
                'meta' => [
                    'path' => $path,
                    'message' => $exception->getMessage(),
                ],
            ]);

            return [
                'success' => false,
                'skipped' => false,
                'path' => $path,
                'error' => $exception->getMessage(),
                'severity' => [],
                'block_deploy' => false,
            ];
        }

        $raw = trim($process->getOutput());
        if ($raw === '') {
            $raw = trim($process->getErrorOutput());
        }

        $decoded = json_decode($raw, true);
        $severity = [
            'critical' => 0,
            'high' => 0,
            'moderate' => 0,
            'low' => 0,
            'info' => 0,
        ];

        if (is_array($decoded)) {
            $vulns = $decoded['metadata']['vulnerabilities'] ?? [];
            foreach (array_keys($severity) as $level) {
                $severity[$level] = (int) ($vulns[$level] ?? 0);
            }
        }

        $blockOnHigh = $this->boolSetting('node_secure_npm_block_high', true);
        $blockDeploy = $blockOnHigh && (($severity['high'] ?? 0) > 0 || ($severity['critical'] ?? 0) > 0);

        $this->securityEventService->log('security:node.npm_audit.completed', [
            'server_id' => $server?->id,
            'risk_level' => $blockDeploy ? 'high' : 'low',
            'meta' => [
                'path' => $path,
                'severity' => $severity,
                'exit_code' => $process->getExitCode(),
                'block_deploy' => $blockDeploy,
            ],
        ]);

        return [
            'success' => true,
            'skipped' => false,
            'path' => $path,
            'severity' => $severity,
            'exit_code' => $process->getExitCode(),
            'block_deploy' => $blockDeploy,
        ];
    }

    public function runSafeDeployScan(?Server $server = null, ?string $customPath = null): array
    {
        $path = $this->resolveScanPath($server, $customPath);
        $maxFiles = max(20, min(500, $this->intSetting('node_secure_scan_max_files', 180)));
        $maxFileSize = 1024 * 512;

        $patterns = [
            'eval_call' => '/\beval\s*\(/i',
            'exec_call' => '/\bchild_process\s*\.\s*(exec|execSync)\s*\(/i',
            'spawn_shell' => '/\b(child_process\.)?(spawn|spawnSync)\s*\(\s*["\'](?:sh|bash|zsh|cmd|powershell)["\']/i',
            'dynamic_function' => '/\bnew\s+Function\s*\(/i',
        ];

        $warnings = [];
        $scanned = 0;
        $iterator = $this->iterScriptFiles($path);
        foreach ($iterator as $file) {
            if ($scanned >= $maxFiles) {
                break;
            }

            if (!is_file($file) || !is_readable($file)) {
                continue;
            }

            if (filesize($file) > $maxFileSize) {
                continue;
            }

            $content = @file_get_contents($file);
            if (!is_string($content) || $content === '') {
                continue;
            }

            $scanned++;
            foreach ($patterns as $key => $pattern) {
                if (preg_match($pattern, $content) === 1) {
                    $warnings[] = [
                        'type' => $key,
                        'file' => str_replace(rtrim($path, '/') . '/', '', $file),
                    ];
                }
            }

            $secrets = $this->scanTextForSecrets(substr($content, 0, 60000));
            foreach ($secrets as $secretFinding) {
                $warnings[] = [
                    'type' => 'secret_pattern',
                    'file' => str_replace(rtrim($path, '/') . '/', '', $file),
                    'detail' => $secretFinding['type'],
                ];
            }
        }

        $warnings = array_values(array_slice($warnings, 0, 100));

        $this->securityEventService->log('security:node.safe_deploy_scan.completed', [
            'server_id' => $server?->id,
            'risk_level' => empty($warnings) ? 'low' : 'medium',
            'meta' => [
                'path' => $path,
                'scanned_files' => $scanned,
                'warnings_count' => count($warnings),
            ],
        ]);

        return [
            'success' => true,
            'path' => $path,
            'scanned_files' => $scanned,
            'warnings_count' => count($warnings),
            'warnings' => $warnings,
        ];
    }

    /**
     * @throws DisplayException
     */
    public function enforceDeployGate(Server $server): void
    {
        if (!$this->isSecureModeEnabled() || !$this->boolSetting('node_secure_deploy_gate_enabled', true)) {
            return;
        }

        $scan = $this->runSafeDeployScan($server);
        $audit = $this->runNpmAudit($server);
        $blockByAudit = !empty($audit['block_deploy']);
        $criticalPatternBlockEnabled = $this->boolSetting('node_secure_deploy_block_critical_patterns', false);
        $criticalPatternFound = collect($scan['warnings'] ?? [])->contains(function ($row) {
            $type = (string) ($row['type'] ?? '');

            return in_array($type, ['exec_call', 'spawn_shell'], true);
        });

        if ($blockByAudit || ($criticalPatternBlockEnabled && $criticalPatternFound)) {
            $this->securityEventService->log('security:node.deploy_gate.blocked', [
                'server_id' => $server->id,
                'risk_level' => 'high',
                'meta' => [
                    'audit' => $audit,
                    'safe_deploy' => [
                        'warnings_count' => (int) ($scan['warnings_count'] ?? 0),
                        'critical_pattern_found' => $criticalPatternFound,
                    ],
                ],
            ]);

            if ($blockByAudit) {
                throw new DisplayException('Deploy blocked by npm security policy: high/critical vulnerabilities detected.');
            }

            throw new DisplayException('Deploy blocked by safe deploy policy: critical command execution pattern detected.');
        }
    }

    public function ingestRuntimeSample(int $serverId, array $sample): array
    {
        $rss = max(1, (float) ($sample['rss_mb'] ?? 0));
        $heapUsed = max(1, (float) ($sample['heap_used_mb'] ?? 0));
        $heapTotal = max(1, (float) ($sample['heap_total_mb'] ?? 0));
        $gcReclaimed = max(0, (float) ($sample['gc_reclaimed_mb'] ?? 0));

        $entry = [
            'rss_mb' => round($rss, 2),
            'heap_used_mb' => round($heapUsed, 2),
            'heap_total_mb' => round($heapTotal, 2),
            'gc_reclaimed_mb' => round($gcReclaimed, 2),
            'captured_at' => now()->toAtomString(),
        ];

        $key = "node:runtime:samples:{$serverId}";
        $samples = Cache::get($key, []);
        if (!is_array($samples)) {
            $samples = [];
        }

        $samples[] = $entry;
        $samples = array_values(array_slice($samples, -30));
        Cache::put($key, $samples, now()->addHours(24));

        $analysis = $this->analyzeMemoryTrend($samples);
        if (!empty($analysis['possible_memory_leak'])) {
            $this->securityEventService->log('security:node.memory_leak.detected', [
                'server_id' => $serverId,
                'risk_level' => 'high',
                'meta' => [
                    'analysis' => $analysis,
                ],
            ]);
        }

        return [
            'samples' => count($samples),
            'analysis' => $analysis,
        ];
    }

    public function runtimeSummary(int $serverId): array
    {
        $samples = Cache::get("node:runtime:samples:{$serverId}", []);
        if (!is_array($samples)) {
            $samples = [];
        }

        return [
            'server_id' => $serverId,
            'samples' => count($samples),
            'latest' => empty($samples) ? null : end($samples),
            'analysis' => $this->analyzeMemoryTrend($samples),
        ];
    }

    public function securityScore(int $serverId): array
    {
        $windowStart = now()->subDays(7);

        $dependencyIssues = DB::table('security_events')
            ->where('server_id', $serverId)
            ->where('event_type', 'security:node.npm_audit.completed')
            ->where('created_at', '>=', $windowStart)
            ->orderByDesc('id')
            ->limit(1)
            ->get(['meta'])
            ->map(function ($row) {
                $meta = json_decode((string) $row->meta, true);

                return [
                    'critical' => (int) ($meta['severity']['critical'] ?? 0),
                    'high' => (int) ($meta['severity']['high'] ?? 0),
                    'moderate' => (int) ($meta['severity']['moderate'] ?? 0),
                ];
            })
            ->first();

        $dependencyScore = 100;
        if (is_array($dependencyIssues)) {
            $dependencyScore -= min(70, ($dependencyIssues['critical'] * 25) + ($dependencyIssues['high'] * 10) + ($dependencyIssues['moderate'] * 3));
        }

        $secretEvents = DB::table('security_events')
            ->where('server_id', $serverId)
            ->where('event_type', 'security:node.secret_leak.detected')
            ->where('created_at', '>=', $windowStart)
            ->count();
        $secretScore = max(0, 100 - ((int) $secretEvents * 20));

        $memoryEvents = DB::table('security_events')
            ->where('server_id', $serverId)
            ->where('event_type', 'security:node.memory_leak.detected')
            ->where('created_at', '>=', $windowStart)
            ->count();
        $runtimeScore = max(0, 100 - ((int) $memoryEvents * 15));

        $networkEvents = DB::table('security_events')
            ->where('server_id', $serverId)
            ->whereIn('event_type', ['security:node.rate_limit.per_app.hit', 'security:ddos.temp_block'])
            ->where('created_at', '>=', $windowStart)
            ->count();
        $networkScore = max(0, 100 - ((int) $networkEvents * 6));

        $scores = [
            'dependency_security' => max(0, min(100, (int) $dependencyScore)),
            'secret_safety' => max(0, min(100, (int) $secretScore)),
            'runtime_stability' => max(0, min(100, (int) $runtimeScore)),
            'network_exposure' => max(0, min(100, (int) $networkScore)),
        ];

        $total = (int) round(array_sum($scores) / max(1, count($scores)));

        return [
            'server_id' => $serverId,
            'scores' => $scores,
            'total' => $total,
            'grade' => $this->gradeFromScore($total),
        ];
    }

    public function extractServerIdFromPath(string $path): ?int
    {
        if (!preg_match('#/api/client/servers/([a-z0-9-]{8}|[a-z0-9-]{36})(/|$)#i', '/' . ltrim($path, '/'), $match)) {
            return null;
        }

        $identifier = strtolower((string) $match[1]);

        $serverId = Server::query()
            ->where(function ($query) use ($identifier) {
                $query->where('uuid', $identifier)
                    ->orWhere('uuidShort', $identifier);
            })
            ->value('id');

        return $serverId ? (int) $serverId : null;
    }

    public function perAppRateLimits(): array
    {
        return [
            'base' => max(30, min(3000, $this->intSetting('node_secure_per_app_rate_per_minute', 240))),
            'write' => max(10, min(1500, $this->intSetting('node_secure_per_app_write_rate_per_minute', 90))),
        ];
    }

    public function adjustDynamicRateLimit(int $serverId, int $baseLimit): int
    {
        $penalty = (int) Cache::get("node:app:rl:penalty:{$serverId}", 0);
        if ($penalty <= 0) {
            return $baseLimit;
        }

        return max(10, $baseLimit - ($penalty * 20));
    }

    public function raiseRateLimitPenalty(int $serverId): void
    {
        $key = "node:app:rl:penalty:{$serverId}";
        $penalty = min(8, ((int) Cache::get($key, 0)) + 1);
        Cache::put($key, $penalty, now()->addMinutes(15));
    }

    private function isPayloadScanTarget(string $path, string $method): bool
    {
        if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return false;
        }

        return str_contains($path, '/files/write')
            || str_contains($path, '/chat/messages')
            || preg_match('#^/api/client/servers/[a-z0-9-]+/command$#i', $path) === 1;
    }

    /**
     * Detect suspicious patterns commonly used for container escape / host takeover attempts.
     *
     * @return array<int, array{type:string,sample:string}>
     */
    public function scanTextForEscapePatterns(string $text): array
    {
        $findings = [];

        $patterns = [
            'container_escape_privileged' => '/\bprivileged\s*[:=]\s*(true|1|yes)\b/i',
            'container_escape_docker_sock' => '#/var/run/docker\.sock#i',
            'container_escape_cap_sys_admin' => '/\bcap_add\b.{0,80}\b(sys_admin|all)\b/i',
            'container_escape_security_opt' => '/\bsecurity_opt\b.{0,120}\b(apparmor=unconfined|seccomp=unconfined)\b/i',
            'container_escape_nsenter' => '/\bnsenter\b/i',
            'container_escape_runc' => '/\brunc\b/i',
            'container_escape_release_agent' => '/\brelease_agent\b/i',
            'container_escape_mount_host' => '#\bmount\b.{0,80}/(?:proc|sys|etc|root)\b#i',
            'container_escape_hconfig' => '/\.(?:hconfig|chconfig)\b/i',
            'metadata_service_probe' => '/169\.254\.169\.254(?:[:\/]|$)/i',
            'metadata_service_probe_ipv6' => '/fd00:ec2::254(?:[:\/]|$)/i',
            'metadata_user_data_endpoint' => '#/(?:latest/user-data|metadata/v1/user-data|openstack/latest/user_data)\b#i',
            'cloud_init_user_data_path' => '#/var/lib/cloud/(?:instance|instances/[^\s/]+)/user-data(?:\.txt)?#i',
            'cloud_init_config_reference' => '#\bcloud-config\b#i',
            'host_mount_cloud_probe' => '#/mnt/(?:host_var|host_cloud|host_root)\b#i',
            'cloud_init_find_probe' => '/\bfind\s+\/(?:var|mnt)\b.{0,220}\buser-data\*?/is',
            'cloud_init_exec_probe' => '/\b(?:readfilesync|existssync|child_process|exec\s*\(|cat\s+).{0,220}\b(?:user-data(?:\.txt)?|cloud-init|\/var\/lib\/cloud|\/mnt\/host_)\b/is',
            'cloud_init_exec_probe_reverse' => '/\b(?:user-data(?:\.txt)?|cloud-init|\/var\/lib\/cloud|\/mnt\/host_).{0,220}\b(?:readfilesync|existssync|child_process|exec\s*\(|cat\s+)/is',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches) > 0 && !empty($matches[0]) && is_array($matches[0])) {
                foreach (array_slice(array_values(array_unique($matches[0])), 0, 3) as $match) {
                    $findings[] = [
                        'type' => $type,
                        'sample' => $this->maskSecret((string) $match),
                    ];
                }
            }
        }

        return $findings;
    }

    private function quarantineServer(int $serverId, int $minutes): void
    {
        Cache::put("quarantine:server:{$serverId}", true, now()->addMinutes($minutes));

        $list = collect(Cache::get('quarantine:servers:list', []))
            ->map(fn ($id) => (int) $id)
            ->push($serverId)
            ->unique()
            ->values()
            ->all();
        Cache::put('quarantine:servers:list', $list, now()->addDays(2));
    }

    private function analyzeMemoryTrend(array $samples): array
    {
        $count = count($samples);
        if ($count < 6) {
            return [
                'possible_memory_leak' => false,
                'reason' => 'insufficient_samples',
                'growth_percent' => 0,
            ];
        }

        $first = (float) ($samples[0]['heap_used_mb'] ?? 0);
        $last = (float) ($samples[$count - 1]['heap_used_mb'] ?? 0);
        $growthPercent = $first > 0 ? (($last - $first) / $first) * 100 : 0;

        $upwardSteps = 0;
        for ($i = 1; $i < $count; $i++) {
            $prev = (float) ($samples[$i - 1]['heap_used_mb'] ?? 0);
            $curr = (float) ($samples[$i]['heap_used_mb'] ?? 0);
            if ($curr >= ($prev * 1.03)) {
                $upwardSteps++;
            }
        }

        $gcRecovered = 0.0;
        foreach ($samples as $sample) {
            $gcRecovered += (float) ($sample['gc_reclaimed_mb'] ?? 0);
        }

        $possible = $growthPercent >= 35 && $upwardSteps >= max(4, (int) floor($count * 0.6)) && $gcRecovered <= ($last * 0.2);

        return [
            'possible_memory_leak' => $possible,
            'growth_percent' => round($growthPercent, 2),
            'upward_steps' => $upwardSteps,
            'sample_count' => $count,
            'gc_reclaimed_mb' => round($gcRecovered, 2),
        ];
    }

    private function resolveScanPath(?Server $server = null, ?string $customPath = null): string
    {
        $default = $this->defaultScanPath($server);

        if (is_string($customPath) && trim($customPath) !== '') {
            $resolved = realpath(trim($customPath));
            if (!is_string($resolved) || $resolved === '' || !is_dir($resolved)) {
                return $default;
            }

            if ($server && $server->relationLoaded('node') === false) {
                $server->loadMissing('node:id,daemonBase');
            }

            if ($server && $server->node) {
                $daemonBase = realpath((string) $server->node->daemonBase);
                if (!is_string($daemonBase) || $daemonBase === '' || !$this->isPathWithin($daemonBase, $resolved)) {
                    return $default;
                }
            } else {
                $base = realpath(base_path());
                if (!is_string($base) || $base === '' || !$this->isPathWithin($base, $resolved)) {
                    return $default;
                }
            }

            return rtrim($resolved, '/');
        }

        return $default;
    }

    private function defaultScanPath(?Server $server = null): string
    {
        if ($server && $server->relationLoaded('node') === false) {
            $server->loadMissing('node:id,daemonBase');
        }

        if ($server && $server->node) {
            return rtrim((string) $server->node->daemonBase, '/') . '/' . $server->uuid;
        }

        return base_path();
    }

    private function isPathWithin(string $base, string $target): bool
    {
        $base = rtrim($base, DIRECTORY_SEPARATOR);
        $target = rtrim($target, DIRECTORY_SEPARATOR);

        if ($base === $target) {
            return true;
        }

        return str_starts_with($target, $base . DIRECTORY_SEPARATOR);
    }

    private function iterScriptFiles(string $basePath): \Generator
    {
        if (!is_dir($basePath)) {
            return;
        }

        $extensions = ['js', 'mjs', 'cjs', 'ts', 'jsx', 'tsx', 'json', 'env'];
        $flags = \FilesystemIterator::SKIP_DOTS;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($basePath, $flags));

        foreach ($iterator as $fileInfo) {
            $file = (string) $fileInfo;
            if (str_contains($file, '/node_modules/') || str_contains($file, '/.git/')) {
                continue;
            }

            $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
            if ($ext !== '' && !in_array($ext, $extensions, true)) {
                continue;
            }

            yield $file;
        }
    }

    private function gradeFromScore(int $score): string
    {
        return match (true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 83 => 'B',
            $score >= 80 => 'B-',
            $score >= 77 => 'C+',
            $score >= 73 => 'C',
            $score >= 70 => 'C-',
            $score >= 60 => 'D',
            default => 'F',
        };
    }

    private function boolSetting(string $key, bool $default): bool
    {
        $value = Cache::remember("system:{$key}", 30, function () use ($key) {
            return DB::table('system_settings')->where('key', $key)->value('value');
        });

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function intSetting(string $key, int $default): int
    {
        $value = Cache::remember("system:{$key}", 30, function () use ($key) {
            return DB::table('system_settings')->where('key', $key)->value('value');
        });

        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }

    private function maskSecret(string $value): string
    {
        $trimmed = trim($value);
        if (strlen($trimmed) <= 10) {
            return str_repeat('*', strlen($trimmed));
        }

        return substr($trimmed, 0, 4) . str_repeat('*', max(4, strlen($trimmed) - 8)) . substr($trimmed, -4);
    }

    private function redactTextSecrets(string $text): string
    {
        $patterns = [
            '/(?<![A-Za-z0-9])[MN][A-Za-z0-9_-]{23}\.[A-Za-z0-9_-]{6}\.[A-Za-z0-9_-]{27}(?![A-Za-z0-9])/m',
            '/https:\/\/(?:canary\.|ptb\.)?discord(?:app)?\.com\/api\/webhooks\/[0-9]{16,20}\/[A-Za-z0-9_-]{30,}/i',
            '/(?:DISCORD_TOKEN|BOT_TOKEN|API_KEY|SECRET_KEY|PRIVATE_KEY|ACCESS_TOKEN|AUTH_TOKEN)\s*[:=]\s*["\']?[A-Za-z0-9_\-\.]{16,}/i',
        ];

        return (string) preg_replace($patterns, '[REDACTED_SECRET]', $text);
    }
}
