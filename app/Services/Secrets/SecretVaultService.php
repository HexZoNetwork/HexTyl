<?php

namespace Pterodactyl\Services\Secrets;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Models\Server;
use Pterodactyl\Exceptions\DisplayException;

class SecretVaultService
{
    /**
     * Store a secret in the vault (Encrypted).
     */
    public function store(Server $server, string $key, string $value): void
    {
        // In reality, this would likely be a separate table 'server_secrets'
        // For now, we mock the storage pattern.
        
        $encrypted = Crypt::encryptString($value);
        
        // SettingsRepository::set('secret:' . $server->id . ':' . $key, $encrypted);
        Cache::put('secret:' . $server->id . ':' . $key, $encrypted);
    }

    /**
     * Retrieve a secret (Decrypted).
     */
    public function retrieve(Server $server, string $key): string
    {
        $encrypted = Cache::get('secret:' . $server->id . ':' . $key);

        if (!$encrypted) {
            throw new DisplayException("Secret {$key} not found.");
        }

        return Crypt::decryptString($encrypted);
    }

    /**
     * Inject secrets into environment variables for startup.
     */
    public function injectIntoEnvironment(Server $server, array &$env): void
    {
        // Fetch all secrets for server
        // decrypt and add to env array
        
        // $secrets = ...
        // foreach ($secrets as $k => $v) $env[$k] = $v;
    }
}
