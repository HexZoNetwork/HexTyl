<?php

namespace Pterodactyl\Services\Ide;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Security\SecurityEventService;
use RuntimeException;

class McpGatewayService
{
    public function __construct(private SecurityEventService $securityEventService)
    {
    }

    public function proxy(array $payload, Server $server, User $user, string $ip): array
    {
        $provider = strtolower((string) ($payload['provider'] ?? config('mcp.default_provider', 'routeway')));
        $model = trim((string) ($payload['model'] ?? ''));
        if ($model === '') {
            throw new RuntimeException('Model is required.');
        }

        $messages = $this->sanitizeMessages((array) ($payload['messages'] ?? []));
        if ($messages === []) {
            throw new RuntimeException('At least one message is required.');
        }

        $providerConfig = (array) config("mcp.providers.{$provider}", []);
        if ($providerConfig === []) {
            throw new RuntimeException("Unsupported MCP provider: {$provider}.");
        }

        if (!((bool) ($providerConfig['enabled'] ?? false))) {
            throw new RuntimeException("MCP provider '{$provider}' is disabled by system policy.");
        }

        $endpoint = trim((string) ($providerConfig['endpoint'] ?? ''));
        if ($endpoint === '') {
            throw new RuntimeException("MCP provider '{$provider}' endpoint is not configured.");
        }

        $apiKey = trim((string) ($payload['api_key'] ?? ''));
        if ($apiKey === '') {
            $apiKey = trim((string) ($providerConfig['api_key'] ?? ''));
        }
        if ($apiKey === '') {
            throw new RuntimeException("MCP provider '{$provider}' API key is not configured.");
        }

        $timeout = max(5, min(120, (int) config('mcp.timeout_seconds', 40)));
        $maxOutputCap = max(64, min(32768, (int) config('mcp.max_output_tokens_cap', 8192)));
        $defaultMaxTokens = max(64, min($maxOutputCap, (int) config('mcp.default_max_tokens', 1024)));
        $maxTokens = (int) ($payload['max_tokens'] ?? $defaultMaxTokens);
        $maxTokens = max(1, min($maxOutputCap, $maxTokens));
        $temperature = (float) ($payload['temperature'] ?? 0.7);
        $temperature = max(0.0, min(2.0, $temperature));

        $responseData = match ($provider) {
            'openai', 'routeway' => $this->callOpenAiCompatible(
                $provider,
                $endpoint,
                $apiKey,
                $model,
                $messages,
                $temperature,
                $maxTokens,
                $providerConfig,
                $timeout
            ),
            'claude' => $this->callClaude(
                $endpoint,
                $apiKey,
                $model,
                $messages,
                $temperature,
                $maxTokens,
                $providerConfig,
                $timeout
            ),
            default => throw new RuntimeException("Unsupported MCP provider: {$provider}."),
        };

        $this->securityEventService->log('security:ide.mcp_proxy', [
            'actor_user_id' => $user->id,
            'server_id' => $server->id,
            'ip' => $ip,
            'risk_level' => 'low',
            'meta' => [
                'provider' => $provider,
                'model' => $model,
                'message_count' => count($messages),
            ],
        ]);

        return [
            'provider' => $provider,
            'model' => (string) ($responseData['model'] ?? $model),
            'content' => (string) ($responseData['content'] ?? ''),
            'usage' => (array) ($responseData['usage'] ?? []),
        ];
    }

    private function callOpenAiCompatible(
        string $provider,
        string $endpoint,
        string $apiKey,
        string $model,
        array $messages,
        float $temperature,
        int $maxTokens,
        array $providerConfig,
        int $timeout
    ): array {
        $requestBody = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ];

        $headers = ['Authorization' => 'Bearer ' . $apiKey];
        if ($provider === 'routeway') {
            $referer = trim((string) ($providerConfig['http_referer'] ?? ''));
            if ($referer !== '') {
                $headers['HTTP-Referer'] = $referer;
            }

            $title = trim((string) ($providerConfig['app_title'] ?? ''));
            if ($title !== '') {
                $headers['X-Title'] = $title;
            }
        }

        $response = Http::withHeaders($headers)
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->post($endpoint, $requestBody);

        if ($response->failed()) {
            throw new RuntimeException($this->formatGatewayError($provider, $response->status(), (array) $response->json()));
        }

        $json = (array) $response->json();
        $choices = (array) ($json['choices'] ?? []);
        $choice = (array) Arr::first($choices, []);
        $message = (array) ($choice['message'] ?? []);
        $contentRaw = $message['content'] ?? '';
        $content = is_array($contentRaw)
            ? trim(collect($contentRaw)->map(fn ($part) => (string) ($part['text'] ?? ''))->implode(''))
            : trim((string) $contentRaw);

        return [
            'model' => (string) ($json['model'] ?? $model),
            'content' => $content,
            'usage' => (array) ($json['usage'] ?? []),
        ];
    }

    private function callClaude(
        string $endpoint,
        string $apiKey,
        string $model,
        array $messages,
        float $temperature,
        int $maxTokens,
        array $providerConfig,
        int $timeout
    ): array {
        $systemPrompts = [];
        $anthropicMessages = [];
        foreach ($messages as $message) {
            $role = (string) ($message['role'] ?? 'user');
            $content = (string) ($message['content'] ?? '');

            if ($role === 'system') {
                $systemPrompts[] = $content;
                continue;
            }

            $anthropicMessages[] = [
                'role' => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $content,
            ];
        }

        if ($anthropicMessages === []) {
            $anthropicMessages[] = ['role' => 'user', 'content' => 'Hello'];
        }

        $requestBody = [
            'model' => $model,
            'messages' => $anthropicMessages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];
        if ($systemPrompts !== []) {
            $requestBody['system'] = implode("\n\n", $systemPrompts);
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => (string) ($providerConfig['anthropic_version'] ?? '2023-06-01'),
        ])
            ->acceptJson()
            ->asJson()
            ->timeout($timeout)
            ->post($endpoint, $requestBody);

        if ($response->failed()) {
            throw new RuntimeException($this->formatGatewayError('claude', $response->status(), (array) $response->json()));
        }

        $json = (array) $response->json();
        $parts = (array) ($json['content'] ?? []);
        $content = trim(collect($parts)->map(fn ($part) => (string) ($part['text'] ?? ''))->implode(''));

        return [
            'model' => (string) ($json['model'] ?? $model),
            'content' => $content,
            'usage' => (array) ($json['usage'] ?? []),
        ];
    }

    private function sanitizeMessages(array $messages): array
    {
        $maxMessages = max(1, min(200, (int) config('mcp.max_input_messages', 40)));
        $messages = array_slice($messages, 0, $maxMessages);

        $normalized = [];
        foreach ($messages as $message) {
            $role = strtolower(trim((string) Arr::get($message, 'role', 'user')));
            if (!in_array($role, ['system', 'user', 'assistant'], true)) {
                continue;
            }

            $content = trim((string) Arr::get($message, 'content', ''));
            if ($content === '') {
                continue;
            }

            if (mb_strlen($content) > 16000) {
                $content = mb_substr($content, 0, 16000);
            }

            $normalized[] = [
                'role' => $role,
                'content' => $content,
            ];
        }

        return $normalized;
    }

    private function formatGatewayError(string $provider, int $status, array $json): string
    {
        $detail = trim((string) Arr::get($json, 'error.message', Arr::get($json, 'error', Arr::get($json, 'message', ''))));
        if ($detail === '') {
            $detail = 'Unknown upstream gateway error.';
        }

        return strtoupper($provider) . " gateway failed ({$status}): {$detail}";
    }
}
