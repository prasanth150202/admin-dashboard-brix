<?php

use App\Http\Controllers\AppsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\PayoutsController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SetupsController;
use App\Http\Controllers\StoresController;
use App\Http\Controllers\SupportFormController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketsController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
});

// Public: the merchant-facing support form.
Route::get('/support', [SupportFormController::class, 'show'])->name('support.form');
Route::post('/support', [SupportFormController::class, 'store'])->name('support.submit')->middleware('throttle:5,1');
Route::get('/support/thanks', [SupportFormController::class, 'thanks'])->name('support.thanks');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Team and admins.
    Route::get('/tickets', [TicketsController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/new', [TicketsController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketsController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketsController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}', [TicketsController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/reply', [TicketsController::class, 'reply'])->name('tickets.reply');

    Route::get('/setups', [SetupsController::class, 'index'])->name('setups.index');
    Route::post('/setups', [SetupsController::class, 'store'])->name('setups.store');
    Route::get('/setups/{setup}', [SetupsController::class, 'show'])->name('setups.show');
    Route::patch('/setups/{setup}', [SetupsController::class, 'update'])->name('setups.update');
    Route::delete('/setups/{setup}', [SetupsController::class, 'destroy'])->name('setups.destroy');
    Route::post('/setups/{setup}/tasks', [SetupsController::class, 'addTask'])->name('setups.tasks.store');
    Route::post('/setups/{setup}/tasks/{task}/toggle', [SetupsController::class, 'toggleTask'])->name('setups.tasks.toggle');
    Route::delete('/setups/{setup}/tasks/{task}', [SetupsController::class, 'destroyTask'])->name('setups.tasks.destroy');

    Route::get('/stores', [StoresController::class, 'index'])->name('stores.index');
    Route::get('/stores/{shop}', [StoresController::class, 'show'])->name('stores.show')->whereNumber('shop');
    Route::patch('/stores/{shop}', [StoresController::class, 'update'])->name('stores.update');

    // Admins get the overview; the team lands on tickets.
    Route::get('/', OverviewController::class)->name('overview');

    // Admins only: money, the Partner API connection and the team.
    Route::middleware('can:admin')->group(function () {
        Route::get('/apps', [AppsController::class, 'index'])->name('apps.index');
        Route::get('/apps/{app}/checklist', [ChecklistController::class, 'edit'])->name('apps.checklist');
        Route::put('/apps/{app}/checklist', [ChecklistController::class, 'update'])->name('apps.checklist.update');

        Route::get('/stores/export', [StoresController::class, 'export'])->name('stores.export');

        Route::get('/payouts', [PayoutsController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/{start}', [PayoutsController::class, 'show'])->name('payouts.show')->where('start', '\d{4}-\d{2}-\d{2}');
        Route::get('/payouts/{start}/export', [PayoutsController::class, 'export'])->name('payouts.export')->where('start', '\d{4}-\d{2}-\d{2}');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::patch('/team/{user}', [TeamController::class, 'update'])->name('team.update');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
        Route::post('/settings/apps', [SettingsController::class, 'storeApp'])->name('settings.apps.store');
        Route::delete('/settings/apps/{app}', [SettingsController::class, 'destroyApp'])->name('settings.apps.destroy');
        Route::post('/settings/test', [SettingsController::class, 'test'])->name('settings.test');
        Route::post('/settings/sync', [SettingsController::class, 'sync'])->name('settings.sync');
        Route::post('/settings/api-keys', [SettingsController::class, 'storeApiKey'])->name('settings.api-keys.store');
        Route::delete('/settings/api-keys/{key}', [SettingsController::class, 'destroyApiKey'])->name('settings.api-keys.destroy');
    });
});
