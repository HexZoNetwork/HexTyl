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
        $providerHint = strtolower(trim((string) ($payload['provider'] ?? '')));
        $modelHint = trim((string) ($payload['model'] ?? ''));
        $qualityProfile = strtolower(trim((string) ($payload['quality_profile'] ?? config('mcp.default_quality_profile', 'balanced'))));
        if ($qualityProfile === '') {
            $qualityProfile = 'balanced';
        }
        $taskType = strtolower(trim((string) ($payload['task_type'] ?? config('mcp.default_task_type', 'coding'))));
        if (!in_array($taskType, ['coding', 'general'], true)) {
            $taskType = 'coding';
        }
        $fallbackEnabled = array_key_exists('fallback', $payload)
            ? filter_var($payload['fallback'], FILTER_VALIDATE_BOOLEAN)
            : (bool) config('mcp.fallback_enabled', true);

        $messages = $this->sanitizeMessages((array) ($payload['messages'] ?? []), $taskType);
        if ($messages === []) {
            throw new RuntimeException('At least one message is required.');
        }

        $timeout = max(5, min(120, (int) config('mcp.timeout_seconds', 40)));
        $maxOutputCap = max(64, min(32768, (int) config('mcp.max_output_tokens_cap', 8192)));
        $defaultMaxTokens = max(64, min($maxOutputCap, (int) config('mcp.default_max_tokens', 1024)));
        $profileDefaults = (array) config("mcp.quality_profiles.{$qualityProfile}", []);
        $profileMaxTokens = (int) ($profileDefaults['max_tokens'] ?? $defaultMaxTokens);
        $maxTokens = (int) ($payload['max_tokens'] ?? $profileMaxTokens);
        $maxTokens = max(1, min($maxOutputCap, $maxTokens));
        $profileTemperature = (float) ($profileDefaults['temperature'] ?? 0.2);
        $temperature = (float) ($payload['temperature'] ?? $profileTemperature);
        $temperature = max(0.0, min(2.0, $temperature));

        $apiKeyOverride = trim((string) ($payload['api_key'] ?? ''));
        $plan = $this->buildProviderPlan($providerHint, $modelHint, $qualityProfile);

        $errors = [];
        $responseData = null;
        $usedProvider = '';
        $usedModel = '';
        foreach ($plan as $index => $item) {
            if ($index > 0 && !$fallbackEnabled) {
                break;
            }

            $provider = (string) ($item['provider'] ?? '');
            $model = (string) ($item['model'] ?? '');
            if ($provider === '' || $model === '') {
                continue;
            }

            $providerConfig = (array) config("mcp.providers.{$provider}", []);
            if ($providerConfig === [] || !((bool) ($providerConfig['enabled'] ?? false))) {
                $errors[] = "Provider '{$provider}' is unavailable.";
                continue;
            }

            $endpoint = trim((string) ($providerConfig['endpoint'] ?? ''));
            if ($endpoint === '') {
                $errors[] = "Provider '{$provider}' endpoint is not configured.";
                continue;
            }

            $apiKey = $apiKeyOverride !== '' ? $apiKeyOverride : trim((string) ($providerConfig['api_key'] ?? ''));
            if ($apiKey === '') {
                $errors[] = "Provider '{$provider}' API key is not configured.";
                continue;
            }

            try {
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
            } catch (RuntimeException $exception) {
                $errors[] = $exception->getMessage();
                $responseData = null;
                continue;
            }

            $usedProvider = $provider;
            $usedModel = $model;
            break;
        }

        if (!is_array($responseData)) {
            $detail = implode(' | ', array_slice($errors, 0, 4));
            if ($detail === '') {
                $detail = 'No available MCP provider/model candidate.';
            }

            throw new RuntimeException($detail);
        }

        $this->securityEventService->log('security:ide.mcp_proxy', [
            'actor_user_id' => $user->id,
            'server_id' => $server->id,
            'ip' => $ip,
            'risk_level' => 'low',
            'meta' => [
                'provider' => $usedProvider,
                'model' => $usedModel,
                'message_count' => count($messages),
                'quality_profile' => $qualityProfile,
                'task_type' => $taskType,
                'fallback_enabled' => $fallbackEnabled,
                'fallback_attempts' => max(0, count($plan) - 1),
            ],
        ]);

        return [
            'provider' => $usedProvider,
            'model' => (string) ($responseData['model'] ?? $usedModel),
            'content' => (string) ($responseData['content'] ?? ''),
            'usage' => (array) ($responseData['usage'] ?? []),
            'quality_profile' => $qualityProfile,
            'task_type' => $taskType,
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

    private function sanitizeMessages(array $messages, string $taskType): array
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

        if ($taskType === 'coding' && !$this->hasSystemMessage($normalized)) {
            $codingSystemPrompt = trim((string) config('mcp.coding_system_prompt', ''));
            if ($codingSystemPrompt !== '') {
                array_unshift($normalized, [
                    'role' => 'system',
                    'content' => $codingSystemPrompt,
                ]);
            }
        }

        return $normalized;
    }

    private function hasSystemMessage(array $messages): bool
    {
        foreach ($messages as $message) {
            if (($message['role'] ?? '') === 'system') {
                return true;
            }
        }

        return false;
    }

    private function buildProviderPlan(string $providerHint, string $modelHint, string $qualityProfile): array
    {
        $priority = (array) config('mcp.provider_priority', ['routeway', 'openai', 'claude']);
        $profiles = (array) config("mcp.quality_profiles.{$qualityProfile}.models", []);
        $defaultProvider = (string) config('mcp.default_provider', 'routeway');

        $orderedProviders = [];
        if ($providerHint !== '') {
            $orderedProviders[] = $providerHint;
        } else {
            $orderedProviders = $priority;
            if ($orderedProviders === []) {
                $orderedProviders = [$defaultProvider];
            }
        }

        $plan = [];
        foreach ($orderedProviders as $provider) {
            $provider = strtolower(trim((string) $provider));
            if ($provider === '' || !in_array($provider, ['routeway', 'openai', 'claude'], true)) {
                continue;
            }

            $model = $modelHint !== '' ? $modelHint : trim((string) ($profiles[$provider] ?? ''));
            if ($model === '') {
                continue;
            }

            $plan[] = [
                'provider' => $provider,
                'model' => $model,
            ];
        }

        // Deduplicate provider/model pairs while preserving order.
        $seen = [];
        $unique = [];
        foreach ($plan as $item) {
            $key = $item['provider'] . '|' . $item['model'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $item;
        }

        return $unique;
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
