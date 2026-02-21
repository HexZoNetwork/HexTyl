<?php

return [
    'ddos' => [
        'enabled' => filter_var(env('WINGS_DDOS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'per_ip_per_minute' => (int) env('WINGS_DDOS_PER_IP_PER_MINUTE', 240),
        'per_ip_burst' => (int) env('WINGS_DDOS_PER_IP_BURST', 60),
        'global_per_minute' => (int) env('WINGS_DDOS_GLOBAL_PER_MINUTE', 2400),
        'global_burst' => (int) env('WINGS_DDOS_GLOBAL_BURST', 300),
        'strike_threshold' => (int) env('WINGS_DDOS_STRIKE_THRESHOLD', 12),
        'block_seconds' => (int) env('WINGS_DDOS_BLOCK_SECONDS', 600),
        'whitelist' => array_values(array_filter(array_map('trim', explode(',', (string) env('WINGS_DDOS_WHITELIST', '127.0.0.1/32,::1/128'))))),
    ],
    'bootstrap' => [
        // Repo source mode only.
        'install_mode' => 'repo_source',
        'allowed_repo_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('WINGS_BOOTSTRAP_ALLOWED_REPO_HOSTS', 'github.com'))))),
        'repo_url' => (string) env('WINGS_BOOTSTRAP_REPO_URL', 'https://github.com/hexzo/hextyl.git'),
        'repo_ref' => (string) env('WINGS_BOOTSTRAP_REPO_REF', 'main'),
        // Safety guard for SSH bootstrap target.
        'allow_private_targets' => filter_var(env('WINGS_BOOTSTRAP_ALLOW_PRIVATE_TARGETS', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
