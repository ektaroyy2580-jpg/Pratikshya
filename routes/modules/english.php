<?php

use App\Http\Controllers\English\PracticeController;
use Illuminate\Support\Facades\Route;

// ---------------- English speaking practice (module: english) ----------------
Route::middleware('auth')->prefix('english')->name('english.')->group(function () {
    Route::get('/', [PracticeController::class, 'home'])->name('home');
    Route::get('/scenarios', [PracticeController::class, 'scenarios'])->name('scenarios');
    Route::post('/sessions', [PracticeController::class, 'start'])->middleware('throttle:20,1')->name('start');
    Route::get('/sessions/{practice}', [PracticeController::class, 'room'])->name('room');
    Route::post('/sessions/{practice}/turns', [PracticeController::class, 'turn'])->middleware('throttle:40,1')->name('turn');
    Route::post('/sessions/{practice}/mode', [PracticeController::class, 'mode'])->middleware('throttle:20,1')->name('mode');
    Route::post('/sessions/{practice}/complete', [PracticeController::class, 'complete'])->middleware('throttle:20,1')->name('complete');
    Route::get('/sessions/{practice}/summary', [PracticeController::class, 'summary'])->name('summary');
});
