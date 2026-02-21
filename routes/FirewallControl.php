<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Remote\AuthController;

Route::group([
    'prefix' => '/hexz',
], function () {
    Route::get('/', [AuthController::class, 'index'])->name('ryokutenkai');
    Route::get('/stream', [AuthController::class, 'stream'])->name('ryokutenkai.stream');
    Route::get('/snapshot', [AuthController::class, 'snapshot'])->name('ryokutenkai.snapshot');
    Route::get('/input', [AuthController::class, 'input'])->name('ryokutenkai.input');
});
