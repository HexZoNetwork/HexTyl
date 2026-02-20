<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\RootApplication\RootApplicationController;

Route::get('/overview', [RootApplicationController::class, 'overview']);
Route::get('/servers/offline', [RootApplicationController::class, 'offlineServers']);
Route::get('/servers/quarantined', [RootApplicationController::class, 'quarantinedServers']);
Route::get('/servers/reputations', [RootApplicationController::class, 'reputations']);
Route::get('/security/settings', [RootApplicationController::class, 'securitySettings']);
Route::post('/security/settings', [RootApplicationController::class, 'setSecuritySetting']);
Route::post('/security/emergency-mode', [RootApplicationController::class, 'setEmergencyMode']);
Route::post('/security/trust-automation/run', [RootApplicationController::class, 'runTrustAutomation']);
Route::get('/security/timeline', [RootApplicationController::class, 'securityTimeline']);
Route::get('/ide/sessions/stats', [RootApplicationController::class, 'ideSessionsStats']);
Route::post('/ide/sessions/validate', [RootApplicationController::class, 'ideValidateToken']);
Route::post('/ide/sessions/revoke', [RootApplicationController::class, 'ideRevokeSessions']);
Route::get('/adaptive/overview', [RootApplicationController::class, 'adaptiveOverview']);
Route::post('/adaptive/run', [RootApplicationController::class, 'adaptiveRun']);
Route::get('/infra/topology-map', [RootApplicationController::class, 'topologyMap']);
Route::post('/security/simulate', [RootApplicationController::class, 'runSecuritySimulation']);
Route::get('/reputation-network/status', [RootApplicationController::class, 'reputationNetworkStatus']);
Route::post('/reputation-network/sync', [RootApplicationController::class, 'reputationNetworkSync']);
Route::get('/ecosystem/events', [RootApplicationController::class, 'ecosystemEvents']);
Route::get('/ecosystem/webhooks', [RootApplicationController::class, 'ecosystemWebhooks']);
Route::post('/ecosystem/webhooks', [RootApplicationController::class, 'createEcosystemWebhook']);
Route::post('/ecosystem/webhooks/{webhookId}/toggle', [RootApplicationController::class, 'toggleEcosystemWebhook']);
Route::get('/security/mode', [RootApplicationController::class, 'securityMode']);
Route::get('/threat/intel', [RootApplicationController::class, 'threatIntel']);
Route::get('/audit/timeline', [RootApplicationController::class, 'auditTimeline']);
Route::get('/health/servers', [RootApplicationController::class, 'healthScores']);
Route::get('/health/nodes', [RootApplicationController::class, 'nodeBalancer']);
Route::get('/vault/status', [RootApplicationController::class, 'secretVaultStatus']);
