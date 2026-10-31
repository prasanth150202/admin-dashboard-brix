<?php

use App\Http\Controllers\Api\TicketApiController;
use App\Http\Middleware\SupportApiToken;
use Illuminate\Support\Facades\Route;

// Chatbot intake: POST /api/tickets with "Authorization: Bearer <SUPPORT_API_TOKEN>".
Route::post('/tickets', [TicketApiController::class, 'store'])->middleware([SupportApiToken::class, 'throttle:60,1']);
