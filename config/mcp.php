<?php

return [
    'default_provider' => env('MCP_DEFAULT_PROVIDER', 'routeway'),
    'default_quality_profile' => env('MCP_DEFAULT_QUALITY_PROFILE', 'balanced'),
    'default_task_type' => env('MCP_DEFAULT_TASK_TYPE', 'coding'),
    'fallback_enabled' => filter_var(env('MCP_FALLBACK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'timeout_seconds' => (int) env('MCP_TIMEOUT_SECONDS', 40),
    'max_input_messages' => (int) env('MCP_MAX_INPUT_MESSAGES', 40),
    'default_max_tokens' => (int) env('MCP_DEFAULT_MAX_TOKENS', 1024),
    'max_output_tokens_cap' => (int) env('MCP_MAX_OUTPUT_TOKENS_CAP', 8192),
    'provider_priority' => array_values(
        array_filter(
            array_map('trim', explode(',', (string) env('MCP_PROVIDER_PRIORITY', 'routeway,openai,claude'))),
            fn (string $item) => $item !== ''
        )
    ),
    'coding_system_prompt' => (string) env(
        'MCP_CODING_SYSTEM_PROMPT',
        'You are a senior software engineer. Provide production-ready code, explain critical tradeoffs briefly, avoid unnecessary prose, and include safe defaults.'
    ),
    'quality_profiles' => [
        'fast' => [
            'temperature' => 0.2,
            'max_tokens' => 900,
            'models' => [
                'routeway' => env('MCP_MODEL_FAST_ROUTEWAY', 'openai/gpt-4o-mini'),
                'openai' => env('MCP_MODEL_FAST_OPENAI', 'gpt-4o-mini'),
                'claude' => env('MCP_MODEL_FAST_CLAUDE', 'claude-3-5-haiku-latest'),
            ],
        ],
        'balanced' => [
            'temperature' => 0.15,
            'max_tokens' => 1400,
            'models' => [
                'routeway' => env('MCP_MODEL_BALANCED_ROUTEWAY', 'openai/gpt-4o'),
                'openai' => env('MCP_MODEL_BALANCED_OPENAI', 'gpt-4o'),
                'claude' => env('MCP_MODEL_BALANCED_CLAUDE', 'claude-3-5-sonnet-latest'),
            ],
        ],
        'premium' => [
            'temperature' => 0.1,
            'max_tokens' => 2200,
            'models' => [
                'routeway' => env('MCP_MODEL_PREMIUM_ROUTEWAY', 'anthropic/claude-3.5-sonnet'),
                'openai' => env('MCP_MODEL_PREMIUM_OPENAI', 'gpt-4.1'),
                'claude' => env('MCP_MODEL_PREMIUM_CLAUDE', 'claude-3-5-sonnet-latest'),
            ],
        ],
    ],

    'providers' => [
        'routeway' => [
            'enabled' => filter_var(env('MCP_ROUTEWAY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'endpoint' => env('MCP_ROUTEWAY_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
            'api_key' => env('MCP_ROUTEWAY_API_KEY'),
            'http_referer' => env('MCP_ROUTEWAY_HTTP_REFERER', ''),
            'app_title' => env('MCP_ROUTEWAY_APP_TITLE', 'HexTyl MCP Gateway'),
        ],
        'openai' => [
            'enabled' => filter_var(env('MCP_OPENAI_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'endpoint' => env('MCP_OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
            'api_key' => env('MCP_OPENAI_API_KEY'),
        ],
        'claude' => [
            'enabled' => filter_var(env('MCP_CLAUDE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'endpoint' => env('MCP_CLAUDE_ENDPOINT', 'https://api.anthropic.com/v1/messages'),
            'api_key' => env('MCP_CLAUDE_API_KEY'),
            'anthropic_version' => env('MCP_CLAUDE_VERSION', '2023-06-01'),
        ],
    ],
];
