<?php

use App\Http\Controllers\Explore\ExploreController;
use App\Http\Controllers\Explore\HospitalController;
use App\Http\Controllers\Explore\PreferenceController;
use Illuminate\Support\Facades\Route;

// ---------------- Explore: nearby places, waiting-time-aware recommendations, hospital assistance ----------------
Route::middleware('auth')->prefix('explore')->name('explore.')->group(function () {
    Route::get('/', [ExploreController::class, 'index'])->name('index');
    Route::get('/places/{place}', [ExploreController::class, 'place'])->whereNumber('place')->name('place');
    Route::get('/api/recommendations', [ExploreController::class, 'recommendations'])->middleware('throttle:60,1')->name('recommendations');
    Route::get('/api/places/{place}/travel', [ExploreController::class, 'travel'])->whereNumber('place')->middleware('throttle:60,1')->name('travel');

    Route::post('/places/{place}/dismiss', [PreferenceController::class, 'dismiss'])->whereNumber('place')->middleware('throttle:30,1')->name('dismiss');
    Route::delete('/dismissals/{dismissal}', [PreferenceController::class, 'undismiss'])->whereNumber('dismissal')->middleware('throttle:30,1')->name('undismiss');
    Route::get('/preferences', [PreferenceController::class, 'edit'])->name('preferences');
    Route::post('/preferences', [PreferenceController::class, 'update'])->middleware('throttle:20,1')->name('preferences.update');

    Route::get('/hospital/{location}', [HospitalController::class, 'show'])->whereNumber('location')->name('hospital');
});
