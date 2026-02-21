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
        // release_binary|repo_source
        'install_mode' => (string) env('WINGS_BOOTSTRAP_INSTALL_MODE', 'repo_source'),
        'repo_url' => (string) env('WINGS_BOOTSTRAP_REPO_URL', 'https://github.com/hexzo/hextyl.git'),
        'repo_ref' => (string) env('WINGS_BOOTSTRAP_REPO_REF', 'main'),
        // Supports placeholders: {arch}, {version}
        'binary_url_template' => (string) env('WINGS_BOOTSTRAP_BINARY_URL_TEMPLATE', 'https://github.com/hexzo/HexWings/releases/latest/download/hexwings_linux_{arch}'),
        'binary_version' => (string) env('WINGS_BOOTSTRAP_BINARY_VERSION', 'latest'),
        // Optional SHA256 verification (leave empty to skip).
        'binary_sha256_amd64' => (string) env('WINGS_BOOTSTRAP_BINARY_SHA256_AMD64', ''),
        'binary_sha256_arm64' => (string) env('WINGS_BOOTSTRAP_BINARY_SHA256_ARM64', ''),
        // Safety guard for SSH bootstrap target.
        'allow_private_targets' => filter_var(env('WINGS_BOOTSTRAP_ALLOW_PRIVATE_TARGETS', false), FILTER_VALIDATE_BOOLEAN),
    ],
];
