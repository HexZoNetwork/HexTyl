<?php

namespace Pterodactyl\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
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
            throw new BadRequestHttpException('Request blocked by security hardening policy.');
        }

        return $next($request);
    }

    private function containsInvalidInput(Request $request): bool
    {
        $samples = [];

        $samples[] = (string) $request->path();
        $samples[] = (string) $request->getQueryString();
        $samples[] = (string) $request->getContent();
        $samples[] = json_encode($request->all(), JSON_UNESCAPED_UNICODE) ?: '';

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
}
