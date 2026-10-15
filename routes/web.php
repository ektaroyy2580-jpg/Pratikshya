<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', [QueueController::class, 'home'])->name('home');

// ---------------- Authentication ----------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:20,1')->name('login.post');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register.show');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');
});
Route::get('/staff/login', [AuthController::class, 'showStaffLogin'])->name('staff.login');
Route::post('/staff/login', [AuthController::class, 'staffLogin'])->middleware('throttle:20,1')->name('staff.login.post');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ---------------- Users ----------------
Route::middleware('auth')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile'])->name('profile');
    Route::get('/find', [QueueController::class, 'find'])->name('find');
    Route::get('/services/{service}', [QueueController::class, 'service'])->name('service');
    Route::post('/services/{service}/join', [QueueController::class, 'join'])->middleware('throttle:15,1')->name('join');
    Route::get('/ticket/{ticket}', [QueueController::class, 'ticket'])->name('ticket');
    Route::post('/ticket/{ticket}/leave', [QueueController::class, 'leave'])->name('ticket.leave');
    Route::get('/api/ticket/{ticket}', [QueueController::class, 'state'])->middleware('throttle:30,1')->name('ticket.state');
    Route::post('/api/ticket/{ticket}/points', [QueueController::class, 'points'])->middleware('throttle:10,1')->name('ticket.points');
});

// ---------------- Staff (assigned locations only) ----------------
Route::middleware(['auth', 'role:staff,admin'])->prefix('staff')->name('staff')->group(function () {
    Route::get('/', [StaffController::class, 'dashboard'])->name('');
    Route::post('/services/{service}/next', [StaffController::class, 'callNext'])->name('.next');
    Route::post('/services/{service}/pause', [StaffController::class, 'pause'])->name('.pause');
    Route::post('/tickets/{ticket}/start', [StaffController::class, 'start'])->name('.start');
    Route::post('/tickets/{ticket}/complete', [StaffController::class, 'complete'])->name('.complete');
    Route::post('/tickets/{ticket}/absent', [StaffController::class, 'absent'])->name('.absent');
    Route::post('/tickets/{ticket}/cancel', [StaffController::class, 'cancel'])->name('.cancel');
    Route::post('/tickets/{ticket}/move', [StaffController::class, 'move'])->name('.move');
    Route::post('/tickets/{ticket}/verify', [StaffController::class, 'verify'])->name('.verify');
});

// ---------------- Admin ----------------
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('');
    Route::post('/users/{user}/role', [AdminController::class, 'updateRole'])->name('.role');
    Route::post('/users/{user}/assign', [AdminController::class, 'assign'])->name('.assign');
    Route::delete('/assignments/{assignment}', [AdminController::class, 'unassign'])->name('.unassign');
});

// ---------------- Feature modules (one route file per module) ----------------
foreach (glob(__DIR__.'/modules/*.php') as $moduleRoutes) {
    require $moduleRoutes;
}
