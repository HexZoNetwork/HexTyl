<?php

namespace Pterodactyl\Services\Ide;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\IdeSession;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Security\SecurityEventService;
use RuntimeException;

class IdeSessionService
{
    public function __construct(private SecurityEventService $securityEventService)
    {
    }

    public function createSession(Server $server, User $user, string $ip, array $options = []): array
    {
        $enabled = $this->settingBool('ide_connect_enabled', false);
        if (!$enabled) {
            throw new RuntimeException('IDE connect is disabled by system policy.');
        }

        $blockDuringEmergency = $this->settingBool('ide_block_during_emergency', true);
        $emergency = $this->settingBool('root_emergency_mode', false);
        if ($blockDuringEmergency && $emergency && !$user->isRoot()) {
            throw new RuntimeException('IDE connect is temporarily disabled during emergency mode.');
        }

        if (Cache::has("quarantine:server:{$server->id}") && !$user->isRoot()) {
            throw new RuntimeException('IDE connect is blocked because this server is currently quarantined.');
        }

        $template = $this->resolveLaunchTemplate();
        if ($template === '') {
            throw new RuntimeException('IDE connect URL/domain is not configured.');
        }

        $ttlMinutes = max(1, min(120, (int) $this->settingString('ide_session_ttl_minutes', '10')));
        $expiresAt = now()->addMinutes($ttlMinutes);
        $token = bin2hex(random_bytes(24));
        $tokenHash = hash('sha256', $token);
        $node = $server->relationLoaded('node') ? $server->node : $server->node()->select(['id', 'name', 'fqdn'])->first();
        $nodeId = (string) ($server->node_id ?? ($node?->id ?? ''));
        $nodeName = (string) ($node?->name ?? '');
        $nodeFqdn = (string) ($node?->fqdn ?? '');

        $payload = [
            'server_id' => $server->id,
            'server_uuid' => $server->uuid,
            'server_identifier' => method_exists($server, 'getRouteKey') ? $server->getRouteKey() : ($server->uuidShort ?? $server->uuid),
            'server_name' => $server->name,
            'node_id' => $nodeId,
            'node_name' => $nodeName,
            'node_fqdn' => $nodeFqdn,
            'user_id' => $user->id,
            'ip' => $ip,
            'created_at' => now()->toAtomString(),
            'expires_at' => $expiresAt->toAtomString(),
            'ide_terminal' => (bool) ($options['terminal'] ?? false),
            'ide_extensions' => (bool) ($options['extensions'] ?? false),
        ];

        $launchUrl = $this->compileTemplate($template, [
            'token' => $token,
            'token_hash' => $tokenHash,
            'server_uuid' => $server->uuid,
            'server_identifier' => (string) (method_exists($server, 'getRouteKey') ? $server->getRouteKey() : ($server->uuidShort ?? $server->uuid)),
            'server_name' => rawurlencode((string) $server->name),
            'server_internal_id' => (string) $server->id,
            'node_id' => rawurlencode($nodeId),
            'node_name' => rawurlencode($nodeName),
            'node_fqdn' => rawurlencode($nodeFqdn),
            'user_id' => (string) $user->id,
            'expires_at_unix' => (string) $expiresAt->getTimestamp(),
        ]);

        Cache::put("ide:session:{$tokenHash}", $payload, $expiresAt);
        Cache::put("ide:session:list:server:{$server->id}:{$tokenHash}", true, $expiresAt);

        IdeSession::query()->create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'token_hash' => $tokenHash,
            'launch_url' => $launchUrl ?? null,
            'request_ip' => $ip,
            'terminal_allowed' => (bool) ($options['terminal'] ?? false),
            'extensions_allowed' => (bool) ($options['extensions'] ?? false),
            'expires_at' => $expiresAt,
            'meta' => [
                'server_identifier' => (string) (method_exists($server, 'getRouteKey') ? $server->getRouteKey() : ($server->uuidShort ?? $server->uuid)),
            ],
        ]);

        $this->securityEventService->log('security:ide.session_created', [
            'actor_user_id' => $user->id,
            'server_id' => $server->id,
            'ip' => $ip,
            'risk_level' => 'low',
            'meta' => [
                'token_hash' => $tokenHash,
                'ttl_minutes' => $ttlMinutes,
            ],
        ]);

        return [
            'token' => $token,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->toAtomString(),
            'launch_url' => $launchUrl,
            'ttl_minutes' => $ttlMinutes,
        ];
    }

    public function validateToken(
        string $token,
        bool $consume = false,
        ?string $expectedServerIdentifier = null,
        ?string $requestIp = null
    ): array {
        $token = trim($token);
        if ($token === '') {
            throw new RuntimeException('Token is required.');
        }

        $tokenHash = hash('sha256', $token);
        $session = IdeSession::query()
            ->with(['server:id,uuid,uuidShort,name,node_id', 'server.node:id,name,fqdn', 'user:id,username,email'])
            ->where('token_hash', $tokenHash)
            ->first();
        if (!$session) {
            throw new RuntimeException('IDE token is invalid.');
        }

        if ($session->revoked_at !== null) {
            throw new RuntimeException('IDE token has been revoked.');
        }

        if ($session->expires_at->isPast()) {
            throw new RuntimeException('IDE token has expired.');
        }

        if ($session->consumed_at !== null) {
            throw new RuntimeException('IDE token has already been consumed.');
        }

        $serverIdentifier = (string) ($session->meta['server_identifier'] ?? $session->server?->uuidShort ?? $session->server?->uuid ?? '');
        if ($expectedServerIdentifier !== null && trim($expectedServerIdentifier) !== '' && $serverIdentifier !== trim($expectedServerIdentifier)) {
            throw new RuntimeException('IDE token server mismatch.');
        }

        if ($consume) {
            $session->forceFill(['consumed_at' => now()])->save();
            Cache::forget("ide:session:{$tokenHash}");
            Cache::forget("ide:session:list:server:{$session->server_id}:{$tokenHash}");
        }

        $this->securityEventService->log('security:ide.session_validated', [
            'actor_user_id' => $session->user_id,
            'server_id' => $session->server_id,
            'ip' => $requestIp,
            'risk_level' => 'low',
            'meta' => [
                'token_hash' => $tokenHash,
                'consumed' => $consume,
            ],
        ]);

        return [
            'token_hash' => $session->token_hash,
            'server_id' => $session->server_id,
            'server_uuid' => (string) ($session->server?->uuid ?? ''),
            'server_identifier' => $serverIdentifier,
            'server_name' => (string) ($session->server?->name ?? ''),
            'node_id' => (int) ($session->server?->node_id ?? 0),
            'node_name' => (string) ($session->server?->node?->name ?? ''),
            'node_fqdn' => (string) ($session->server?->node?->fqdn ?? ''),
            'user_id' => $session->user_id,
            'username' => (string) ($session->user?->username ?? ''),
            'request_ip' => (string) ($session->request_ip ?? ''),
            'terminal_allowed' => (bool) $session->terminal_allowed,
            'extensions_allowed' => (bool) $session->extensions_allowed,
            'expires_at' => $session->expires_at->toAtomString(),
            'consumed_at' => $session->consumed_at?->toAtomString(),
        ];
    }

    public function revokeSessions(?int $serverId = null, ?string $tokenHash = null, ?int $actorUserId = null, ?string $ip = null): int
    {
        $query = IdeSession::query()->whereNull('revoked_at')->where('expires_at', '>=', now());
        if ($serverId !== null) {
            $query->where('server_id', $serverId);
        }
        if ($tokenHash !== null && trim($tokenHash) !== '') {
            $query->where('token_hash', trim($tokenHash));
        }

        $sessions = $query->get(['id', 'server_id', 'token_hash']);
        if ($sessions->isEmpty()) {
            return 0;
        }

        $ids = $sessions->pluck('id')->all();
        IdeSession::query()->whereIn('id', $ids)->update(['revoked_at' => now(), 'updated_at' => now()]);
        foreach ($sessions as $session) {
            Cache::forget("ide:session:{$session->token_hash}");
            Cache::forget("ide:session:list:server:{$session->server_id}:{$session->token_hash}");
        }

        $this->securityEventService->log('security:ide.session_revoked', [
            'actor_user_id' => $actorUserId,
            'server_id' => $serverId,
            'ip' => $ip,
            'risk_level' => 'medium',
            'meta' => [
                'count' => count($ids),
                'token_hash' => $tokenHash,
            ],
        ]);

        return count($ids);
    }

    public function stats(?int $serverId = null): array
    {
        $base = IdeSession::query();
        if ($serverId !== null) {
            $base->where('server_id', $serverId);
        }

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->whereNull('revoked_at')->whereNull('consumed_at')->where('expires_at', '>=', now())->count(),
            'consumed_24h' => (clone $base)->whereNotNull('consumed_at')->where('consumed_at', '>=', now()->subDay())->count(),
            'revoked_24h' => (clone $base)->whereNotNull('revoked_at')->where('revoked_at', '>=', now()->subDay())->count(),
        ];
    }

    private function compileTemplate(string $template, array $values): string
    {
        $result = $template;
        foreach ($values as $key => $value) {
            $result = str_replace('{' . $key . '}', (string) $value, $result);
        }

        return $result;
    }

    private function resolveLaunchTemplate(): string
    {
        $raw = trim($this->settingString('ide_connect_url_template', ''));
        if ($raw === '') {
            return '';
        }

        if (str_contains($raw, '{token}')) {
            return $raw;
        }

        $base = $this->normalizeBaseUrl($raw);
        if ($base === '') {
            return '';
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return rtrim($base, '/') . '/session/{server_identifier}' . $separator . 'token={token}';
    }

    private function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';
        $query = isset($parts['query']) ? '?' . (string) $parts['query'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}";
    }

    private function settingBool(string $key, bool $default): bool
    {
        $value = Cache::remember("system:{$key}", 30, function () use ($key) {
            return DB::table('system_settings')->where('key', $key)->value('value');
        });
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function settingString(string $key, string $default): string
    {
        $value = Cache::remember("system:{$key}", 30, function () use ($key) {
            return DB::table('system_settings')->where('key', $key)->value('value');
        });
        if ($value === null || $value === '') {
            return $default;
        }

        return (string) $value;
    }
}
