<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\RootApplication\RootApplicationController;

Route::get('/overview', [RootApplicationController::class, 'overview']);
Route::get('/servers/offline', [RootApplicationController::class, 'offlineServers']);
Route::get('/servers/quarantined', [RootApplicationController::class, 'quarantinedServers']);
Route::get('/servers/reputations', [RootApplicationController::class, 'reputations']);
Route::get('/security/settings', [RootApplicationController::class, 'securitySettings']);
Route::post('/security/settings', [RootApplicationController::class, 'setSecuritySetting']);
