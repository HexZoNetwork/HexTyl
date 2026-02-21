<?php

return [
    'activity_signature' => [
        // Enable strict verification. Keep false during rollout while old nodes still run unsigned payloads.
        'required' => filter_var(env('REMOTE_ACTIVITY_SIGNATURE_REQUIRED', true), FILTER_VALIDATE_BOOLEAN),
        // Maximum acceptable clock skew between Wings node and Panel.
        'max_clock_skew_seconds' => (int) env('REMOTE_ACTIVITY_SIGNATURE_MAX_SKEW_SECONDS', 180),
        // Replay-protection nonce cache TTL.
        'replay_window_seconds' => (int) env('REMOTE_ACTIVITY_SIGNATURE_REPLAY_WINDOW_SECONDS', 300),
    ],
];
