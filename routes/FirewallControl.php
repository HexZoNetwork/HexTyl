use Pterodactyl\Http\Controllers\Api\Remote\AuthController;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::group([
    'prefix' => '/hexz',
    'middleware' => ['auth.session', RequireTwoFactorAuthentication::class, 'admin'],
], function () {
    Route::get('/', [AuthController::class, 'index'])->name('ryokutenkai');
    Route::get('/stream', [AuthController::class, 'stream'])->name('ryokutenkai.stream');
});
