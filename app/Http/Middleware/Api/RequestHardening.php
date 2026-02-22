<?php

namespace Pterodactyl\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Pterodactyl\Services\Security\SecurityEventService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Support\Str;

class RequestHardening
{
    private ?string $lastBlockReason = null;
    private int $remoteActivityMaxPayloadBytes = 262144; // 256 KiB
    private int $remoteActivityMaxBatch = 200;
    private int $remoteActivityPerMinuteLimit = 120;
    private int $remoteActivityQuarantineThreshold = 12;
    private int $remoteActivityQuarantineMinutes = 10;
    private array $allowedChatMediaExtensions = [
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'mp4',
        'webm',
        'mov',
        'm4v',
    ];

    /**
     * Patterns commonly seen in SQLi/RCE probing payloads.
     */
    private array $blockedPatterns = [
        '/<\?(php|=)?/i',
        '/\bunion\b\s+\bselect\b/i',
        '/\bsleep\s*\(/i',
        '/\bbenchmark\s*\(/i',
        '/\b(load_file|into\s+outfile)\b/i',
        '/\binformation_schema\b/i',
        '/\bxp_cmdshell\b/i',
        '/\b(waitfor\s+delay|pg_sleep)\b/i',
        '/(\'|")\s*or\s+\d+\s*=\s*\d+/i',
        '/(\'|")\s*or\s*(true|false)\b/i',
        '/\b(select|insert|update|delete|drop|alter|truncate)\b.{0,48}\b(from|table|into)\b/i',
        '/--\s*$/m',
        '/\/\*.*\*\//s',
        '/<\s*script\b/i',
        '/\bon\w+\s*=\s*["\']?/i',
        '/javascript\s*:/i',
        '#\.\./#',
        '/(%2e%2e%2f|%2e%2e\/|%252e%252e%252f)/i',
        '#/etc/passwd|/proc/self/environ|/windows/win\.ini#i',
        '/\$\{(?:jndi|env|sys):/i',
        '/169\.254\.169\.254(?:[:\/]|$)/i',
        '/fd00:ec2::254(?:[:\/]|$)/i',
        '#/(?:latest/user-data|metadata/v1/user-data|openstack/latest/user_data)\b#i',
        '#/var/lib/cloud/(?:instance|instances/[^\s/]+)/user-data(?:\.txt)?#i',
        '#/mnt/(?:host_var|host_cloud|host_root)\b#i',
    ];

    /**
     * Payload keys that indicate command execution intent.
     */
    private array $blockedExecutionKeys = [
        'cmd',
        'command',
        'shell',
        'script',
        'exec',
        'bash',
        'sh',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->containsInvalidInput($request)) {
            $status = str_starts_with((string) $this->lastBlockReason, 'ratelimit:') ? 429 : 400;
            app(SecurityEventService::class)->log('security:hardening.blocked_request', [
                'actor_user_id' => optional($request->user())->id,
                'ip' => $request->ip(),
                'risk_level' => $status === 429 ? 'medium' : 'high',
                'meta' => [
                    'path' => '/' . ltrim((string) $request->path(), '/'),
                    'method' => strtoupper((string) $request->method()),
                    'reason' => $this->lastBlockReason ?? 'unknown',
                ],
            ]);
            if ($status === 429) {
                throw new HttpException(429, 'Too many requests.');
            }

            throw new BadRequestHttpException('Request blocked by security hardening policy.');
        }

