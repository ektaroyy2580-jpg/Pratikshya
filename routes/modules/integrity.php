<?php

use App\Http\Controllers\Integrity\AlertReviewController;
use App\Http\Controllers\Integrity\ExchangeController;
use App\Http\Controllers\Integrity\StaffIntegrityController;
use Illuminate\Support\Facades\Route;

/*
| Integrity module: Dalal AI (queue-integrity review) + Voluntary Queue Exchange.
| Alerts are admin-only. Users never see alerts. Exchanges never involve payment or points.
*/

// ---------------- Users: voluntary queue exchange ----------------
Route::middleware('auth')->group(function () {
    Route::get('/exchange', [ExchangeController::class, 'index'])->name('integrity.exchange.index');
    Route::get('/api/exchange', [ExchangeController::class, 'poll'])->middleware('throttle:30,1')->name('integrity.exchange.poll');
    Route::post('/exchange/tickets/{ticket}', [ExchangeController::class, 'store'])->whereNumber('ticket')->middleware('throttle:10,1')->name('integrity.exchange.store');
    Route::post('/exchange/{exchange}/accept', [ExchangeController::class, 'accept'])->whereNumber('exchange')->middleware('throttle:20,1')->name('integrity.exchange.accept');
    Route::post('/exchange/{exchange}/decline', [ExchangeController::class, 'decline'])->whereNumber('exchange')->middleware('throttle:20,1')->name('integrity.exchange.decline');
    Route::post('/exchange/{exchange}/cancel', [ExchangeController::class, 'cancel'])->whereNumber('exchange')->middleware('throttle:20,1')->name('integrity.exchange.cancel');
});

// ---------------- Staff: duplicate-token reports + exchange approvals (assigned locations only) ----------------
Route::middleware(['auth', 'role:staff,admin'])->prefix('staff/integrity')->group(function () {
    Route::get('/exchanges', [StaffIntegrityController::class, 'exchanges'])->name('integrity.staff.exchanges');
    Route::post('/exchanges/{exchange}/approve', [StaffIntegrityController::class, 'approve'])->whereNumber('exchange')->middleware('throttle:30,1')->name('integrity.staff.exchanges.approve');
    Route::post('/exchanges/{exchange}/reject', [StaffIntegrityController::class, 'reject'])->whereNumber('exchange')->middleware('throttle:30,1')->name('integrity.staff.exchanges.reject');
    Route::post('/tickets/{ticket}/report', [StaffIntegrityController::class, 'report'])->whereNumber('ticket')->middleware('throttle:10,1')->name('integrity.staff.report');
});

// ---------------- Admin: Dalal AI review ----------------
Route::middleware(['auth', 'role:admin'])->prefix('admin/integrity')->group(function () {
    Route::get('/', [AlertReviewController::class, 'index'])->name('integrity.admin.index');
    Route::post('/scan', [AlertReviewController::class, 'scan'])->middleware('throttle:6,1')->name('integrity.admin.scan');
    Route::get('/alerts/{alert}', [AlertReviewController::class, 'show'])->whereNumber('alert')->name('integrity.admin.show');
    Route::post('/alerts/{alert}/review', [AlertReviewController::class, 'review'])->whereNumber('alert')->middleware('throttle:30,1')->name('integrity.admin.review');
    Route::post('/alerts/{alert}/explain', [AlertReviewController::class, 'explain'])->whereNumber('alert')->middleware('throttle:10,1')->name('integrity.admin.explain');
});
