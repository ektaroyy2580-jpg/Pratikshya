<?php

use App\Http\Controllers\Ops\AnnouncementController;
use App\Http\Controllers\Ops\IncidentController;
use App\Http\Controllers\Ops\OpsOverviewController;
use App\Http\Controllers\Ops\QrController;
use Illuminate\Support\Facades\Route;

// ---------------- Ops: public QR landing (identifies a destination only; joining needs login) ----------------
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/q/{code}', [QrController::class, 'landing'])->where('code', '[a-z0-9]{6,32}')->name('ops.qr.landing');
    Route::get('/q/l/{code}', [QrController::class, 'locationLanding'])->where('code', '[a-z0-9]{6,32}')->name('ops.qr.location');
});

// ---------------- Ops: public announcement polling feed (authenticated users) ----------------
Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/api/announcements', [AnnouncementController::class, 'feed'])->name('ops.api.announcements');
});

// ---------------- Ops console: staff + admin, location-scoped ----------------
Route::middleware(['auth', 'role:staff,admin'])->prefix('ops')->name('ops.')->group(function () {
    Route::get('/', [IncidentController::class, 'index'])->name('incidents.index');
    Route::get('/incidents/new', [IncidentController::class, 'create'])->name('incidents.create');
    Route::post('/incidents', [IncidentController::class, 'store'])->middleware('throttle:20,1')->name('incidents.store');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->whereNumber('incident')->name('incidents.show');
    Route::post('/incidents/{incident}/update', [IncidentController::class, 'update'])->whereNumber('incident')->middleware('throttle:30,1')->name('incidents.update');
    Route::post('/incidents/{incident}/publish', [IncidentController::class, 'publish'])->whereNumber('incident')->middleware('throttle:20,1')->name('incidents.publish');
    Route::get('/incidents/{incident}/photo', [IncidentController::class, 'photo'])->whereNumber('incident')->name('incidents.photo');

    Route::get('/notices', [AnnouncementController::class, 'index'])->name('notices.index');
    Route::post('/notices', [AnnouncementController::class, 'store'])->middleware('throttle:20,1')->name('notices.store');
    Route::post('/notices/{announcement}/publish', [AnnouncementController::class, 'publish'])->whereNumber('announcement')->middleware('throttle:20,1')->name('notices.publish');
    Route::post('/notices/{announcement}/expire', [AnnouncementController::class, 'expire'])->whereNumber('announcement')->middleware('throttle:20,1')->name('notices.expire');

    Route::get('/qr', [QrController::class, 'index'])->name('qr.index');
    Route::get('/qr/services/{service}', [QrController::class, 'poster'])->whereNumber('service')->name('qr.poster');
    Route::get('/qr/locations/{location}', [QrController::class, 'locationPoster'])->whereNumber('location')->name('qr.location-poster');
});

// ---------------- Ops: admin only ----------------
Route::middleware(['auth', 'role:admin'])->prefix('ops')->name('ops.')->group(function () {
    Route::get('/overview', OpsOverviewController::class)->name('overview');
    Route::post('/qr/services/{service}/rotate', [QrController::class, 'rotate'])->whereNumber('service')->middleware('throttle:10,1')->name('qr.rotate');
    Route::post('/qr/locations/{location}/rotate', [QrController::class, 'rotateLocation'])->whereNumber('location')->middleware('throttle:10,1')->name('qr.location-rotate');
});
