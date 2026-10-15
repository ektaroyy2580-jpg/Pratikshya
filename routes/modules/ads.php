<?php

use App\Http\Controllers\Ads\AdDeliveryController;
use App\Http\Controllers\Ads\AdminAdsController;
use App\Http\Controllers\Ads\AdvertiserPortalController;
use App\Http\Controllers\Ads\ProviderCallbackController;
use App\Http\Controllers\Ads\RewardedAdController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

/*
| Advertising module (ads). Ads never affect queue order, document checks, medical priority or staff decisions.
*/

// ---------------- Delivery (guests may see sponsored cards on public pages) ----------------
Route::post('/ads/impression', [AdDeliveryController::class, 'impression'])->middleware('throttle:120,1')->name('ads.impression');
Route::get('/ads/click/{campaign}/{placement}', [AdDeliveryController::class, 'click'])
    ->middleware(['signed', 'throttle:60,1'])->whereNumber('campaign')->where('placement', '[a-z_]+')->name('ads.click');

// Real ad-network S2S reward callback. Rejects everything until a provider is configured (config/ads.php).
Route::post('/ads/provider/callback', ProviderCallbackController::class)
    ->withoutMiddleware([ValidateCsrfToken::class])->middleware('throttle:30,1')->name('ads.provider.callback');

Route::middleware('auth')->group(function () {
    // ---------------- Rewarded ads (voluntary, DEMO provider) ----------------
    Route::get('/ads/rewarded', [RewardedAdController::class, 'show'])->name('ads.rewarded');
    Route::post('/ads/rewarded/start', [RewardedAdController::class, 'start'])->middleware('throttle:10,1')->name('ads.rewarded.start');
    Route::post('/ads/rewarded/{claim}/complete', [RewardedAdController::class, 'complete'])
        ->whereNumber('claim')->middleware('throttle:20,1')->name('ads.rewarded.complete');

    // ---------------- Advertiser portal (users linked via advertiser_users) ----------------
    Route::get('/advertise', [AdvertiserPortalController::class, 'apply'])->name('ads.apply');
    Route::post('/advertise', [AdvertiserPortalController::class, 'storeApplication'])->middleware('throttle:5,1')->name('ads.apply.store');

    Route::prefix('advertiser')->group(function () {
        Route::get('/', [AdvertiserPortalController::class, 'dashboard'])->name('ads.portal');
        Route::get('/campaigns/create', [AdvertiserPortalController::class, 'create'])->name('ads.campaigns.create');
        Route::post('/campaigns', [AdvertiserPortalController::class, 'store'])->middleware('throttle:30,1')->name('ads.campaigns.store');
        Route::get('/campaigns/{campaign}', [AdvertiserPortalController::class, 'show'])->whereNumber('campaign')->name('ads.campaigns.show');
        Route::get('/campaigns/{campaign}/edit', [AdvertiserPortalController::class, 'edit'])->whereNumber('campaign')->name('ads.campaigns.edit');
        Route::put('/campaigns/{campaign}', [AdvertiserPortalController::class, 'update'])->whereNumber('campaign')->middleware('throttle:30,1')->name('ads.campaigns.update');
        Route::post('/campaigns/{campaign}/submit', [AdvertiserPortalController::class, 'submit'])->whereNumber('campaign')->middleware('throttle:30,1')->name('ads.campaigns.submit');
        Route::post('/campaigns/{campaign}/pause', [AdvertiserPortalController::class, 'pause'])->whereNumber('campaign')->middleware('throttle:30,1')->name('ads.campaigns.pause');
        Route::post('/campaigns/{campaign}/resume', [AdvertiserPortalController::class, 'resume'])->whereNumber('campaign')->middleware('throttle:30,1')->name('ads.campaigns.resume');
    });
});

// ---------------- Admin ----------------
Route::middleware(['auth', 'role:admin'])->prefix('admin/ads')->group(function () {
    Route::get('/', [AdminAdsController::class, 'index'])->name('ads.admin');
    Route::post('/advertisers/{advertiser}/status', [AdminAdsController::class, 'advertiserStatus'])->whereNumber('advertiser')->middleware('throttle:60,1')->name('ads.admin.advertisers.status');
    Route::get('/campaigns/{campaign}', [AdminAdsController::class, 'campaign'])->whereNumber('campaign')->name('ads.admin.campaigns.show');
    Route::post('/campaigns/{campaign}/review', [AdminAdsController::class, 'review'])->whereNumber('campaign')->middleware('throttle:60,1')->name('ads.admin.campaigns.review');
});
