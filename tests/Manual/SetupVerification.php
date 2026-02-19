<?php

use Pterodactyl\Models\User;
use Illuminate\Http\Request;
use Pterodactyl\Services\Auth\SessionRiskService;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Setup & Risk Verification Script
|--------------------------------------------------------------------------
| usage: php artisan tinker tests/Manual/SetupVerification.php
*/

echo "Starting Setup & Risk Verification...\n";

// 1. Verify Session Risk Service
echo "\n[Test 1] Session Risk Service (New IP Detection)...\n";

$user = User::first(); // Root
if (!$user) {
    echo "FAIL: No user found to test risk service.\n";
    exit;
}

$service = app(SessionRiskService::class);

// Mock Request from new IP
$request = Request::create('/auth/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.99']);

// Ensure no previous log exists for this IP
DB::table('activity_logs')->where('actor_id', $user->id)->where('ip', '10.0.0.99')->delete();

$isRisk = $service->handle($user, $request);

if ($isRisk) {
    echo "PASS: Detected new IP '10.0.0.99' as risk.\n";
} else {
    echo "FAIL: Did NOT detect new IP as risk.\n";
}

// 2. Verify Known IP
// Insert a dummy log
DB::table('activity_logs')->insert([
    'actor_id' => $user->id,
    'ip' => '10.0.0.99',
    'event' => 'auth:login',
    'timestamp' => now(),
    'description' => 'Test Log' // Added description field
]);

$isRisk = $service->handle($user, $request);

if (!$isRisk) {
    echo "PASS: Recognized known IP '10.0.0.99'.\n";
} else {
    echo "FAIL: Flagged known IP as risk.\n";
}

echo "\nVerification Complete.\n";
