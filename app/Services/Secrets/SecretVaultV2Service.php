<?php

namespace Pterodactyl\Services\Secrets;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\SecretVaultVersion;
use Pterodactyl\Models\SecurityEvent;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Security\SecurityEventService;

class SecretVaultV2Service
{
    public function __construct(private ?SecurityEventService $securityEvents = null)
    {
    }

    public function put(Server $server, string $key, string $value, ?int $actorUserId = null, ?\DateTimeInterface $expiresAt = null): SecretVaultVersion
    {
        $encrypted = Crypt::encryptString($value);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($server, $key, $encrypted, $actorUserId, $expiresAt): SecretVaultVersion {
                    $latestVersion = (int) SecretVaultVersion::query()
                        ->where('server_id', $server->id)
                        ->where('secret_key', $key)
                        ->lockForUpdate()
                        ->max('version');

                    $record = SecretVaultVersion::query()->create([
                        'server_id' => $server->id,
                        'secret_key' => $key,
                        'version' => $latestVersion + 1,
                        'encrypted_value' => $encrypted,
                        'created_by' => $actorUserId,
                        'expires_at' => $expiresAt,
                    ]);

                    $this->logEvent('vault.secret.stored', $server, $key, [
                        'version' => $record->version,
                        'expires_at' => $record->expires_at?->toAtomString(),
                        'source' => 'v2',
                    ], $actorUserId, null);

                    return $record;
                }, 3);
            } catch (QueryException $exception) {
                if (!$this->isUniqueConstraintViolation($exception) || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to store secret version after retries.');
    }

    public function getLatest(
        Server $server,
        string $key,
        ?int $actorUserId = null,
        ?string $ip = null,
        string $source = 'v2'
    ): ?string
    {
        $latest = SecretVaultVersion::query()
            ->where('server_id', $server->id)
            ->where('secret_key', $key)
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('version')
            ->first();

        if (!$latest) {
            return null;
        }

        SecretVaultVersion::query()
            ->whereKey($latest->id)
            ->increment('access_count', 1, ['last_accessed_at' => now()]);

        $value = Crypt::decryptString($latest->encrypted_value);
        $this->logEvent('vault.secret.accessed', $server, $key, [
            'version' => $latest->version,
            'source' => $source,
        ], $actorUserId, $ip);

        return $value;
    }

    /**
     * @return array<int, string>
     */
    public function listKeys(Server $server): array
    {
        return SecretVaultVersion::query()
            ->where('server_id', $server->id)
            ->distinct()
            ->orderBy('secret_key')
            ->pluck('secret_key')
            ->all();
    }

    public function rotateDueSecrets(): int
    {
        $due = SecretVaultVersion::query()
            ->whereNotNull('rotates_at')
            ->where('rotates_at', '<=', now())
            ->count();

        return (int) $due;
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $state = (string) (($exception->errorInfo[0] ?? $exception->getCode()) ?? '');
        if ($state === '23000' || $state === '23505') {
            return true;
        }

        return str_contains(strtolower((string) $exception->getMessage()), 'unique');
    }

    private function logEvent(
        string $eventType,
        Server $server,
        string $key,
        array $meta = [],
        ?int $actorUserId = null,
        ?string $ip = null
    ): void {
        if (!$this->securityEvents) {
            return;
        }

        $this->securityEvents->log($eventType, [
            'actor_user_id' => $actorUserId,
            'server_id' => $server->id,
            'ip' => $ip ? substr($ip, 0, 45) : null,
            'risk_level' => SecurityEvent::RISK_INFO,
            'meta' => array_merge($meta, [
                'secret_key_sha256' => hash('sha256', $key),
                'secret_key_length' => strlen($key),
            ]),
        ]);
    }
}
