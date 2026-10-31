<?php

use App\Http\Controllers\Api\LookupApiController;
use App\Http\Controllers\Api\SetupApiController;
use App\Http\Controllers\Api\SetupTaskApiController;
use App\Http\Controllers\Api\TicketApiController;
use App\Http\Controllers\Api\TicketMessageApiController;
use App\Http\Middleware\SupportApiToken;
use Illuminate\Support\Facades\Route;

// Every endpoint needs "Authorization: Bearer <SUPPORT_API_TOKEN>". See README → REST API.
Route::middleware([SupportApiToken::class, 'throttle:120,1'])->name('api.')->group(function () {
    Route::apiResource('tickets', TicketApiController::class);
    Route::get('tickets/{ticket}/messages', [TicketMessageApiController::class, 'index'])->name('tickets.messages.index');
    Route::post('tickets/{ticket}/messages', [TicketMessageApiController::class, 'store'])->name('tickets.messages.store');

    Route::apiResource('setups', SetupApiController::class);
    Route::apiResource('setups.tasks', SetupTaskApiController::class)->only(['store', 'update', 'destroy'])->scoped();

    Route::get('apps', [LookupApiController::class, 'apps'])->name('apps.index');
    Route::get('users', [LookupApiController::class, 'users'])->name('users.index');
    Route::get('stores', [LookupApiController::class, 'stores'])->name('stores.index');
    Route::get('stores/{store}', [LookupApiController::class, 'store'])->name('stores.show');
});
