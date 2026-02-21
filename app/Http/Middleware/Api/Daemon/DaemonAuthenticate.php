<?php

namespace Pterodactyl\Http\Middleware\Api\Daemon;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Contracts\Encryption\Encrypter;
use Pterodactyl\Models\Node;
use Pterodactyl\Repositories\Eloquent\NodeRepository;
use Pterodactyl\Services\Security\SecurityEventService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Pterodactyl\Exceptions\Repository\RecordNotFoundException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DaemonAuthenticate
{
    /**
     * Daemon routes that this middleware should be skipped on.
     */
    protected array $except = [
        'daemon.configuration',
    ];

    /**
     * DaemonAuthenticate constructor.
     */
    public function __construct(private Encrypter $encrypter, private NodeRepository $repository)
    {
    }

    /**
     * Check if a request from the daemon can be properly attributed back to a single node instance.
     *
     * @throws HttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        if (in_array($request->route()->getName(), $this->except)) {
            return $next($request);
        }

        if (is_null($bearer = $request->bearerToken())) {
            throw new HttpException(401, 'Access to this endpoint must include an Authorization header.', null, ['WWW-Authenticate' => 'Bearer']);
        }
        if (strlen($bearer) > 512) {
            $this->recordInvalidTokenAttempt($request, 'token_too_long');
            throw new BadRequestHttpException('The Authorization header provided was not in a valid format.');
        }

        $parts = explode('.', $bearer);
        // Ensure that all of the correct parts are provided in the header.
        if (count($parts) !== 2 || empty($parts[0]) || empty($parts[1])) {
            $this->recordInvalidTokenAttempt($request, 'token_parts_invalid');
            throw new BadRequestHttpException('The Authorization header provided was not in a valid format.');
        }
        if (
            preg_match('/^[A-Za-z0-9]{' . Node::DAEMON_TOKEN_ID_LENGTH . '}$/', $parts[0]) !== 1
            || preg_match('/^[A-Za-z0-9]{' . Node::DAEMON_TOKEN_LENGTH . '}$/', $parts[1]) !== 1
        ) {
            $this->recordInvalidTokenAttempt($request, 'token_pattern_invalid');
            throw new BadRequestHttpException('The Authorization header provided was not in a valid format.');
        }

        if ($this->isRateLimitedInvalidTokenAttempts($request, $parts[0])) {
            app(SecurityEventService::class)->log('security:daemon.auth_rate_limited', [
                'ip' => $request->ip(),
                'risk_level' => 'high',
                'meta' => [
                    'token_id' => $parts[0],
                    'path' => '/' . ltrim((string) $request->path(), '/'),
                ],
            ]);

            throw new HttpException(429, 'Too many authentication attempts.');
        }

        $quarantine = Cache::get('security:daemon:quarantine:' . $parts[0]);
        if (is_array($quarantine)) {
            app(SecurityEventService::class)->log('security:daemon.quarantine_block', [
                'ip' => $request->ip(),
                'risk_level' => 'high',
                'meta' => [
                    'token_id' => $parts[0],
                    'path' => '/' . ltrim((string) $request->path(), '/'),
                    'reason' => (string) ($quarantine['reason'] ?? 'remote_activity_violation'),
                    'violations' => (int) ($quarantine['violations'] ?? 0),
                    'quarantined_at' => (string) ($quarantine['at'] ?? ''),
                ],
            ]);

            throw new AccessDeniedHttpException('This node token is temporarily quarantined.');
        }

        try {
            /** @var \Pterodactyl\Models\Node $node */
            $node = $this->repository->findFirstWhere([
                'daemon_token_id' => $parts[0],
            ]);

            if (hash_equals((string) $this->encrypter->decrypt($node->daemon_token), $parts[1])) {
                Cache::forget($this->invalidAttemptCacheKey($request, $parts[0]));
                $request->attributes->set('node', $node);

                return $next($request);
            }
        } catch (RecordNotFoundException $exception) {
            // Do nothing, we don't want to expose a node not existing at all.
        }

        $this->recordInvalidTokenAttempt($request, 'invalid_token', $parts[0]);
        throw new AccessDeniedHttpException('You are not authorized to access this resource.');
    }

    private function invalidAttemptCacheKey(Request $request, string $tokenId): string
    {
        return 'security:daemon:auth_fail:' . sha1((string) $request->ip() . '|' . $tokenId);
    }

    private function recordInvalidTokenAttempt(Request $request, string $reason, string $tokenId = 'unknown'): void
    {
        $key = $this->invalidAttemptCacheKey($request, $tokenId);
        Cache::add($key, 0, now()->addMinutes(10));
        Cache::increment($key);
        Cache::put($key, (int) Cache::get($key), now()->addMinutes(10));

        app(SecurityEventService::class)->log('security:daemon.invalid_auth_header', [
            'ip' => $request->ip(),
            'risk_level' => 'medium',
            'meta' => [
                'path' => '/' . ltrim((string) $request->path(), '/'),
                'reason' => $reason,
            ],
        ]);
    }

    private function isRateLimitedInvalidTokenAttempts(Request $request, string $tokenId): bool
    {
        $key = $this->invalidAttemptCacheKey($request, $tokenId);
        $count = (int) Cache::get($key, 0);

        return $count > 60;
    }
}
