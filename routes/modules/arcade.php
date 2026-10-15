<?php

use App\Http\Controllers\Arcade\ArcadeController;
use App\Services\Arcade\ArcadeManager;
use Illuminate\Support\Facades\Route;

// Arcade: solo games (registry in config/games.php → arcade). Games never affect queue position.
Route::middleware('auth')->prefix('games/play')->name('arcade.')->group(function () {
    Route::get('/', [ArcadeController::class, 'index'])->name('index');

    // One named route per game so the hub can link with route($entry['route']) and no params.
    foreach (array_keys(ArcadeManager::GAMES) as $key) {
        Route::get('/'.$key, [ArcadeController::class, 'play'])->defaults('game', $key)->name('play.'.$key);
    }
    Route::get('/{game}', [ArcadeController::class, 'play'])->whereIn('game', array_keys(ArcadeManager::GAMES))->name('play');

    Route::post('/{game}/start', [ArcadeController::class, 'start'])->whereIn('game', array_keys(ArcadeManager::GAMES))
        ->middleware('throttle:30,1')->name('start');
    Route::post('/session/{arcadeSession}/action', [ArcadeController::class, 'action'])->whereNumber('arcadeSession')
        ->middleware('throttle:240,1')->name('action');
    Route::post('/session/{arcadeSession}/finish', [ArcadeController::class, 'finish'])->whereNumber('arcadeSession')
        ->middleware('throttle:30,1')->name('finish');
});
