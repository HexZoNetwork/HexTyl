<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Pterodactyl\Http\Controllers\Root\RootPanelController;
use Pterodactyl\Models\User;

/*
|--------------------------------------------------------------------------
| Root Panel Routes
|--------------------------------------------------------------------------
|
| These routes are only accessible to the root user (isRoot() === true).
| Authorization is enforced by the "root" middleware and controller checks.
|
*/

Route::prefix('/root')->middleware(['root'])->group(function () {

    // Dashboard
    Route::get('/', [RootPanelController::class, 'index'])->name('root.dashboard');
    Route::get('/security', [RootPanelController::class, 'security'])->name('root.security');
    Route::get('/quickstart', [RootPanelController::class, 'quickstart'])->name('root.quickstart');
    Route::post('/quickstart/settings', [RootPanelController::class, 'updateQuickstartSettings'])->name('root.quickstart.settings');
    Route::post('/security/settings', [RootPanelController::class, 'updateSecuritySettings'])->name('root.security.settings');
    Route::post('/security/emergency-mode', [RootPanelController::class, 'toggleEmergencyMode'])->name('root.security.emergency_mode');
    Route::post('/security/trust-automation/run', [RootPanelController::class, 'runTrustAutomation'])->name('root.security.trust_automation.run');
    Route::post('/security/simulate', [RootPanelController::class, 'simulateAbuse'])->name('root.security.simulate');
    Route::get('/threat-intelligence', [RootPanelController::class, 'threatIntelligence'])->name('root.threat_intelligence');
    Route::get('/audit-timeline', [RootPanelController::class, 'auditTimeline'])->name('root.audit_timeline');
    Route::get('/health-center', [RootPanelController::class, 'healthCenter'])->name('root.health_center');

    // Users
    Route::get('/users', [RootPanelController::class, 'users'])->name('root.users');
    Route::post('/users/create-tester', [RootPanelController::class, 'createTester'])->name('root.users.create_tester');
    Route::post('/users/{user}/delete', [RootPanelController::class, 'deleteUser'])->name('root.users.delete');
    Route::get('/users/{user:id}/quick-server', [RootPanelController::class, 'createQuickServer'])->name('root.users.quick_server.get');
    Route::post('/users/{user}/quick-server', [RootPanelController::class, 'createQuickServer'])->name('root.users.quick_server');
    Route::post('/users/{user}/toggle-suspension', [RootPanelController::class, 'toggleUserSuspension'])
        ->name('root.users.toggle_suspension');

    // Servers
    Route::get('/servers', [RootPanelController::class, 'servers'])->name('root.servers');
    Route::post('/servers/{server}/delete', [RootPanelController::class, 'deleteServer'])->name('root.servers.delete_post');
    Route::delete('/servers/{server}', [RootPanelController::class, 'deleteServer'])->name('root.servers.delete');
    Route::post('/servers/delete-offline', [RootPanelController::class, 'deleteOfflineServers'])->name('root.servers.delete_offline');
    Route::post('/servers/delete-selected-offline', [RootPanelController::class, 'deleteSelectedOfflineServers'])->name('root.servers.delete_selected_offline');

    // Nodes
    Route::get('/nodes', [RootPanelController::class, 'nodes'])->name('root.nodes');

    // API Keys (system-wide)
    Route::get('/api-keys', [RootPanelController::class, 'apiKeys'])->name('root.api_keys');
    Route::delete('/api-keys/{identifier}', [RootPanelController::class, 'revokeKey'])->name('root.api_keys.revoke');
});

// Legacy compatibility paths used by older forms/scripts.
$legacyToggleSuspension = function (Request $request, RootPanelController $controller, ?int $user = null) {
    $candidate = $user
        ?? $request->input('user_id')
        ?? $request->query('user_id')
        ?? $request->input('user')
        ?? $request->query('user')
        ?? $request->input('id')
        ?? $request->query('id');

    if (!is_numeric($candidate) || (int) $candidate < 1) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'Missing or invalid user id for suspension toggle.',
            ], 422);
        }

        return redirect()->route('root.users')
            ->with('error', 'Toggle suspension failed: missing or invalid user id.');
    }

    $userId = (int) $candidate;
    $target = User::query()->find($userId);
    if (!$target) {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => 'User not found.',
            ], 404);
        }

        return redirect()->route('root.users')
            ->with('error', "Toggle suspension failed: user #{$userId} not found.");
    }

    return $controller->toggleUserSuspension($request, $target);
};

Route::match(['GET', 'POST', 'PUT', 'PATCH'], '/toggle-suspension/{user?}', $legacyToggleSuspension)
    ->middleware(['root'])
    ->name('root.users.toggle_suspension.legacy');
Route::match(['GET', 'POST', 'PUT', 'PATCH'], '/root/toggle-suspension/{user?}', $legacyToggleSuspension)
    ->middleware(['root']);
Route::match(['GET', 'POST', 'PUT', 'PATCH'], '/root/users/toggle-suspension/{user?}', $legacyToggleSuspension)
    ->middleware(['root']);
