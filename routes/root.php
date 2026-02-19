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
    Route::post('/security/simulate', [RootPanelController::class, 'simulateAbuse'])->name('root.security.simulate');

    // Users
    Route::get('/users', [RootPanelController::class, 'users'])->name('root.users');
    Route::post('/users/{user}/toggle-suspension', [RootPanelController::class, 'toggleUserSuspension'])
        ->name('root.users.toggle_suspension');

    // Servers
    Route::get('/servers', [RootPanelController::class, 'servers'])->name('root.servers');
    Route::delete('/servers/{server}', [RootPanelController::class, 'deleteServer'])->name('root.servers.delete');

    // Nodes
    Route::get('/nodes', [RootPanelController::class, 'nodes'])->name('root.nodes');

    // API Keys (system-wide)
    Route::get('/api-keys', [RootPanelController::class, 'apiKeys'])->name('root.api_keys');
    Route::delete('/api-keys/{identifier}', [RootPanelController::class, 'revokeKey'])->name('root.api_keys.revoke');
});
