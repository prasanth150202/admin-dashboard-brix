<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketMessageResource;
use App\Models\Ticket;
use App\Services\Support\TicketIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Messages on a ticket. Messages posted here count as the merchant's, so they reopen the ticket. */
class TicketMessageApiController extends Controller
{
    public function index(Ticket $ticket)
    {
        return TicketMessageResource::collection($ticket->messages()->get());
    }

    public function store(Request $request, Ticket $ticket, TicketIntake $intake): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);

        $message = $intake->addMessage($ticket, $data['message'], name: $data['name'] ?? null, email: $data['email'] ?? null);

        return TicketMessageResource::make($message)->response()->setStatusCode(201);
    }
}
