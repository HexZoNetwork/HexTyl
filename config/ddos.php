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
    // Do not throttle authenticated panel users by default.
    'skip_authenticated_limits' => filter_var(env('DDOS_SKIP_AUTHENTICATED_LIMITS', true), FILTER_VALIDATE_BOOLEAN),
    // Push temporary bans into nftables blocklist if sudo root elevation is available.
    'firewall_block_enabled' => filter_var(env('DDOS_FIREWALL_BLOCK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

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
    // Hard limiter for immediate flood bursts (requests/1s/IP).
    'hard_burst_threshold_1s' => (int) env('DDOS_HARD_BURST_THRESHOLD_1S', 100),
    // Auth-probe limiter when already-authenticated clients keep hitting /auth/*.
    'auth_probe_threshold_1s' => (int) env('DDOS_AUTH_PROBE_THRESHOLD_1S', 100),
    // If an IP repeatedly hits the exact same path this many times in 10s, temp-block it.
    'repeat_path_threshold_10s' => (int) env('DDOS_REPEAT_PATH_THRESHOLD_10S', 80),
    // If an IP exceeds per-minute limits this many times in 5 minutes, temp-block it.
    'violation_threshold_5m' => (int) env('DDOS_VIOLATION_THRESHOLD_5M', 3),
    // Unauthenticated requests with suspicious/missing headers on sensitive paths in 30s.
    'suspicious_header_threshold_30s' => (int) env('DDOS_SUSPICIOUS_HEADER_THRESHOLD_30S', 20),
    'direct_ip_host_protection' => [
        // Treat requests using raw IP in Host header (instead of domain) as high-risk probing/flooding.
        'enabled' => filter_var(env('DDOS_DIRECT_IP_HOST_PROTECTION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // Requests/30s from same IP to raw-IP host before temporary ban.
        'threshold_30s' => (int) env('DDOS_DIRECT_IP_HOST_THRESHOLD_30S', 12),
        'port_probe' => [
            // Stricter detector for raw-IP host with explicit non-80/443 port.
            'enabled' => filter_var(env('DDOS_DIRECT_IP_PORT_PROBE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            // Requests/10s from one IP before temporary ban.
            'threshold_10s' => (int) env('DDOS_DIRECT_IP_PORT_PROBE_THRESHOLD_10S', 4),
        ],
    ],
    'temporary_block_minutes' => (int) env('DDOS_TEMP_BLOCK_MINUTES', 10),

    'infrastructure_pressure_guard' => [
        // Fast request-time protection when host resource pressure spikes.
        'enabled' => filter_var(env('DDOS_INFRA_PRESSURE_GUARD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // 1m load average normalized by CPU core count.
        'load_per_core_threshold' => (float) env('DDOS_INFRA_LOAD_PER_CORE_THRESHOLD', 1.8),
        // Host memory used percentage threshold.
        'memory_used_percent_threshold' => (float) env('DDOS_INFRA_MEMORY_USED_PERCENT_THRESHOLD', 92),
        // Host disk used percentage threshold.
        'disk_used_percent_threshold' => (float) env('DDOS_INFRA_DISK_USED_PERCENT_THRESHOLD', 95),
        // Keep guard active for at least this duration once triggered.
        'hold_seconds' => (int) env('DDOS_INFRA_PRESSURE_HOLD_SECONDS', 45),
    ],

    'auto_under_attack' => [
        // Auto-enable ddos_lockdown_mode when burst events spike globally.
        'enabled' => filter_var(env('DDOS_AUTO_UNDER_ATTACK_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // Number of burst-triggered temp blocks observed in 30s before activating lockdown.
        'trigger_30s' => (int) env('DDOS_AUTO_UNDER_ATTACK_TRIGGER_30S', 25),
        // Prevent repeated setting writes while attack is still ongoing.
        'cooldown_minutes' => (int) env('DDOS_AUTO_UNDER_ATTACK_COOLDOWN_MINUTES', 15),
    ],

    'server_error_guard' => [
        // If one source IP triggers too many 5xx errors quickly, temporarily shield it.
        'enabled' => filter_var(env('DDOS_SERVER_ERROR_GUARD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // 5xx responses per minute from one IP before temporary block.
        'threshold_per_minute' => (int) env('DDOS_SERVER_ERROR_GUARD_THRESHOLD_PER_MINUTE', 8),
        // Temporary block duration in minutes.
        'block_minutes' => (int) env('DDOS_SERVER_ERROR_GUARD_BLOCK_MINUTES', 15),
    ],

    'container_lateral_guard' => [
        // Block sensitive API/login requests coming from container/private lateral networks.
        'enabled' => filter_var(env('DDOS_CONTAINER_LATERAL_GUARD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        // Common container/internal network ranges (comma separated CIDR).
        'cidrs' => env('DDOS_CONTAINER_LATERAL_GUARD_CIDRS', '172.16.0.0/12,100.64.0.0/10'),
        // Trusted source IP/CIDR that are still allowed (comma separated).
        'whitelist_ips' => env('DDOS_CONTAINER_LATERAL_GUARD_WHITELIST_IPS', ''),
        // Always block rootapplication calls from container/internal sources.
        'block_rootapplication' => filter_var(env('DDOS_CONTAINER_LATERAL_GUARD_BLOCK_ROOTAPPLICATION', true), FILTER_VALIDATE_BOOLEAN),
        // Always block login/auth paths from container/internal sources.
        'block_auth' => filter_var(env('DDOS_CONTAINER_LATERAL_GUARD_BLOCK_AUTH', true), FILTER_VALIDATE_BOOLEAN),
        // Allow container-origin API requests if Bearer token is valid PTLA/PTLC.
        'allow_valid_panel_api_keys' => filter_var(env('DDOS_CONTAINER_LATERAL_GUARD_ALLOW_VALID_PANEL_API_KEYS', true), FILTER_VALIDATE_BOOLEAN),
    ],
];
