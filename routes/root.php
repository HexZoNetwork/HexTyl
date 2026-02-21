<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Root\RootPanelController;
use Pterodactyl\Http\Middleware\AdminAuthenticate;

/*
|--------------------------------------------------------------------------
| Root Panel Routes
|--------------------------------------------------------------------------
|
| These routes are only accessible to the root user (isRoot() === true).
| Authorization is enforced inside RootPanelController::requireRoot().
|
*/

Route::prefix('/root')->middleware(['admin'])->group(function () {

    // Dashboard
    Route::get('/', [RootPanelController::class, 'index'])->name('root.dashboard');
    Route::get('/security', [RootPanelController::class, 'security'])->name('root.security');
    Route::post('/security/settings', [RootPanelController::class, 'updateSecuritySettings'])->name('root.security.settings');
    Route::post('/security/emergency-mode', [RootPanelController::class, 'toggleEmergencyMode'])->name('root.security.emergency_mode');
    Route::post('/security/trust-automation/run', [RootPanelController::class, 'runTrustAutomation'])->name('root.security.trust_automation.run');
    Route::post('/security/simulate', [RootPanelController::class, 'simulateAbuse'])->name('root.security.simulate');
    Route::get('/threat-intelligence', [RootPanelController::class, 'threatIntelligence'])->name('root.threat_intelligence');
    Route::get('/audit-timeline', [RootPanelController::class, 'auditTimeline'])->name('root.audit_timeline');
    Route::get('/health-center', [RootPanelController::class, 'healthCenter'])->name('root.health_center');

    // Users
    Route::get('/users', [RootPanelController::class, 'users'])->name('root.users');
    Route::post('/users/{user}/toggle-suspension', [RootPanelController::class, 'toggleUserSuspension'])
        ->name('root.users.toggle_suspension');

    // Servers
    Route::get('/servers', [RootPanelController::class, 'servers'])->name('root.servers');
    Route::delete('/servers/{server}', [RootPanelController::class, 'deleteServer'])->name('root.servers.delete');
    Route::post('/servers/delete-offline', [RootPanelController::class, 'deleteOfflineServers'])->name('root.servers.delete_offline');
    Route::post('/servers/delete-selected-offline', [RootPanelController::class, 'deleteSelectedOfflineServers'])->name('root.servers.delete_selected_offline');

    // Nodes
    Route::get('/nodes', [RootPanelController::class, 'nodes'])->name('root.nodes');

    // API Keys (system-wide)
    Route::get('/api-keys', [RootPanelController::class, 'apiKeys'])->name('root.api_keys');
    Route::delete('/api-keys/{identifier}', [RootPanelController::class, 'revokeKey'])->name('root.api_keys.revoke');
});
