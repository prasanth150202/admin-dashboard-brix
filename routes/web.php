<?php

use App\Http\Controllers\AppsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PayoutsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StoresController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', OverviewController::class)->name('overview');
    Route::get('/apps', [AppsController::class, 'index'])->name('apps.index');

    Route::get('/stores', [StoresController::class, 'index'])->name('stores.index');
    Route::get('/stores/export', [StoresController::class, 'export'])->name('stores.export');
    Route::get('/stores/{shop}', [StoresController::class, 'show'])->name('stores.show');
    Route::patch('/stores/{shop}', [StoresController::class, 'update'])->name('stores.update');

    Route::get('/payouts', [PayoutsController::class, 'index'])->name('payouts.index');
    Route::get('/payouts/{start}', [PayoutsController::class, 'show'])->name('payouts.show')->where('start', '\d{4}-\d{2}-\d{2}');
    Route::get('/payouts/{start}/export', [PayoutsController::class, 'export'])->name('payouts.export')->where('start', '\d{4}-\d{2}-\d{2}');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::post('/settings/apps', [SettingsController::class, 'storeApp'])->name('settings.apps.store');
    Route::delete('/settings/apps/{app}', [SettingsController::class, 'destroyApp'])->name('settings.apps.destroy');
    Route::post('/settings/test', [SettingsController::class, 'test'])->name('settings.test');
    Route::post('/settings/sync', [SettingsController::class, 'sync'])->name('settings.sync');
});
