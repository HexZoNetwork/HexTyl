<?php

namespace Pterodactyl\Http\Controllers\Admin\Nodes;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Services\Nodes\NodeBootstrapPayloadService;
use Symfony\Component\Process\Process;

class NodeBootstrapController extends Controller
{
    public function __construct(private NodeBootstrapPayloadService $payloadService)
    {
    }

    public function __invoke(Request $request, Node $node): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.\-:]+$/i'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:64', 'regex:/^[a-z_][a-z0-9_.-]*$/i'],
            'auth_type' => ['required', 'in:password,private_key'],
            'password' => ['nullable', 'string', 'max:255'],
            'private_key' => ['nullable', 'string', 'min:80', 'max:20000'],
            'strict_host_key' => ['nullable', 'boolean'],
        ]);

        $payload = $this->payloadService->forUserAndNode($request->user(), $node);
        $script = (string) ($payload['bootstrap_script'] ?? '');
        if ($script === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to generate bootstrap script.',
            ], 500);
        }

        $host = (string) $data['host'];
        $port = (int) ($data['port'] ?? 22);
        $username = (string) $data['username'];
        $strictHostKey = (bool) ($data['strict_host_key'] ?? false);
        $authType = (string) $data['auth_type'];
        $userId = (int) optional($request->user())->id;

        if ($this->isRateLimited($node->id, $userId, $request->ip())) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Too many bootstrap attempts. Please wait a few minutes.',
            ], 429);
        }

        try {
            $this->assertHostIsSafe($host);
        } catch (ValidationException $exception) {
            $firstError = collect($exception->errors())->flatten()->first();
            return new JsonResponse([
                'success' => false,
                'message' => is_string($firstError) && $firstError !== '' ? $firstError : 'Bootstrap target blocked by safety policy.',
            ], 422);
        }

        $keyPath = null;
        $askPassPath = null;
        try {
            [$command, $preflight, $processEnv, $askPassPath] = $this->buildSshCommand(
                $authType,
                $host,
                $port,
                $username,
                (string) ($data['password'] ?? ''),
                (string) ($data['private_key'] ?? ''),
                $strictHostKey,
                $keyPath
            );

            if ($preflight !== null) {
                return $preflight;
            }

            $process = new Process($command);
            $process->setEnv($processEnv);
            $process->setInput($script);
            $process->setTimeout(900);
            $process->setIdleTimeout(120);
            $process->run();

            $stdout = Str::limit(trim((string) $process->getOutput()), 12000, "\n... [truncated]");
            $stderr = Str::limit(trim((string) $process->getErrorOutput()), 12000, "\n... [truncated]");

            if (!$process->isSuccessful()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'SSH bootstrap failed.',
                    'exit_code' => $process->getExitCode(),
                    'stdout' => $stdout,
                    'stderr' => $stderr,
                ], 422);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Node bootstrap executed successfully.',
                'stdout' => $stdout,
                'stderr' => $stderr,
            ]);
        } finally {
            if (is_string($keyPath) && $keyPath !== '' && is_file($keyPath)) {
                @unlink($keyPath);
            }
            if (is_string($askPassPath) && $askPassPath !== '' && is_file($askPassPath)) {
                @unlink($askPassPath);
            }
        }
    }

    private function buildSshCommand(
        string $authType,
        string $host,
        int $port,
        string $username,
        string $password,
        string $privateKey,
        bool $strictHostKey,
        ?string &$keyPath
    ): array {
        $env = [];
        $base = ['ssh'];
        if (!$strictHostKey) {
            $base = array_merge($base, [
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
            ]);
        }

        $base = array_merge($base, [
            '-o', 'ConnectTimeout=20',
            '-o', 'ServerAliveInterval=20',
            '-o', 'ServerAliveCountMax=3',
            '-p', (string) $port,
        ]);

        if ($authType === 'private_key') {
            $keyPath = tempnam(sys_get_temp_dir(), 'node_bootstrap_key_');
            if (!is_string($keyPath) || $keyPath === '') {
                return [[], new JsonResponse([
                    'success' => false,
                    'message' => 'Unable to allocate temporary key file.',
                ], 500), [], null];
            }

            file_put_contents($keyPath, $privateKey);
            chmod($keyPath, 0600);

            $base = array_merge($base, ['-i', $keyPath]);
        }

        $base[] = sprintf('%s@%s', $username, $host);
        $base[] = 'bash -s';

        if ($authType === 'password') {
            $probe = new Process(['sshpass', '-V']);
            $probe->setTimeout(10);
            $probe->run();
            if ($password === '') {
                return [[], new JsonResponse([
                    'success' => false,
                    'message' => 'Password is required for password auth.',
                ], 422), [], null];
            }

            // If sshpass is unavailable, fallback to SSH_ASKPASS flow (still backend-side, not browser-side).
            if ($probe->isSuccessful()) {
                $base = array_merge(['sshpass', '-p', $password], $base);
            } else {
                $askPassPath = tempnam(sys_get_temp_dir(), 'node_bootstrap_askpass_');
                if (!is_string($askPassPath) || $askPassPath === '') {
                    return [[], new JsonResponse([
                        'success' => false,
                        'message' => 'Unable to allocate temporary askpass helper.',
                    ], 500), [], null];
                }

                file_put_contents($askPassPath, "#!/usr/bin/env bash\nexec printf '%s\\n' \"\$HEXWINGS_SSH_PASSWORD\"\n");
                chmod($askPassPath, 0700);

                $env = [
                    'SSH_ASKPASS' => $askPassPath,
                    'SSH_ASKPASS_REQUIRE' => 'force',
                    'DISPLAY' => ':0',
                    'HEXWINGS_SSH_PASSWORD' => $password,
                ];

                $base = array_merge($base, [
                    '-o', 'PreferredAuthentications=password',
                    '-o', 'PubkeyAuthentication=no',
                    '-o', 'NumberOfPasswordPrompts=1',
                ]);
            }
        }

        return [$base, null, $env, $askPassPath ?? null];
    }

    private function assertHostIsSafe(string $host): void
    {
        $allowPrivateTargets = (bool) config('wings_security.bootstrap.allow_private_targets', true);
        $ips = $this->resolveHostIps($host);
        if ($ips === []) {
            throw ValidationException::withMessages([
                'host' => ['Unable to resolve host IP for bootstrap target.'],
            ]);
        }

        foreach ($ips as $ip) {
            if ($this->isUnsafeIpTarget($ip, $allowPrivateTargets)) {
                throw ValidationException::withMessages([
                    'host' => ['Bootstrap target is blocked by safety policy.'],
                ]);
            }
        }
    }

    private function resolveHostIps(string $host): array
    {
        $sanitized = trim($host);
        if ($sanitized === '') {
            return [];
        }

        if (str_contains($sanitized, ':') && str_contains($sanitized, ']')) {
            $sanitized = trim($sanitized, '[]');
        }

        if (filter_var($sanitized, FILTER_VALIDATE_IP)) {
            return [$sanitized];
        }

        $resolved = @gethostbynamel($sanitized) ?: [];

        return array_values(array_unique(array_filter($resolved, fn ($ip) => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP))));
    }

    private function isUnsafeIpTarget(string $ip, bool $allowPrivateTargets): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        $flags = FILTER_FLAG_NO_RES_RANGE;
        if (!$allowPrivateTargets) {
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
    }

    private function isRateLimited(int $nodeId, int $userId, ?string $ip): bool
    {
        $windowSeconds = 600;
        $maxAttempts = 6;
        $suffix = implode(':', [$nodeId, $userId, (string) $ip]);
        $key = 'security:node_bootstrap:attempts:' . sha1($suffix);

        Cache::add($key, 0, now()->addSeconds($windowSeconds));
        $attempts = (int) Cache::increment($key);
        Cache::put($key, $attempts, now()->addSeconds($windowSeconds));

        return $attempts > $maxAttempts;
    }
}
