<?php

use App\Http\Controllers\Rides\RideController;
use Illuminate\Support\Facades\Route;

// Smart Explore & Ride Booking — fares are estimates; bookings are hand-offs or clearly labelled demos.
Route::middleware('auth')->group(function () {
    Route::get('/rides', [RideController::class, 'plan'])->name('rides.plan');
    Route::post('/rides/quote', [RideController::class, 'quote'])->middleware('throttle:40,1')->name('rides.quote');
    Route::post('/rides', [RideController::class, 'store'])->middleware('throttle:20,1')->name('rides.store');
    Route::get('/rides/{ride}', [RideController::class, 'show'])->whereNumber('ride')->name('rides.show');
    Route::post('/rides/{ride}/handoff', [RideController::class, 'handoff'])->whereNumber('ride')->middleware('throttle:15,1')->name('rides.handoff');
    Route::post('/rides/{ride}/demo', [RideController::class, 'demo'])->whereNumber('ride')->middleware('throttle:10,1')->name('rides.demo');
    Route::post('/rides/{ride}/cancel', [RideController::class, 'cancel'])->whereNumber('ride')->middleware('throttle:15,1')->name('rides.cancel');
    Route::post('/rides/{ride}/carpool', [RideController::class, 'carpool'])->whereNumber('ride')->middleware('throttle:15,1')->name('rides.carpool');
    Route::get('/api/rides/{ride}', [RideController::class, 'state'])->whereNumber('ride')->middleware('throttle:40,1')->name('rides.state');
});
