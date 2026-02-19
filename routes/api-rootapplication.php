<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\RootApplication\RootApplicationController;

Route::get('/overview', [RootApplicationController::class, 'overview']);
Route::get('/servers/offline', [RootApplicationController::class, 'offlineServers']);
Route::get('/servers/quarantined', [RootApplicationController::class, 'quarantinedServers']);
Route::get('/servers/reputations', [RootApplicationController::class, 'reputations']);
Route::get('/security/settings', [RootApplicationController::class, 'securitySettings']);
Route::post('/security/settings', [RootApplicationController::class, 'setSecuritySetting']);
Route::get('/security/mode', [RootApplicationController::class, 'securityMode']);
Route::get('/threat/intel', [RootApplicationController::class, 'threatIntel']);
Route::get('/audit/timeline', [RootApplicationController::class, 'auditTimeline']);
Route::get('/health/servers', [RootApplicationController::class, 'healthScores']);
Route::get('/health/nodes', [RootApplicationController::class, 'nodeBalancer']);
Route::get('/vault/status', [RootApplicationController::class, 'secretVaultStatus']);
