<?php

namespace Pterodactyl\Services\Secrets;

use Illuminate\Support\Facades\Crypt;
use Pterodactyl\Models\SecretVaultVersion;
use Pterodactyl\Models\Server;

class SecretVaultV2Service
{
    public function put(Server $server, string $key, string $value, ?int $actorUserId = null, ?\DateTimeInterface $expiresAt = null): SecretVaultVersion
    {
        $latestVersion = (int) SecretVaultVersion::query()
            ->where('server_id', $server->id)
            ->where('secret_key', $key)
            ->max('version');

        return SecretVaultVersion::query()->create([
            'server_id' => $server->id,
            'secret_key' => $key,
            'version' => $latestVersion + 1,
            'encrypted_value' => Crypt::encryptString($value),
            'created_by' => $actorUserId,
            'expires_at' => $expiresAt,
        ]);
    }

    public function getLatest(Server $server, string $key): ?string
    {
        $latest = SecretVaultVersion::query()
            ->where('server_id', $server->id)
            ->where('secret_key', $key)
            ->orderByDesc('version')
            ->first();

        if (!$latest) {
            return null;
        }

        $latest->forceFill([
            'access_count' => $latest->access_count + 1,
            'last_accessed_at' => now(),
        ])->save();

        return Crypt::decryptString($latest->encrypted_value);
    }

    public function rotateDueSecrets(): int
    {
        $due = SecretVaultVersion::query()
            ->whereNotNull('rotates_at')
            ->where('rotates_at', '<=', now())
            ->count();

        return (int) $due;
    }
}
