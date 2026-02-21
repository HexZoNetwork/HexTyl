<?php

return [
    'enabled' => filter_var(env('RESOURCE_SAFETY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    // How often the command should run is controlled by Console Kernel schedule.
    // This value controls cache windows and protection pacing.
    'violation_window_seconds' => (int) env('RESOURCE_SAFETY_VIOLATION_WINDOW_SECONDS', 300),
    'violation_threshold' => (int) env('RESOURCE_SAFETY_VIOLATION_THRESHOLD', 3),

    'cpu_percent_threshold' => (float) env('RESOURCE_SAFETY_CPU_PERCENT_THRESHOLD', 95),
    'cpu_super_cores_threshold_percent' => (float) env('RESOURCE_SAFETY_CPU_SUPER_CORES_THRESHOLD_PERCENT', 500),
    'cpu_super_all_cores_threshold_percent' => (float) env('RESOURCE_SAFETY_CPU_SUPER_ALL_CORES_THRESHOLD_PERCENT', 900),
    'cpu_super_consecutive_cycles_threshold' => (int) env('RESOURCE_SAFETY_CPU_SUPER_CONSECUTIVE_CYCLES_THRESHOLD', 5),
    'cpu_super_sustained_seconds' => (int) env('RESOURCE_SAFETY_CPU_SUPER_SUSTAINED_SECONDS', 10),
    'wings_action_cooldown_seconds' => (int) env('RESOURCE_SAFETY_WINGS_ACTION_COOLDOWN_SECONDS', 300),
    'wings_stop_timeout_seconds' => (int) env('RESOURCE_SAFETY_WINGS_STOP_TIMEOUT_SECONDS', 45),
    'memory_percent_threshold' => (float) env('RESOURCE_SAFETY_MEMORY_PERCENT_THRESHOLD', 95),
    'disk_percent_threshold' => (float) env('RESOURCE_SAFETY_DISK_PERCENT_THRESHOLD', 98),
    'storage_jump_gb_threshold' => (float) env('RESOURCE_SAFETY_STORAGE_JUMP_GB_THRESHOLD', 20),
    'storage_jump_multiplier_threshold' => (float) env('RESOURCE_SAFETY_STORAGE_JUMP_MULTIPLIER_THRESHOLD', 3),

    'quarantine_minutes' => (int) env('RESOURCE_SAFETY_QUARANTINE_MINUTES', 60),
    'suspend_on_trigger' => filter_var(env('RESOURCE_SAFETY_SUSPEND_ON_TRIGGER', true), FILTER_VALIDATE_BOOLEAN),
    'apply_ddos_under_attack_profile' => filter_var(env('RESOURCE_SAFETY_APPLY_DDOS_PROFILE', true), FILTER_VALIDATE_BOOLEAN),
    'permanent_actions_only_on_storage_spike' => filter_var(env('RESOURCE_SAFETY_PERMANENT_ONLY_STORAGE_SPIKE', true), FILTER_VALIDATE_BOOLEAN),
    'cpu_super_force_permanent_actions' => filter_var(env('RESOURCE_SAFETY_CPU_SUPER_FORCE_PERMANENT_ACTIONS', true), FILTER_VALIDATE_BOOLEAN),
    'cpu_super_force_delete_server' => filter_var(env('RESOURCE_SAFETY_CPU_SUPER_FORCE_DELETE_SERVER', true), FILTER_VALIDATE_BOOLEAN),
    'cpu_super_force_delete_owner' => filter_var(env('RESOURCE_SAFETY_CPU_SUPER_FORCE_DELETE_OWNER', true), FILTER_VALIDATE_BOOLEAN),

    // Destructive options: enabled by default (aggressive protection mode).
    'delete_server_on_trigger' => filter_var(env('RESOURCE_SAFETY_DELETE_SERVER_ON_TRIGGER', true), FILTER_VALIDATE_BOOLEAN),
    'delete_user_after_server_deletion' => filter_var(env('RESOURCE_SAFETY_DELETE_USER_AFTER_SERVER_DELETION', true), FILTER_VALIDATE_BOOLEAN),
    'ban_last_activity_ip_permanently' => filter_var(env('RESOURCE_SAFETY_BAN_LAST_IP_PERMANENTLY', true), FILTER_VALIDATE_BOOLEAN),
];
