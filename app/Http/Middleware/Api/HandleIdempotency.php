<?php

namespace Pterodactyl\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotency
{
    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->isMethod('POST')) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (empty($key)) {
            return $next($request);
        }

        $user = $request->user();
        $identifier = $user ? $user->id : $request->ip();
        $cacheKey = "idempotency:{$identifier}:{$key}";

        if (Cache::has($cacheKey)) {
            $data = Cache::get($cacheKey);

            return response()->json($data['content'], $data['status'], $data['headers'] ?? []);
        }

        $response = $next($request);

        if ($response->isSuccessful()) {
            $content = json_decode($response->getContent(), true);
            // Only cache valid JSON responses
            if (json_last_error() === JSON_ERROR_NONE) {
                Cache::put($cacheKey, [
                    'content' => $content,
                    'status' => $response->getStatusCode(),
                    'headers' => ['X-Idempotency-Hit' => 'true'],
                ], now()->addHours(24));
            }
        }

        return $response;
    }
}
