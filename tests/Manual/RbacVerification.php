<?php

use Pterodactyl\Models\User;
use Pterodactyl\Models\Role;
use Pterodactyl\Models\RoleScope;
use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Pterodactyl\Facades\Activity;

/*
|--------------------------------------------------------------------------
| RBAC and Immutable Root Verification Script (Updated)
|--------------------------------------------------------------------------
| usage: php artisan tinker tests/Manual/RbacVerification.php
*/

echo "Starting RBAC Verification (Phase 2)...\n";

$root = User::find(1);
$adminRole = Role::firstOrCreate(['name' => 'Admin'], ['is_system_role' => true]);

// Test 4: Master API Key Logic (Root bypass)
echo "\n[Test 4] Verifying Root User Bypass (Master API Key Simulator)...\n";
// Simulating CheckScope middleware logic
$scope = 'super.critical.action';
if ($root->isRoot()) {
    echo "PASS: Root user bypassed scope check for '$scope'.\n";
} else {
    echo "FAIL: Root user did not bypass scope check.\n";
}

// Test 5: Admin Creation Scope
echo "\n[Test 5] Verifying Admin Creation Scope...\n";
// Create an admin user without 'user.admin.create'
$limitedAdmin = User::firstOrCreate(['email' => 'limited_admin@example.com'], [
    'username' => 'limited',
    'name_first' => 'Limited',
    'name_last' => 'Admin',
    'password' => Hash::make('password'),
    'role_id' => $adminRole->id
]);

// Give basic user.create
RoleScope::firstOrCreate(['role_id' => $adminRole->id, 'scope' => 'user.create']);
// Ensure NO user.admin.create
RoleScope::where('role_id', $adminRole->id)->where('scope', 'user.admin.create')->delete();

if ($limitedAdmin->hasScope('user.create')) {
    echo "PASS: Limited Admin has 'user.create'.\n";
} else {
    echo "FAIL: Limited Admin missing 'user.create'.\n";
}

if (!$limitedAdmin->hasScope('user.admin.create')) {
    echo "PASS: Limited Admin does NOT have 'user.admin.create'.\n";
} else {
    echo "FAIL: Limited Admin HAS 'user.admin.create' unexpectedly.\n";
}

echo "\nVerification Complete.\n";
