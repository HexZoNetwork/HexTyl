<?php

namespace Pterodactyl\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Services\Security\SecurityEventService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RequestHardening
{
    /**
     * Patterns commonly seen in SQLi/RCE probing payloads.
     */
    private array $blockedPatterns = [
        '/<\?(php|=)?/i',
        '/\bunion\b\s+\bselect\b/i',
        '/\bsleep\s*\(/i',
        '/\bbenchmark\s*\(/i',
        '/\b(load_file|into\s+outfile)\b/i',
        '/(\'|")\s*or\s+\d+\s*=\s*\d+/i',
        '/--\s*$/m',
        '/\/\*.*\*\//s',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        if ($this->containsInvalidInput($request)) {
            app(SecurityEventService::class)->log('security:hardening.blocked_request', [
                'actor_user_id' => optional($request->user())->id,
                'ip' => $request->ip(),
                'risk_level' => 'high',
                'meta' => [
                    'path' => '/' . ltrim((string) $request->path(), '/'),
                    'method' => strtoupper((string) $request->method()),
                ],
            ]);
            throw new BadRequestHttpException('Request blocked by security hardening policy.');
        }

        return $next($request);
    }

    private function containsInvalidInput(Request $request): bool
    {
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

            // Null-byte injection guard.
            if (strpos($sample, "\0") !== false) {
                return true;
            }

            foreach ($this->blockedPatterns as $pattern) {
                if (preg_match($pattern, $sample) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    private function shouldSkipBodyInspection(Request $request): bool
    {
        $path = '/' . ltrim((string) $request->path(), '/');

        // File contents commonly include comment syntax that can trigger generic SQLi signatures.
        return str_starts_with($path, '/api/client/servers/') && str_contains($path, '/files/write');
    }
}
