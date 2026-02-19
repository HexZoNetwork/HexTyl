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
    'temporary_block_minutes' => (int) env('DDOS_TEMP_BLOCK_MINUTES', 10),
];

