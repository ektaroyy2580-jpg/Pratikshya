<?php

use App\Http\Controllers\Preorder\CartController;
use App\Http\Controllers\Preorder\MerchantController;
use App\Http\Controllers\Preorder\OrderController;
use App\Http\Controllers\Preorder\RestaurantController;
use Illuminate\Support\Facades\Route;

// ---------------- Cafe preordering (participating businesses only; demo payments) ----------------
Route::middleware('auth')->prefix('preorder')->name('preorder.')->group(function () {
    // Browse
    Route::get('/', [RestaurantController::class, 'index'])->name('index');
    Route::get('/cafes/{restaurant}', [RestaurantController::class, 'show'])->name('restaurant');

    // Session cart
    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart/items/{menuItem}', [CartController::class, 'set'])->whereNumber('menuItem')->middleware('throttle:90,1')->name('cart.set');
    Route::post('/cart/instructions', [CartController::class, 'instructions'])->middleware('throttle:30,1')->name('cart.instructions');
    Route::post('/cart/clear', [CartController::class, 'clear'])->middleware('throttle:30,1')->name('cart.clear');

    // Checkout & orders
    Route::get('/checkout', [OrderController::class, 'checkout'])->name('checkout');
    Route::post('/checkout', [OrderController::class, 'place'])->middleware('throttle:10,1')->name('place');
    Route::get('/orders', [OrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('order');
    Route::get('/api/orders/{order}', [OrderController::class, 'state'])->whereNumber('order')->middleware('throttle:60,1')->name('order.state');
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->middleware('throttle:10,1')->name('order.cancel');

    // Merchant dashboard (restaurant_staff links + admins; checked per restaurant by policy)
    Route::get('/merchant', [MerchantController::class, 'dashboard'])->name('merchant');
    Route::get('/api/merchant/{restaurant}/orders', [MerchantController::class, 'feed'])->middleware('throttle:60,1')->name('merchant.feed');
    Route::post('/merchant/orders/{order}/status', [MerchantController::class, 'status'])->whereNumber('order')->middleware('throttle:60,1')->name('merchant.status');
    Route::post('/merchant/{restaurant}/items/{menuItem}/toggle', [MerchantController::class, 'toggleItem'])->whereNumber('menuItem')->middleware('throttle:60,1')->name('merchant.item.toggle');
    Route::post('/merchant/{restaurant}/toggle', [MerchantController::class, 'toggleRestaurant'])->middleware('throttle:20,1')->name('merchant.toggle');
});
