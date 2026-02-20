<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DDoS Protection Defaults
    |--------------------------------------------------------------------------
    |
    | These are fallback values. They can be overridden at runtime by
    | values in the `system_settings` table.
    |
    */
    'lockdown_mode' => env('DDOS_LOCKDOWN_MODE', false),
    'whitelist_ips' => env('DDOS_WHITELIST_IPS', ''),

    'rate_limits' => [
        // Requests/minute for non-API routes.
        'web_per_minute' => (int) env('DDOS_RATE_WEB_PER_MINUTE', 180),
        // Requests/minute for /api/* routes.
        'api_per_minute' => (int) env('DDOS_RATE_API_PER_MINUTE', 120),
        // Requests/minute for /auth/login and login checkpoints.
        'login_per_minute' => (int) env('DDOS_RATE_LOGIN_PER_MINUTE', 20),
        // Requests/minute for heavy mutating routes.
        'write_per_minute' => (int) env('DDOS_RATE_WRITE_PER_MINUTE', 40),
    ],

    // If an IP exceeds this number in 10s, mark as temporary hot source.
    'burst_threshold_10s' => (int) env('DDOS_BURST_THRESHOLD_10S', 150),
    // If an IP repeatedly hits the exact same path this many times in 10s, temp-block it.
    'repeat_path_threshold_10s' => (int) env('DDOS_REPEAT_PATH_THRESHOLD_10S', 80),
    // If an IP exceeds per-minute limits this many times in 5 minutes, temp-block it.
    'violation_threshold_5m' => (int) env('DDOS_VIOLATION_THRESHOLD_5M', 3),
    'temporary_block_minutes' => (int) env('DDOS_TEMP_BLOCK_MINUTES', 10),

    'auto_under_attack' => [
        // Auto-enable ddos_lockdown_mode when burst events spike globally.
        'enabled' => filter_var(env('DDOS_AUTO_UNDER_ATTACK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // Number of burst-triggered temp blocks observed in 30s before activating lockdown.
        'trigger_30s' => (int) env('DDOS_AUTO_UNDER_ATTACK_TRIGGER_30S', 25),
        // Prevent repeated setting writes while attack is still ongoing.
        'cooldown_minutes' => (int) env('DDOS_AUTO_UNDER_ATTACK_COOLDOWN_MINUTES', 15),
    ],
];
