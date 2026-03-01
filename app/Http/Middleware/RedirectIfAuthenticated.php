<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Security\SecurityEventService;

class RedirectIfAuthenticated
{
    /**
     * RedirectIfAuthenticated constructor.
     */
    public function __construct(private AuthManager $authManager)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, \Closure $next, ?string $guard = null): mixed
    {
        if ($this->authManager->guard($guard)->check()) {
            $this->trackAuthenticatedAuthProbe($request);

            return redirect()->route('index');
        }

        return $next($request);
    }

    private function trackAuthenticatedAuthProbe(Request $request): void
    {
        $path = '/' . ltrim((string) $request->path(), '/');
        if (!Str::startsWith($path, '/auth/')) {
            return;
        }

        $userId = (int) ($request->user()?->id ?? 0);
        $ip = (string) $request->ip();
        $ua = substr((string) $request->userAgent(), 0, 180);
        $fingerprint = sha1("{$userId}|{$ip}|{$ua}");

        $secondWindow = (int) floor(microtime(true));
        $burstKey = "security:auth_probe:{$fingerprint}:{$secondWindow}";
        Cache::add($burstKey, 0, 3);
        $count = (int) Cache::increment($burstKey);
        Cache::put($burstKey, $count, 3);

        $threshold = max(5, (int) config('ddos.auth_probe_threshold_1s', 100));
        if ($count < $threshold) {
            return;
        }

        $blockMinutes = max(1, (int) config('ddos.temporary_block_minutes', 10));
        Cache::put("ddos:ban:{$ip}", true, now()->addMinutes($blockMinutes));
        Cache::put("ddos:temp_block:{$ip}", true, now()->addMinutes($blockMinutes));
        Cache::put("security:cloudflare:challenge:{$ip}", true, now()->addMinutes(5));

        app(SecurityEventService::class)->log('security:auth.login_probe.blocked', [
            'actor_user_id' => $userId > 0 ? $userId : null,
            'ip' => $ip,
            'risk_level' => 'high',
            'meta' => [
                'path' => $path,
                'hits_1s' => $count,
                'threshold_1s' => $threshold,
                'block_minutes' => $blockMinutes,
            ],
        ]);
    }
}
