<?php

use App\Http\Controllers\Platform\AnalyticsController;
use App\Http\Controllers\Platform\AuditController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\IntegrationsController;
use App\Http\Controllers\Platform\LocationManageController;
use App\Http\Controllers\Platform\NotificationsController;
use App\Http\Controllers\Platform\OrganizationController;
use App\Http\Controllers\Platform\PlaceManageController;
use App\Http\Controllers\Platform\RewardsController;
use App\Http\Controllers\Platform\ServiceManageController;
use App\Http\Controllers\Platform\SettingsController;
use App\Http\Controllers\Platform\StaffManageController;
use App\Http\Controllers\Platform\UserManageController;
use App\Http\Middleware\Platform\EnsurePlatformManager;
use Illuminate\Support\Facades\Route;

/*
| Platform module: user dashboard, settings & privacy, notifications centre,
| rewards wallet, management console (platform admins + organization admins)
| and analytics. All names are prefixed "platform.".
*/

// ---------------- Signed-in users ----------------
Route::middleware('auth')->name('platform.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->middleware('throttle:20,1')->name('settings.profile');
    Route::post('/settings/password', [SettingsController::class, 'updatePassword'])->middleware('throttle:6,1')->name('settings.password');
    Route::post('/settings/preferences', [SettingsController::class, 'updatePreferences'])->middleware('throttle:30,1')->name('settings.preferences');
    Route::get('/settings/export', [SettingsController::class, 'export'])->middleware('throttle:5,1')->name('settings.export');
    Route::post('/settings/delete', [SettingsController::class, 'destroy'])->middleware('throttle:5,1')->name('settings.delete');

    Route::get('/notifications', [NotificationsController::class, 'index'])->name('notifications');
    Route::get('/notifications/unread-count', [NotificationsController::class, 'unreadCount'])->middleware('throttle:30,1')->name('notifications.count');
    Route::post('/notifications/read-all', [NotificationsController::class, 'readAll'])->middleware('throttle:20,1')->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationsController::class, 'read'])->middleware('throttle:60,1')->whereUuid('id')->name('notifications.read');

    Route::get('/rewards', [RewardsController::class, 'index'])->name('rewards');
    Route::post('/rewards/{item}/redeem', [RewardsController::class, 'redeem'])->middleware('throttle:10,1')->whereNumber('item')->name('rewards.redeem');
});

// ---------------- Analytics & platform-admin-only pages ----------------
Route::middleware(['auth', 'role:admin'])->name('platform.')->group(function () {
    Route::get('/admin/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/admin/manage/users', [UserManageController::class, 'index'])->name('manage.users');
    Route::post('/admin/manage/users/{user}/role', [UserManageController::class, 'updateRole'])->middleware('throttle:30,1')->name('manage.users.role');
    Route::get('/admin/manage/audit', [AuditController::class, 'index'])->name('manage.audit');
    Route::get('/admin/manage/integrations', [IntegrationsController::class, 'index'])->name('manage.integrations');
    Route::post('/admin/manage/integrations/ping', [IntegrationsController::class, 'ping'])->middleware('throttle:6,1')->name('manage.integrations.ping');
    Route::post('/admin/manage/organizations', [OrganizationController::class, 'store'])->middleware('throttle:20,1')->name('manage.organizations.store');
    Route::post('/admin/manage/organizations/{organization}/delete', [OrganizationController::class, 'destroy'])->name('manage.organizations.delete');
    Route::post('/admin/manage/organizations/{organization}/members', [OrganizationController::class, 'addMember'])->middleware('throttle:20,1')->name('manage.organizations.members.add');
    Route::post('/admin/manage/organizations/{organization}/members/{user}/remove', [OrganizationController::class, 'removeMember'])->name('manage.organizations.members.remove');
});

// ---------------- Management console (platform admins + organization admins, scoped) ----------------
Route::middleware(['auth', EnsurePlatformManager::class])->prefix('admin/manage')->name('platform.manage.')->group(function () {
    Route::get('/', [OrganizationController::class, 'overview'])->name('index');

    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations');
    Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->name('organizations.show');
    Route::post('/organizations/{organization}', [OrganizationController::class, 'update'])->middleware('throttle:30,1')->name('organizations.update');

    Route::get('/locations', [LocationManageController::class, 'index'])->name('locations');
    Route::get('/locations/create', [LocationManageController::class, 'create'])->name('locations.create');
    Route::post('/locations', [LocationManageController::class, 'store'])->middleware('throttle:20,1')->name('locations.store');
    Route::get('/locations/{location}', [LocationManageController::class, 'edit'])->name('locations.edit');
    Route::post('/locations/{location}', [LocationManageController::class, 'update'])->middleware('throttle:30,1')->name('locations.update');
    Route::post('/locations/{location}/counters', [LocationManageController::class, 'storeCounter'])->middleware('throttle:30,1')->name('counters.store');
    Route::post('/counters/{counter}/toggle', [LocationManageController::class, 'toggleCounter'])->middleware('throttle:60,1')->name('counters.toggle');
    Route::post('/counters/{counter}', [LocationManageController::class, 'updateCounter'])->middleware('throttle:30,1')->name('counters.update');

    Route::get('/locations/{location}/services/create', [ServiceManageController::class, 'create'])->name('services.create');
    Route::post('/locations/{location}/services', [ServiceManageController::class, 'store'])->middleware('throttle:20,1')->name('services.store');
    Route::get('/services/{service}', [ServiceManageController::class, 'edit'])->name('services.edit');
    Route::post('/services/{service}', [ServiceManageController::class, 'update'])->middleware('throttle:30,1')->name('services.update');

    Route::get('/staff', [StaffManageController::class, 'index'])->name('staff');
    Route::post('/staff', [StaffManageController::class, 'assign'])->middleware('throttle:20,1')->name('staff.assign');
    Route::post('/staff/{assignment}/approve', [StaffManageController::class, 'toggleApproval'])->middleware('throttle:30,1')->name('staff.approve');
    Route::post('/staff/{assignment}/remove', [StaffManageController::class, 'remove'])->middleware('throttle:30,1')->name('staff.remove');

    Route::get('/places', [PlaceManageController::class, 'index'])->name('places');
    Route::get('/places/create', [PlaceManageController::class, 'create'])->name('places.create');
    Route::post('/places', [PlaceManageController::class, 'store'])->middleware('throttle:20,1')->name('places.store');
    Route::get('/places/{place}', [PlaceManageController::class, 'edit'])->name('places.edit');
    Route::post('/places/{place}', [PlaceManageController::class, 'update'])->middleware('throttle:30,1')->name('places.update');
    Route::post('/places/{place}/delete', [PlaceManageController::class, 'destroy'])->middleware('throttle:20,1')->name('places.delete');
});
