<?php

namespace Pterodactyl\Services\Secrets;

use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\ServerSecret;
use Pterodactyl\Exceptions\DisplayException;

class SecretVaultService
{
    public function __construct(private SecretVaultV2Service $v2)
    {
    }

    /**
     * Store a secret in the vault (Encrypted).
     */
    public function store(Server $server, string $key, string $value): void
    {
        $encrypted = Crypt::encryptString($value);

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
        $this->v2->put($server, $key, $value);
    }

    /**
     * Retrieve a secret (Decrypted).
     */
    public function retrieve(Server $server, string $key): string
    {
        $secret = ServerSecret::query()
            ->where('server_id', $server->id)
            ->where('secret_key', $key)
            ->first();
        if (!$secret) {
            $value = $this->v2->getLatest($server, $key);
            if ($value === null) {
                throw new DisplayException("Secret {$key} not found.");
            }

            return $value;
        }

        $secret->forceFill(['last_accessed_at' => now()])->save();

        return Crypt::decryptString($secret->encrypted_value);
    }

    /**
     * Inject secrets into environment variables for startup.
     */
    public function injectIntoEnvironment(Server $server, array &$env): void
    {
        $secrets = ServerSecret::query()->where('server_id', $server->id)->get();
        foreach ($secrets as $secret) {
            $env[$secret->secret_key] = Crypt::decryptString($secret->encrypted_value);
        }
    }
}
