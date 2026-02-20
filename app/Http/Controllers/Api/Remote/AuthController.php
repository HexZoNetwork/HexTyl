<?php

namespace Pterodactyl\Http\Controllers\Api\Remote;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Security\SecurityEventService;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\Process\Process;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthController extends Controller
{
    public function index(Request $request)
    {
        $this->guardRootAccess($request);

        return view('Controllers.AuthControl');
    }

    public function stream(Request $request)
    {
        $this->guardRootAccess($request);

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
                'actor_user_id' => $request->user()->id,
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

    private function guardRootAccess(Request $request): void
    {
        $enabled = filter_var(
            (string) Cache::remember('system:ide_connect_enabled', 30, function () {
                return (string) (DB::table('system_settings')->where('key', 'ide_connect_enabled')->value('value') ?? 'false');
            }),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$request->user() || !$request->user()->isRoot() || !$enabled) {
            throw new AccessDeniedHttpException('Secure terminal access is restricted.');
        }
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
