<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index');
Route::redirect('/r', '/root', 302)->name('shortcut.root');
Route::redirect('/a', '/admin', 302)->name('shortcut.admin');
Route::get('/favicon.ico', function () {
    return redirect('/favicons/favicon.ico', 302);
})->name('favicon.redirect');
Route::get('/doc', [Base\DocumentationController::class, 'index'])->name('docs.index');
Route::get('/documentation', [Base\DocumentationController::class, 'index'])->name('docs.documentation');
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth.session', RequireTwoFactorAuthentication::class]);

Route::get('/{react?}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(api|auth|admin|daemon|root)(?:/|$)).*')
    ->fallback();