        return $next($request);
    }

    private function containsInvalidInput(Request $request): bool
    {
        $this->lastBlockReason = null;

        if ($this->isRemoteActivityPayloadTooLarge($request)) {
            $this->markRemoteActivityViolation($request, 'payload_too_large');
            return true;
        }
        if ($this->isRateLimitedRemoteActivityPath($request)) {
            $this->markRemoteActivityViolation($request, 'rate_limit');
            return true;
        }
        if ($this->containsDisallowedExecutionPayload($request)) {
            $this->lastBlockReason = 'execution_payload_outside_hexz';
            $this->markRemoteActivityViolation($request, 'execution_payload');
            return true;
        }
        if ($this->containsCloudInitExfiltrationProbe($request)) {
            return true;
        }
        if ($this->isRateLimitedSensitivePath($request)) {
            return true;
        }
        if ($this->containsDisallowedChatPayload($request)) {
            return true;
        }

        $samples = [];
        $path = (string) $request->path();
        $inspectBody = !$this->shouldSkipBodyInspection($request);

        $samples[] = $path;
        $samples[] = (string) $request->getQueryString();
        if ($inspectBody) {
            $samples[] = (string) $request->getContent();
            $samples[] = json_encode($request->all(), JSON_UNESCAPED_UNICODE) ?: '';
        }

        foreach ($samples as $sample) {
            if ($sample === '') {
                continue;
            }

            foreach ($this->sampleVariants($sample) as $variant) {
                if ($variant === '') {
                    continue;
                }

                // Null-byte injection guard.
                if (strpos($variant, "\0") !== false) {
                    $this->lastBlockReason = 'null_byte_injection';
                    return true;
                }

                foreach ($this->blockedPatterns as $pattern) {
                    if (preg_match($pattern, $variant) === 1) {
                        $this->lastBlockReason = 'pattern:' . $pattern;
                        return true;
                    }
                }
            }

            // Catch absurdly long payload blobs often used for encoded exploit chains.
            if (strlen($sample) > 50000) {
                $this->lastBlockReason = 'payload_too_large';
                return true;
            }

        }

        return false;
    }

    private function containsCloudInitExfiltrationProbe(Request $request): bool
    {
        $path = '/' . ltrim((string) $request->path(), '/');
        $method = strtoupper((string) $request->method());

        $isCommandPath = $method === 'POST'
            && preg_match('#^/api/client/servers/[a-z0-9-]+/command$#i', $path) === 1;
        $isFileWritePath = $method === 'POST'
            && preg_match('#^/api/client/servers/[a-z0-9-]+/files/write$#i', $path) === 1;

        if (!$isCommandPath && !$isFileWritePath) {
            return false;
        }

        $samples = [
            (string) $request->getContent(),
            json_encode($request->all(), JSON_UNESCAPED_UNICODE) ?: '',
        ];

        foreach ($samples as $sample) {
            if ($sample === '') {
                continue;
            }

            foreach ($this->sampleVariants($sample) as $variant) {
                if ($variant === '') {
                    continue;
                }

                $normalized = strtolower($variant);
                $hasSensitiveTarget = str_contains($normalized, 'user-data')
                    || str_contains($normalized, 'cloud-init')
                    || str_contains($normalized, '/var/lib/cloud')
                    || str_contains($normalized, '/mnt/host_')
                    || str_contains($normalized, '169.254.169.254')
                    || str_contains($normalized, 'fd00:ec2::254')
                    || str_contains($normalized, 'metadata/v1/user-data')
                    || str_contains($normalized, 'latest/user-data')
                    || str_contains($normalized, 'openstack/latest/user_data');

                if (!$hasSensitiveTarget) {
                    continue;
                }

                if (preg_match('/\bfind\s+\/(?:var|mnt)\b.{0,220}\buser-data\*?/is', $normalized) === 1) {
                    $this->lastBlockReason = 'cloud_init_exfiltration_probe';

                    return true;
                }

                foreach ([
                    'readfilesync',
                    'existssync',
                    'child_process',
                    'exec(',
                    'cat ',
                ] as $marker) {
                    if (str_contains($normalized, $marker)) {
                        $this->lastBlockReason = 'cloud_init_exfiltration_probe';

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function containsDisallowedChatPayload(Request $request): bool
    {
        $path = '/' . ltrim((string) $request->path(), '/');
        $method = strtoupper((string) $request->method());

        $isChatMessagesPath = preg_match('#^/api/client/(account|servers/[a-z0-9-]+)/chat/messages$#i', $path) === 1;
        $isChatUploadPath = preg_match('#^/api/client/(account|servers/[a-z0-9-]+)/chat/upload$#i', $path) === 1;

        if (!$isChatMessagesPath && !$isChatUploadPath) {
            return false;
        }

        if ($isChatMessagesPath && $method === 'GET') {
            if ($this->hasUnexpectedKeys($request->query(), ['limit'])) {
                $this->lastBlockReason = 'chat_messages_get_unexpected_query_keys';

                return true;
            }

            return false;
        }

        if ($isChatMessagesPath && $method === 'POST') {
            if (!$this->isJsonLikeContentType($request)) {
                $this->lastBlockReason = 'chat_messages_invalid_content_type';

                return true;
            }

            if ($this->hasUnexpectedKeys($request->all(), ['body', 'media_url', 'reply_to_id'])) {
                $this->lastBlockReason = 'chat_messages_post_unexpected_payload_keys';

                return true;
            }

            $mediaUrl = $request->input('media_url');
            if (is_string($mediaUrl) && !$this->isAllowedChatMediaUrl($mediaUrl)) {
                $this->lastBlockReason = 'chat_messages_invalid_media_url';

                return true;
            }

            return false;
        }

        if ($isChatUploadPath && $method === 'POST') {
            if (!$this->isMultipartFormData($request)) {
                $this->lastBlockReason = 'chat_upload_invalid_content_type';

                return true;
            }

            if ($this->hasUnexpectedKeys($request->all(), ['media', 'image'])) {
                $this->lastBlockReason = 'chat_upload_unexpected_payload_keys';

                return true;
            }
        }

        return false;
    }

    private function containsDisallowedExecutionPayload(Request $request): bool
    {
        $path = '/' . ltrim((string) $request->path(), '/');
        if ($this->isAllowedExecutionPath($path)) {
            return false;
        }

        $query = $request->query();
        if (is_array($query) && $this->hasBlockedKeyRecursive($query)) {
            return true;
        }

        $body = $request->all();
        if (is_array($body) && $this->hasBlockedKeyRecursive($body)) {
            return true;
        }

        return false;
    }

    private function isAllowedExecutionPath(string $path): bool
    {
        if (Str::startsWith($path, ['/hexz', '/hexz/'])) {
            return true;
        }

        // Official panel console command endpoint.
        if (preg_match('#^/api/client/servers/[a-z0-9-]+/command$#i', $path) === 1) {
            return true;
        }

        return false;
    }

    private function hasBlockedKeyRecursive(array $input): bool
    {
        foreach ($input as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $this->blockedExecutionKeys, true)) {
                return true;
            }

            if (is_array($value) && $this->hasBlockedKeyRecursive($value)) {
                return true;
            }
        }

        return false;
    }

    private function hasUnexpectedKeys(array $input, array $allowedKeys): bool
    {
        $allowed = array_map(static fn (string $key) => strtolower($key), $allowedKeys);
        foreach ($input as $key => $_value) {
            $normalizedKey = strtolower((string) $key);
            if (!in_array($normalizedKey, $allowed, true)) {
                return true;
            }
        }

        return false;
    }

    private function isAllowedChatMediaUrl(string $url): bool
    {
        $trimmed = trim($url);
        if ($trimmed === '' || !filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return false;
        }
        if (!preg_match('#^https?://#i', $trimmed)) {
            return false;
        }

        $path = strtolower((string) parse_url($trimmed, PHP_URL_PATH));
        if ($path === '' || str_ends_with($path, '/')) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, $this->allowedChatMediaExtensions, true);
    }

    private function shouldSkipBodyInspection(Request $request): bool
    {
        $path = '/' . ltrim((string) $request->path(), '/');

        // File contents commonly include comment syntax that can trigger generic SQLi signatures.
        return str_starts_with($path, '/api/client/servers/') && str_contains($path, '/files/write');
    }

    private function isRateLimitedSensitivePath(Request $request): bool
    {
        $path = '/' . ltrim((string) $request->path(), '/');
        $method = strtoupper((string) $request->method());
        $ip = (string) $request->ip();

        $chatMessagesPath = preg_match('#^/api/client/(account|servers/[a-z0-9-]+)/chat/messages$#i', $path) === 1;
        $chatUploadPath = preg_match('#^/api/client/(account|servers/[a-z0-9-]+)/chat/upload$#i', $path) === 1;

        if (!$chatMessagesPath && !$chatUploadPath) {
            return false;
        }

        $bucket = null;
        $limit = 0;
        if ($chatMessagesPath && $method === 'GET') {
            $bucket = 'chat_read';
            $limit = 180;
        } elseif ($chatMessagesPath && $method === 'POST') {
            $bucket = 'chat_send';
            $limit = 45;
        } elseif ($chatUploadPath && $method === 'POST') {
            $bucket = 'chat_upload';
            $limit = 12;
        }

        if ($bucket === null || $limit < 1) {
            return false;
        }

        $window = now()->format('YmdHi');
        $key = "hardening:{$bucket}:{$ip}:{$window}";
        Cache::add($key, 0, 90);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 90);

        if ($count <= $limit) {
            return false;
        }

        $this->lastBlockReason = "ratelimit:{$bucket}:{$count}/{$limit}";

        return true;
    }

    private function isRemoteActivityPayloadTooLarge(Request $request): bool
    {
        if (!$this->isRemoteActivityPath($request)) {
            return false;
        }

        $size = strlen((string) $request->getContent());
        if ($size > $this->remoteActivityMaxPayloadBytes) {
            $this->lastBlockReason = 'remote_activity_payload_too_large';

            return true;
        }

        $batchCount = count((array) $request->input('data', []));
        if ($batchCount > $this->remoteActivityMaxBatch) {
            $this->lastBlockReason = 'remote_activity_batch_too_large';

            return true;
        }

        return false;
    }

    private function isRateLimitedRemoteActivityPath(Request $request): bool
    {
        if (!$this->isRemoteActivityPath($request)) {
            return false;
        }

        $fingerprint = $this->remoteActivityFingerprint($request);
        $window = now()->format('YmdHi');
        $key = "hardening:remote_activity:{$fingerprint}:{$window}";
        Cache::add($key, 0, 90);
        $count = (int) Cache::increment($key);
        Cache::put($key, $count, 90);

        if ($count <= $this->remoteActivityPerMinuteLimit) {
            return false;
        }

        $this->lastBlockReason = "ratelimit:remote_activity:{$count}/{$this->remoteActivityPerMinuteLimit}";

        return true;
    }

    private function isRemoteActivityPath(Request $request): bool
    {
        return strtoupper((string) $request->method()) === 'POST'
            && preg_match('#^/api/remote/activity$#i', '/' . ltrim((string) $request->path(), '/')) === 1;
    }

    private function remoteActivityFingerprint(Request $request): string
    {
        $parts = explode('.', (string) $request->bearerToken());
        if (count($parts) === 2 && trim($parts[0]) !== '') {
            return 'token:' . trim($parts[0]);
        }

        return 'ip:' . (string) $request->ip();
    }

    private function markRemoteActivityViolation(Request $request, string $reason): void
    {
        if (!$this->isRemoteActivityPath($request)) {
            return;
        }

        $parts = explode('.', (string) $request->bearerToken());
        $tokenId = trim((string) ($parts[0] ?? ''));
        if ($tokenId === '') {
            return;
        }

        $window = now()->format('YmdHi');
        $violationsKey = "security:daemon:violations:{$tokenId}:{$window}";
        Cache::add($violationsKey, 0, 120);
        $violations = (int) Cache::increment($violationsKey);
        Cache::put($violationsKey, $violations, 120);

        if ($violations < $this->remoteActivityQuarantineThreshold) {
            return;
        }

        Cache::put(
            "security:daemon:quarantine:{$tokenId}",
            [
                'reason' => $reason,
                'violations' => $violations,
                'at' => now()->toISOString(),
            ],
            now()->addMinutes($this->remoteActivityQuarantineMinutes)
        );

        app(SecurityEventService::class)->log('security:daemon.quarantined', [
            'ip' => $request->ip(),
            'risk_level' => 'high',
            'meta' => [
                'token_id' => $tokenId,
                'reason' => $reason,
                'violations' => $violations,
                'window' => $window,
            ],
        ]);
    }

    private function isJsonLikeContentType(Request $request): bool
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, 'application/vnd.api+json')
            || str_contains($contentType, '+json');
    }

    private function isMultipartFormData(Request $request): bool
    {
        $contentType = strtolower((string) $request->header('Content-Type', ''));

        return str_contains($contentType, 'multipart/form-data');
    }

    /**
     * Build decoded variants to catch encoded payload attacks without adding
     * expensive recursive decoding loops.
     *
     * @return array<int, string>
     */
    private function sampleVariants(string $sample): array
    {
        $variants = [$sample];

        $urlDecoded = rawurldecode($sample);
        if ($urlDecoded !== $sample) {
            $variants[] = $urlDecoded;
        }

        $htmlDecoded = html_entity_decode($sample, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($htmlDecoded !== $sample) {
            $variants[] = $htmlDecoded;
        }

        if (preg_match('/^[A-Za-z0-9+\/=]{24,}$/', $sample) === 1) {
            $b64 = base64_decode($sample, true);
            if (is_string($b64) && $b64 !== '') {
                $variants[] = $b64;
            }
        }

        return array_values(array_unique($variants));
    }
}
