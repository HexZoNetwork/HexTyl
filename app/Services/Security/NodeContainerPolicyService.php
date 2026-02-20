<?php

namespace Pterodactyl\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Exceptions\DisplayException;
use Pterodactyl\Models\Server;

class NodeContainerPolicyService
{
    public function __construct(private SecurityEventService $securityEventService)
    {
    }

    public function evaluateImage(string $image): array
    {
        $normalized = trim(strtolower($image));
        $isNodeImage = str_contains($normalized, 'node:')
            || str_contains($normalized, '/node:')
            || preg_match('/(?:^|[\/\-])node(?:$|[\-_:\.])/i', $normalized) === 1;

        $major = null;
        if ($isNodeImage && preg_match('/node:(\d{1,2})/i', $normalized, $match)) {
            $major = (int) $match[1];
        }

        $minMajor = max(12, min(30, $this->intSetting('node_secure_container_min_major', 18)));
        $preferredMajor = max($minMajor, min(30, $this->intSetting('node_secure_container_preferred_major', 22)));

        $deprecated = $isNodeImage && $major !== null && $major < $minMajor;
        $recommendedImage = $isNodeImage
            ? preg_replace('/node:\d+(?:\.\d+)?(?:\.\d+)?/i', 'node:' . $preferredMajor, $image)
            : $image;

        return [
            'image' => $image,
            'is_node_image' => $isNodeImage,
            'node_major' => $major,
            'minimum_major' => $minMajor,
            'preferred_major' => $preferredMajor,
            'deprecated' => $deprecated,
            'recommended_image' => $recommendedImage,
            'reason' => $deprecated ? sprintf('Node.js major %d is below secure minimum %d.', (int) $major, $minMajor) : null,
        ];
    }

    /**
     * @throws DisplayException
     */
    public function enforceImagePolicy(string $image, ?Server $server = null, ?int $actorUserId = null, ?string $ip = null): array
    {
        $evaluation = $this->evaluateImage($image);

        if (!$this->boolSetting('node_secure_container_policy_enabled', false)) {
            return $evaluation;
        }

        if (!$evaluation['is_node_image'] && !$this->boolSetting('node_secure_container_allow_non_node', true)) {
            throw new DisplayException('Only Node.js container images are allowed while Secure Mode container policy is enabled.');
        }

        if ($evaluation['deprecated'] && $this->boolSetting('node_secure_container_block_deprecated', true)) {
            $this->securityEventService->log('security:node.container_policy.blocked', [
                'actor_user_id' => $actorUserId,
                'server_id' => $server?->id,
                'ip' => $ip,
                'risk_level' => 'high',
                'meta' => $evaluation,
            ]);

            throw new DisplayException(
                sprintf(
                    'Container image blocked by Node policy: %s Recommended upgrade: %s',
                    (string) $evaluation['reason'],
                    (string) $evaluation['recommended_image']
                )
            );
        }

        if ($evaluation['deprecated']) {
            $this->securityEventService->log('security:node.container_policy.warn', [
                'actor_user_id' => $actorUserId,
                'server_id' => $server?->id,
                'ip' => $ip,
                'risk_level' => 'medium',
                'meta' => $evaluation,
            ]);
        }

        return $evaluation;
    }

    private function boolSetting(string $key, bool $default): bool
    {
        $value = Cache::remember("system:{$key}", 30, function () use ($key) {
            return DB::table('system_settings')->where('key', $key)->value('value');
        });

        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function intSetting(string $key, int $default): int
    {
        $value = Cache::remember("system:{$key}", 30, function () use ($key) {
            return DB::table('system_settings')->where('key', $key)->value('value');
        });

        if ($value === null || $value === '') {
            return $default;
        }

        return (int) $value;
    }
}
