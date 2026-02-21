<?php

namespace Pterodactyl\Services\Secrets;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSecret;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Services\Security\SecurityEventService;

class SecretVaultService
{
    public function __construct(private SecretVaultV2Service $v2, private ?SecurityEventService $securityEvents = null)
    {
    }

    /**
     * Store a secret in the vault (Encrypted).
     */
    public function store(Server $server, string $key, string $value, ?int $actorUserId = null, ?string $ip = null): void
    {
        $encrypted = Crypt::encryptString($value);

        DB::transaction(function () use ($server, $key, $value, $encrypted, $actorUserId, $ip): void {
            ServerSecret::query()->updateOrCreate(
                [
                    'server_id' => $server->id,
                    'secret_key' => $key,
                ],
                [
                    'encrypted_value' => $encrypted,
                ]
            );

            // Also keep a versioned copy for audit/rotation workflows.
            $this->v2->put($server, $key, $value, $actorUserId);
        }, 3);
    }

    /**
     * Retrieve a secret (Decrypted).
     */
    public function retrieve(
        Server $server,
        string $key,
        ?int $actorUserId = null,
        ?string $ip = null,
        string $reason = 'manual'
    ): string
    {
        $secret = ServerSecret::query()
            ->where('server_id', $server->id)
            ->where('secret_key', $key)
            ->first();
        if (!$secret) {
            $value = $this->v2->getLatest($server, $key, $actorUserId, $ip, 'v1_fallback');
            if ($value === null) {
                throw new DisplayException("Secret {$key} not found.");
            }

            $this->logAccessEvent('vault.secret.accessed_fallback', $server, $key, 'v1_fallback', $actorUserId, $ip, $reason);

            return $value;
        }

        $secret->forceFill(['last_accessed_at' => now()])->save();
        $this->logAccessEvent('vault.secret.accessed', $server, $key, 'v1', $actorUserId, $ip, $reason);

        return Crypt::decryptString($secret->encrypted_value);
    }

    /**
     * Inject secrets into environment variables for startup.
     */
    public function injectIntoEnvironment(Server $server, array &$env, ?int $actorUserId = null, ?string $ip = null): void
    {
        $secrets = ServerSecret::query()->where('server_id', $server->id)->get();
        foreach ($secrets as $secret) {
            $secret->forceFill(['last_accessed_at' => now()])->save();
            $env[$secret->secret_key] = Crypt::decryptString($secret->encrypted_value);
            $this->logAccessEvent('vault.secret.injected', $server, $secret->secret_key, 'v1_inject', $actorUserId, $ip, 'environment_inject');
        }

        foreach ($this->v2->listKeys($server) as $secretKey) {
            if (array_key_exists($secretKey, $env)) {
                continue;
            }

            $value = $this->v2->getLatest($server, $secretKey, $actorUserId, $ip, 'v2_inject_fallback');
            if ($value === null) {
                continue;
            }

            $env[$secretKey] = $value;
            $this->logAccessEvent('vault.secret.injected_fallback', $server, $secretKey, 'v2_inject_fallback', $actorUserId, $ip, 'environment_inject');
        }
    }

    private function logAccessEvent(
        string $eventType,
        Server $server,
        string $key,
        string $source,
        ?int $actorUserId = null,
        ?string $ip = null,
        ?string $reason = null
    ): void {
        if (!$this->securityEvents) {
            return;
        }

        $this->securityEvents->log($eventType, [
            'actor_user_id' => $actorUserId,
            'server_id' => $server->id,
            'ip' => $ip ? substr($ip, 0, 45) : null,
            'risk_level' => SecurityEvent::RISK_INFO,
            'meta' => [
                'source' => $source,
                'reason' => $reason,
                'secret_key_sha256' => hash('sha256', $key),
                'secret_key_length' => strlen($key),
            ],
        ]);
    }
}
