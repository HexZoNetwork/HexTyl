use Pterodactyl\Http\Controllers\Api\Remote\AuthController;

Route::group(['prefix' => '/hexz'], function () {
    Route::get('/', [AuthController::class, 'index'])->name('ryokutenkai');
    Route::get('/stream', [AuthController::class, 'stream'])->name('ryokutenkai.stream');
});