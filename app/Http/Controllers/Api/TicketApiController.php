<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Services\Support\TicketIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ticket intake for the chatbot. Posting again with the same
 * conversation_id adds a message to that ticket instead of opening a new one.
 */
class TicketApiController extends Controller
{
    public function store(Request $request, TicketIntake $intake): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:20000'],
            'subject' => ['nullable', 'string', 'max:250'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'shop_domain' => ['nullable', 'string', 'max:190'],
            'app' => ['nullable', 'string', 'max:190'],
            'priority' => ['nullable', Rule::in(array_keys(Ticket::PRIORITIES))],
            'conversation_id' => ['nullable', 'string', 'max:190'],
        ]);

        $ref = $data['conversation_id'] ?? null;
        $ticket = $ref ? Ticket::where('source', 'chatbot')->where('external_ref', $ref)->first() : null;

        if ($ticket) {
            $intake->addMessage($ticket, $data['message'], name: $data['name'] ?? null, email: $data['email'] ?? null);
            $created = false;
        } else {
            $ticket = $intake->open('chatbot', $data + ['external_ref' => $ref]);
            $created = true;
        }

        return response()->json([
            'id' => $ticket->id,
            'number' => $ticket->number(),
            'status' => $ticket->status,
            'created' => $created,
            'url' => route('tickets.show', $ticket),
        ], $created ? 201 : 200);
    }
}
