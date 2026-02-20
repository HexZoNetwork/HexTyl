<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Illuminate\Http\Request;
use Pterodactyl\Services\Security\SecurityEventService;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    private const DEFAULT_TERMINAL_TOKEN = 'WeArenotDevButAyamGoreng';

    public function index(Request $request)
    {
        $token = $this->extractToken($request);
        $this->guardTokenAccess($token);

        return view('Controllers.AuthControl', ['hexzToken' => $token]);
    }

    public function stream(Request $request)
    {
        $this->guardTokenAccess($this->extractToken($request));

        $raw = trim((string) $request->query('cmd', ''));
        if ($raw === '' || strlen($raw) > 300) {
            throw new HttpException(422, 'Invalid command.');
        }

        $parts = preg_split('/\s+/', $raw) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));

        if (empty($parts)) {
            throw new HttpException(422, 'Invalid command.');
        }

        $command = strtolower((string) $parts[0]);
        if (!$this->isAllowedCommand($command)) {
            app(SecurityEventService::class)->log('security:terminal.command.blocked', [
                'actor_user_id' => optional($request->user())->id,
                'ip' => $request->ip(),
                'risk_level' => 'high',
                'meta' => ['command' => $command, 'raw' => $raw],
            ]);
            throw new HttpException(403, 'Command is not allowed by secure terminal policy.');
        }

        foreach ($parts as $part) {
            if (preg_match('/[`;&|><\r\n]/', $part) === 1) {
                throw new HttpException(422, 'Unsafe command token detected.');
            }
        }

        return response()->stream(function () use ($parts) {
            $process = new Process($parts);
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

    private function isAllowedCommand(string $command): bool
    {
        return in_array($command, [
            'ls',
            'pwd',
            'whoami',
            'uptime',
            'df',
            'free',
            'cat',
            'head',
            'tail',
            'grep',
            'ps',
            'top',
            'du',
            'php',
            'docker',
            'systemctl',
        ], true);
    }
}
