<?php

use App\Http\Controllers\Library\LibraryAdminController;
use App\Http\Controllers\Library\LibraryController;
use App\Http\Controllers\Library\ReaderController;
use Illuminate\Support\Facades\Route;

// ---------------- E-Library (module: library) ----------------
// Reading progress is keyed by user + book; nothing here touches tickets or queue order.
Route::middleware('auth')->prefix('library')->name('library.')->group(function () {
    Route::get('/', [LibraryController::class, 'home'])->name('home');
    Route::get('/search', [LibraryController::class, 'search'])->middleware('throttle:60,1')->name('search');
    Route::get('/genre/{category}', [LibraryController::class, 'category'])->name('category');
    Route::get('/me', [LibraryController::class, 'mine'])->name('mine');
    Route::get('/credits', [LibraryController::class, 'credits'])->name('credits');
    Route::get('/item/{book}', [LibraryController::class, 'show'])->name('book');

    Route::get('/read/{book}/{chapter?}', [ReaderController::class, 'read'])->whereNumber('chapter')->name('read');
    Route::post('/read/{book}/progress', [ReaderController::class, 'saveProgress'])->middleware('throttle:90,1')->name('progress');
    Route::get('/api/continue', [ReaderController::class, 'continue'])->middleware('throttle:30,1')->name('continue');

    Route::post('/item/{book}/favorite', [ReaderController::class, 'toggleFavorite'])->middleware('throttle:30,1')->name('favorite');
    Route::post('/item/{book}/bookmarks', [ReaderController::class, 'storeBookmark'])->middleware('throttle:30,1')->name('bookmarks.store');
    Route::delete('/bookmarks/{bookmark}', [ReaderController::class, 'destroyBookmark'])->middleware('throttle:30,1')->name('bookmarks.destroy');
    Route::post('/history/{book}/hide', [ReaderController::class, 'hideHistory'])->middleware('throttle:30,1')->name('history.hide');
});

// ---------------- E-Library content management (admins) ----------------
Route::middleware(['auth', 'role:admin'])->prefix('library/admin')->name('library.admin.')->group(function () {
    Route::get('/', [LibraryAdminController::class, 'index'])->name('index');
    Route::post('/categories', [LibraryAdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [LibraryAdminController::class, 'updateCategory'])->name('categories.update');
    Route::post('/categories/{category}/toggle', [LibraryAdminController::class, 'toggleCategory'])->name('categories.toggle');
    Route::get('/items/new', [LibraryAdminController::class, 'createBook'])->name('books.create');
    Route::post('/items', [LibraryAdminController::class, 'storeBook'])->name('books.store');
    Route::get('/items/{book}/edit', [LibraryAdminController::class, 'editBook'])->name('books.edit');
    Route::put('/items/{book}', [LibraryAdminController::class, 'updateBook'])->name('books.update');
    Route::post('/items/{book}/toggle', [LibraryAdminController::class, 'toggleBook'])->name('books.toggle');
    Route::post('/items/{book}/chapters', [LibraryAdminController::class, 'storeChapter'])->name('chapters.store');
    Route::put('/items/{book}/chapters/{chapter}', [LibraryAdminController::class, 'updateChapter'])->name('chapters.update');
});
