<?php

use App\Http\Controllers\Bills\BillController;
use App\Http\Controllers\Bills\BillInviteController;
use App\Http\Controllers\Bills\BillSettlementController;
use Illuminate\Support\Facades\Route;

// ---------------- Group bill splitting (never touches queues) ----------------
Route::middleware('auth')->prefix('bills')->name('bills.')->group(function () {
    Route::get('/', [BillController::class, 'index'])->name('index');
    Route::get('/create', [BillController::class, 'create'])->name('create');
    Route::post('/', [BillController::class, 'store'])->middleware('throttle:20,1')->name('store');

    // Invite link (login required; joining needs an explicit POST confirmation)
    Route::get('/join/{token}', [BillInviteController::class, 'show'])->middleware('throttle:30,1')->name('join');
    Route::post('/join/{token}', [BillInviteController::class, 'join'])->middleware('throttle:15,1')->name('join.confirm');
    Route::post('/invitations/{participant}/accept', [BillInviteController::class, 'accept'])->whereNumber('participant')->middleware('throttle:20,1')->name('invitations.accept');
    Route::post('/invitations/{participant}/decline', [BillInviteController::class, 'decline'])->whereNumber('participant')->middleware('throttle:20,1')->name('invitations.decline');

    Route::get('/{bill}', [BillController::class, 'show'])->whereNumber('bill')->name('show');
    Route::get('/{bill}/edit', [BillController::class, 'edit'])->whereNumber('bill')->name('edit');
    Route::post('/{bill}/update', [BillController::class, 'update'])->whereNumber('bill')->middleware('throttle:30,1')->name('update');
    Route::get('/{bill}/calculation', [BillController::class, 'calculation'])->whereNumber('bill')->middleware('throttle:60,1')->name('calculation');
    Route::post('/{bill}/allocations', [BillController::class, 'allocations'])->whereNumber('bill')->middleware('throttle:40,1')->name('allocations');
    Route::post('/{bill}/items/{item}/mine', [BillController::class, 'toggleMine'])->whereNumber(['bill', 'item'])->middleware('throttle:60,1')->name('items.mine');
    Route::post('/{bill}/custom', [BillController::class, 'custom'])->whereNumber('bill')->middleware('throttle:30,1')->name('custom');
    Route::post('/{bill}/status', [BillController::class, 'status'])->whereNumber('bill')->middleware('throttle:20,1')->name('status');
    Route::post('/{bill}/participants/{participant}/remove', [BillController::class, 'removeParticipant'])->whereNumber(['bill', 'participant'])->middleware('throttle:20,1')->name('participants.remove');

    Route::post('/{bill}/invite-link/rotate', [BillInviteController::class, 'rotate'])->whereNumber('bill')->middleware('throttle:10,1')->name('invite.rotate');
    Route::post('/{bill}/invite-link/revoke', [BillInviteController::class, 'revoke'])->whereNumber('bill')->middleware('throttle:10,1')->name('invite.revoke');
    Route::post('/{bill}/invite-email', [BillInviteController::class, 'email'])->whereNumber('bill')->middleware('throttle:10,1')->name('invite.email');

    Route::get('/{bill}/settle', [BillSettlementController::class, 'show'])->whereNumber('bill')->name('settle');
    Route::post('/{bill}/payments', [BillSettlementController::class, 'claim'])->whereNumber('bill')->middleware('throttle:20,1')->name('payments.claim');
    Route::post('/{bill}/payments/{payment}/confirm', [BillSettlementController::class, 'confirm'])->whereNumber(['bill', 'payment'])->middleware('throttle:40,1')->name('payments.confirm');
    Route::post('/{bill}/payments/{payment}/dispute', [BillSettlementController::class, 'dispute'])->whereNumber(['bill', 'payment'])->middleware('throttle:40,1')->name('payments.dispute');
});
