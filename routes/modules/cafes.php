<?php

use App\Http\Controllers\Cafes\CafeController;
use Illuminate\Support\Facades\Route;

// ---------------- Café hub: one page per café/restaurant (menu & pre-order, split a bill, your order, ride) ----------------
Route::middleware('auth')->group(function () {
    Route::get('/cafes/{place}', [CafeController::class, 'show'])->whereNumber('place')->name('cafes.show');
});

// Photo credits are public: attribution must be reachable without an account.
Route::get('/explore/credits', [CafeController::class, 'credits'])->name('explore.credits');
