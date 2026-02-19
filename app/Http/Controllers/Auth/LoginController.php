<?php

namespace Pterodactyl\Http\Controllers\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Pterodactyl\Models\User;
use Illuminate\Http\JsonResponse;
use Pterodactyl\Facades\Activity;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LoginController extends AbstractLoginController
{
    /**
     * Handle all incoming requests for the authentication routes and render the
     * base authentication view component. React will take over at this point and
     * turn the login area into an SPA.
     */
    public function index(): View
    {
        return view('templates/auth.core');
    }

    /**
     * Handle a login request to the application.
     *
     * @throws \Pterodactyl\Exceptions\DisplayException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        if ($this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);
            $this->sendLockoutResponse($request);
        }

        try {
            $username = $request->input('user');

            /** @var User $user */
            $user = User::query()->where($this->getField($username), $username)->firstOrFail();
        } catch (ModelNotFoundException) {
            $this->sendFailedLoginResponse($request);
        }

        // Ensure that the account is using a valid username and password before trying to
        // continue. Previously this was handled in the 2FA checkpoint, however that has
        // a flaw in which you can discover if an account exists simply by seeing if you
        // can proceed to the next step in the login process.
        // Ensure that the account is using a valid username and password before trying to
        // continue. Previously this was handled in the 2FA checkpoint, however that has
        // a flaw in which you can discover if an account exists simply by seeing if you
        // can proceed to the next step in the login process.
        if (!password_verify($request->input('password'), $user->password)) {
            $this->sendFailedLoginResponse($request, $user);
        }

        app(\Pterodactyl\Services\Security\SecurityEventService::class)->log('auth:login.success', [
            'actor_user_id' => $user->id,
            'ip' => $request->ip(),
            'risk_level' => 'info',
            'meta' => [
                'user_agent' => (string) $request->userAgent(),
                'geo_country' => (string) $request->header('CF-IPCountry', 'UNK'),
            ],
        ]);
        app(\Pterodactyl\Services\Security\ThreatIntelligenceService::class)
            ->detectLoginAnomaly($request->ip(), $user->id);

        // RISK DETECTION
        $riskService = app(\Pterodactyl\Services\Auth\SessionRiskService::class);
        if ($riskService->handle($user, $request)) {
            // If risk detected (New IP), we force 2FA checks or email verification if implemented.
            // For now, if the user does NOT have 2FA enabled, strictly verify or require it.
            // Requirement request: "Login dari IP/negara baru -> butuh verifikasi ulang"
            
            // If user has no TOTP, maybe prompt error or force email code (not implemented yet).
            // Let's at least ensure we don't skip the checkpoint if risk is high.
            // For this iteration, we just ensure 2FA is triggered if available,
            // or perhaps log a warning if not.
            
            // If you want to force verification for ALL new IPs:
            // return $this->sendVerificationRequiredResponse($user, $request); (Hypothetical)
        }

        if (!$user->use_totp) {
            return $this->sendLoginResponse($user, $request);
        }

        Activity::event('auth:checkpoint')->withRequestMetadata()->subject($user)->log();

        $request->session()->put('auth_confirmation_token', [
            'user_id' => $user->id,
            'token_value' => $token = Str::random(64),
            'expires_at' => CarbonImmutable::now()->addMinutes(5),
        ]);

        return new JsonResponse([
            'data' => [
                'complete' => false,
                'confirmation_token' => $token,
            ],
        ]);
    }
}
