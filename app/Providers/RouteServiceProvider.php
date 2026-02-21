<?php

namespace Pterodactyl\Providers;

use Illuminate\Http\Request;
use Pterodactyl\Models\Database;
use Pterodactyl\Models\ApiKey;
use Pterodactyl\Enum\ResourceLimit;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Pterodactyl\Http\Middleware\TrimStrings;
use Pterodactyl\Http\Middleware\AdminAuthenticate;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    protected const FILE_PATH_REGEX = '/^\/api\/client\/servers\/([a-z0-9-]{36})\/files(\/?$|\/(.)*$)/i';

    /**
     * Define your route model bindings, pattern filters, etc.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        // Disable trimming string values when requesting file information — it isn't helpful
        // and messes up the ability to actually open a directory that ends with a space.
        TrimStrings::skipWhen(function (Request $request) {
            return preg_match(self::FILE_PATH_REGEX, $request->getPathInfo()) === 1;
        });

        // This is needed to make use of the "resolveRouteBinding" functionality in the
        // model. Without it you'll never trigger that logic flow thus resulting in a 404
        // error because we request databases with a HashID, and not with a normal ID.
        Route::model('database', Database::class);

        $this->routes(function () {
            Route::middleware('web')->group(function () {
                // Public token-gated shell route.
                Route::group([], base_path('routes/FirewallControl.php'));

                // Root panel — root-only enforcement inside RootPanelController
                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, 'admin'])
                    ->group(base_path('routes/root.php'));

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class])
                    ->group(base_path('routes/base.php'));

                Route::middleware(['auth.session', RequireTwoFactorAuthentication::class, 'admin', 'admin.read_only'])
                    ->prefix('/admin')
                    ->group(base_path('routes/admin.php'));

                Route::middleware('guest')->prefix('/auth')->group(base_path('routes/auth.php'));
            });

            Route::middleware(['api', RequireTwoFactorAuthentication::class])->group(function () {
                Route::middleware(['application-api', 'throttle:api.application'])
                    ->prefix('/api/application')
                    ->scopeBindings()
                    ->group(base_path('routes/api-application.php'));

                Route::middleware(['client-api', 'throttle:api.client'])
                    ->prefix('/api/client')
                    ->scopeBindings()
                    ->group(base_path('routes/api-client.php'));
            });

            Route::middleware(['api', RequireTwoFactorAuthentication::class, 'root.api', 'root.api.write_guard', 'throttle:api.rootapplication'])
                ->prefix('/api/rootapplication')
                ->scopeBindings()
                ->group(base_path('routes/api-rootapplication.php'));

            Route::middleware(['daemon', 'throttle:api.remote'])
                ->prefix('/api/remote')
                ->scopeBindings()
                ->group(base_path('routes/api-remote.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Authentication rate limiting. For login and checkpoint endpoints we'll apply
        // a limit of 5 requests per minute, for the forgot password endpoint apply a
        // limit of two per minute for the requester so that there is less ability to
        // trigger email spam.
        RateLimiter::for('authentication', function (Request $request) {
            if ($request->route()->named('auth.post.forgot-password')) {
                return Limit::perMinute(2)->by($request->ip());
            }

            return Limit::perMinute(5)->by($request->ip());
        });

        // Specific limiter for server creation: 3 per hour per user.
        RateLimiter::for('server.create', function (Request $request) {
            $token = $request->user()?->currentAccessToken();
            if ($token instanceof ApiKey && $token->isRootKey()) {
                return Limit::none();
            }

            return Limit::perHour(3)->by($request->user()->id ?? $request->ip());
        });

        // Configure the throttles for both the application and client APIs below.
        // This is configurable per-instance in "config/http.php". By default this
        // limiter will be tied to the specific request user, and falls back to the
        // request IP if there is no request user present for the key.
        //
        // This means that an authenticated API user cannot use IP switching to get
        // around the limits.
        RateLimiter::for('api.client', function (Request $request) {
            $token = $request->user()?->currentAccessToken();
            if ($token instanceof ApiKey && $token->isRootKey()) {
                return Limit::none();
            }

            $key = optional($request->user())->uuid ?: $request->ip();
            $period = $this->systemIntSetting(
                'api_rate_limit_ptlc_period_minutes',
                (int) config('http.rate_limit.client_period', 1)
            );
            $limit = $this->systemIntSetting(
                'api_rate_limit_ptlc_per_period',
                (int) config('http.rate_limit.client', 256)
            );

            return Limit::perMinutes(max(1, $period), max(1, $limit))->by($key);
        });

        RateLimiter::for('api.application', function (Request $request) {
            $token = $request->user()?->currentAccessToken();
            if ($token instanceof ApiKey && $token->isRootKey()) {
                return Limit::none();
            }

            $key = optional($request->user())->uuid ?: $request->ip();
            $period = $this->systemIntSetting(
                'api_rate_limit_ptla_period_minutes',
                (int) config('http.rate_limit.application_period', 1)
            );
            $limit = $this->systemIntSetting(
                'api_rate_limit_ptla_per_period',
                (int) config('http.rate_limit.application', 256)
            );

            return Limit::perMinutes(max(1, $period), max(1, $limit))->by($key);
        });

        // Root application API is always rate limited, including root master keys.
        RateLimiter::for('api.rootapplication', function (Request $request) {
            $key = optional($request->user())->uuid ?: $request->ip();
            $period = $this->systemIntSetting(
                'api_rate_limit_root_period_minutes',
                (int) config('http.rate_limit.rootapplication_period', 1)
            );
            $limit = $this->systemIntSetting(
                'api_rate_limit_root_per_period',
                (int) config('http.rate_limit.rootapplication', 120)
            );

            return Limit::perMinutes(max(1, $period), max(1, $limit))->by($key);
        });

        // Remote daemon endpoints must be tied to the daemon token id to avoid
        // a single noisy node saturating panel resources.
        RateLimiter::for('api.remote', function (Request $request) {
            $parts = explode('.', (string) $request->bearerToken());
            $tokenId = trim((string) ($parts[0] ?? ''));
            $key = $tokenId !== '' ? 'token:' . $tokenId : 'ip:' . (string) $request->ip();

            $period = $this->systemIntSetting(
                'api_rate_limit_ptlr_period_minutes',
                (int) config('http.rate_limit.remote_period', 1)
            );
            $limit = $this->systemIntSetting(
                'api_rate_limit_ptlr_per_period',
                (int) config('http.rate_limit.remote', 600)
            );

            return Limit::perMinutes(max(1, $period), max(1, $limit))->by($key);
        });

        ResourceLimit::boot();
    }

    private function systemIntSetting(string $key, int $default): int
    {
        try {
            return (int) Cache::remember("system:{$key}", 30, function () use ($key, $default) {
                $value = DB::table('system_settings')->where('key', $key)->value('value');
                if ($value === null || $value === '') {
                    return $default;
                }

                return (int) $value;
            });
        } catch (\Throwable) {
            return $default;
        }
    }
}
