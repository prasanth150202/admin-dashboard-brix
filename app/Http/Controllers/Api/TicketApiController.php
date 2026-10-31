<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Services\Support\TicketIntake;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tickets over REST. Creating with a conversation_id that already has a
 * chatbot ticket adds a message to it instead of opening a second one.
 */
class TicketApiController extends Controller
{
    private const RELATIONS = ['shop', 'app', 'assignee'];

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in([...array_keys(Ticket::STATUSES), 'active'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $tickets = Ticket::query()
            ->with(self::RELATIONS)
            ->when($request->input('status') === 'active', fn ($q) => $q->active())
            ->when(array_key_exists((string) $request->input('status'), Ticket::STATUSES), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('email'), fn ($q) => $q->where('requester_email', TicketIntake::normalizeEmail($request->input('email'))))
            ->when($request->filled('shop_domain'), fn ($q) => $q->where('shop_domain', TicketIntake::normalizeDomain($request->input('shop_domain'))))
            ->when($request->filled('conversation_id'), fn ($q) => $q->where('external_ref', $request->input('conversation_id')))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->input('source')))
            ->when($request->filled('app'), fn ($q) => $q->where('partner_app_id', app(TicketIntake::class)->resolveApp($request->input('app'))?->id ?? 0))
            ->when($request->filled('assignee_id'), fn ($q) => $q->where('assignee_id', $request->integer('assignee_id')))
            ->latest('last_activity_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return TicketResource::collection($tickets);
    }

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
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'string', 'max:190'],
        ]);

        $ref = $data['conversation_id'] ?? null;
        $ticket = $ref ? Ticket::where('source', 'chatbot')->where('external_ref', $ref)->first() : null;
        $created = $ticket === null;

        if ($created) {
            $ticket = $intake->open('chatbot', $data + ['external_ref' => $ref]);
        } else {
            $intake->addMessage($ticket, $data['message'], name: $data['name'] ?? null, email: $data['email'] ?? null);
        }

        return TicketResource::make($ticket->fresh([...self::RELATIONS, 'messages']))
            ->additional(['created' => $created])
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function show(Ticket $ticket): TicketResource
    {
        return TicketResource::make($ticket->load([...self::RELATIONS, 'messages']));
    }

    /** Partial update: send only the fields to change. */
    public function update(Request $request, Ticket $ticket): TicketResource
    {
        $data = $request->validate([
            'subject' => ['sometimes', 'required', 'string', 'max:250'],
            'status' => ['sometimes', 'required', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['sometimes', 'required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'app' => ['sometimes', 'nullable', 'string', 'max:190'],
            'shop_domain' => ['sometimes', 'nullable', 'string', 'max:190'],
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
        ]);

        $changes = collect($data)->only(['subject', 'status', 'priority', 'assignee_id'])->all();

        if (array_key_exists('app', $data)) {
            $changes['partner_app_id'] = app(TicketIntake::class)->resolveApp($data['app'])?->id;
        }
        if (array_key_exists('shop_domain', $data)) {
            $changes += TicketIntake::shopFields($data['shop_domain']);
        }
        if (array_key_exists('name', $data)) {
            $changes['requester_name'] = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $changes['requester_email'] = TicketIntake::normalizeEmail($data['email']);
        }
        if (isset($changes['status'])) {
            $changes['resolved_at'] = $changes['status'] === 'resolved' ? ($ticket->resolved_at ?? now()) : null;
        }

        $ticket->fill($changes)->save();

        return TicketResource::make($ticket->fresh([...self::RELATIONS, 'messages']));
    }

    public function destroy(Ticket $ticket): JsonResponse
    {
        $ticket->delete();

        return response()->json(null, 204);
    }
}
