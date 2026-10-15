<?php

use App\Http\Controllers\Games\CompetitionController;
use App\Http\Controllers\Games\HubController;
use App\Http\Controllers\Games\InvitationController;
use App\Http\Controllers\Games\QuestionAdminController;
use Illuminate\Support\Facades\Route;

/*
| Games module: hub, multiplayer Quick Brain Battle, leaderboard, question admin.
| (Solo arcade games live under /games/play — owned by the arcade module.)
*/
Route::middleware('auth')->prefix('games')->name('games.')->group(function () {
    Route::get('/', [HubController::class, 'hub'])->name('hub');
    Route::get('/leaderboard', [HubController::class, 'leaderboard'])->name('leaderboard');

    Route::post('/competitions', [CompetitionController::class, 'store'])->middleware('throttle:10,1')->name('competitions.store');
    Route::get('/competitions/{competition}', [CompetitionController::class, 'show'])->name('competitions.show');
    Route::get('/competitions/{competition}/state', [CompetitionController::class, 'state'])->middleware('throttle:90,1')->name('competitions.state');
    Route::get('/competitions/{competition}/results', [CompetitionController::class, 'results'])->name('competitions.results');
    Route::post('/competitions/{competition}/join', [CompetitionController::class, 'join'])->middleware('throttle:20,1')->name('competitions.join');
    Route::post('/competitions/{competition}/ready', [CompetitionController::class, 'ready'])->middleware('throttle:30,1')->name('competitions.ready');
    Route::post('/competitions/{competition}/start', [CompetitionController::class, 'start'])->middleware('throttle:10,1')->name('competitions.start');
    Route::post('/competitions/{competition}/leave', [CompetitionController::class, 'leave'])->middleware('throttle:20,1')->name('competitions.leave');
    Route::post('/competitions/{competition}/cancel', [CompetitionController::class, 'cancel'])->middleware('throttle:10,1')->name('competitions.cancel');
    Route::post('/competitions/{competition}/invite', [CompetitionController::class, 'invite'])->middleware('throttle:20,1')->name('competitions.invite');
    Route::post('/competitions/{competition}/answer', [CompetitionController::class, 'answer'])->middleware('throttle:60,1')->name('competitions.answer');

    Route::post('/invitations/{invitation}/accept', [InvitationController::class, 'accept'])->middleware('throttle:20,1')->name('invitations.accept');
    Route::post('/invitations/{invitation}/decline', [InvitationController::class, 'decline'])->middleware('throttle:20,1')->name('invitations.decline');
});

Route::middleware(['auth', 'role:admin'])->prefix('games/admin')->name('games.admin.')->group(function () {
    Route::get('/questions', [QuestionAdminController::class, 'index'])->name('questions');
    Route::post('/questions/generate', [QuestionAdminController::class, 'generate'])->middleware('throttle:6,1')->name('questions.generate');
    Route::post('/questions/{question}/status', [QuestionAdminController::class, 'status'])->middleware('throttle:60,1')->name('questions.status');
});
