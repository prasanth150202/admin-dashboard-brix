<?php

namespace App\Http\Controllers;

use App\Mail\TicketReply;
use App\Models\PartnerApp;
use App\Models\Shop;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Support\TicketIntake;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class TicketsController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['q', 'status', 'app', 'assignee', 'source']);
        $status = $filters['status'] ?? 'active';
        $assignee = $filters['assignee'] ?? '';

        $tickets = Ticket::query()
            ->with(['shop', 'app', 'assignee'])
            ->when($status === 'active', fn ($q) => $q->active())
            ->when(array_key_exists($status, Ticket::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($request->integer('app'), fn ($q, $id) => $q->where('partner_app_id', $id))
            ->when(array_key_exists($filters['source'] ?? '', Ticket::SOURCES), fn ($q) => $q->where('source', $filters['source']))
            ->when($assignee === 'me', fn ($q) => $q->where('assignee_id', $request->user()->id))
            ->when($assignee === 'none', fn ($q) => $q->whereNull('assignee_id'))
            ->when(ctype_digit($assignee), fn ($q) => $q->where('assignee_id', (int) $assignee))
            ->when($request->filled('q'), function ($q) use ($request) {
                $raw = ltrim($request->input('q'), '#');
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $raw).'%';
                $q->where(fn ($q) => $q->where('subject', 'like', $term)
                    ->orWhere('requester_email', 'like', $term)
                    ->orWhere('requester_name', 'like', $term)
                    ->orWhere('shop_domain', 'like', $term)
                    ->when(ctype_digit($raw), fn ($q) => $q->orWhere('id', (int) $raw)));
            })
            // Urgent first, then whatever moved most recently.
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 ELSE 2 END")
            ->latest('last_activity_at')
            ->paginate(50)
            ->withQueryString();

        return view('tickets.index', [
            'tickets' => $tickets,
            'filters' => $filters + ['status' => $status],
            'apps' => PartnerApp::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('tickets.create', [
            'apps' => PartnerApp::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'shop' => $request->integer('shop') ? Shop::find($request->integer('shop')) : null,
        ]);
    }

    public function store(Request $request, TicketIntake $intake)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:250'],
            'message' => ['required', 'string', 'max:20000'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'shop_domain' => ['nullable', 'string', 'max:190'],
            'app' => ['nullable', 'integer', 'exists:partner_apps,id'],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket = $intake->open('manual', $data);

        return redirect()->route('tickets.show', $ticket)->with('status', "Ticket {$ticket->number()} created.");
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['messages.user', 'shop.installs.app', 'shop.setups.app', 'app', 'assignee']);

        return view('tickets.show', [
            'ticket' => $ticket,
            'apps' => PartnerApp::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'others' => Ticket::where('id', '!=', $ticket->id)
                ->where(fn ($q) => $q
                    ->when($ticket->shop_id, fn ($q) => $q->orWhere('shop_id', $ticket->shop_id))
                    ->when($ticket->requester_email, fn ($q) => $q->orWhere('requester_email', $ticket->requester_email)))
                ->when(! $ticket->shop_id && ! $ticket->requester_email, fn ($q) => $q->whereRaw('1 = 0'))
                ->latest('id')->limit(10)->get(),
        ]);
    }

    public function update(Request $request, Ticket $ticket)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Ticket::STATUSES))],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'partner_app_id' => ['nullable', 'integer', 'exists:partner_apps,id'],
            'shop_domain' => ['nullable', 'string', 'max:190'],
        ]);

        $data = TicketIntake::shopFields($data['shop_domain'] ?? null) + $data;
        $data['resolved_at'] = $data['status'] === 'resolved' ? ($ticket->resolved_at ?? now()) : null;

        $ticket->fill($data)->save();

        return back()->with('status', 'Ticket updated.');
    }

    public function reply(Request $request, Ticket $ticket, TicketIntake $intake)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'internal' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(array_keys(Ticket::STATUSES))],
        ]);

        $internal = (bool) ($data['internal'] ?? false);
        $message = $intake->addMessage($ticket, $data['body'], $request->user(), $internal);

        if ($data['status'] ?? null) {
            $ticket->forceFill([
                'status' => $data['status'],
                'resolved_at' => $data['status'] === 'resolved' ? now() : null,
            ])->save();
        }

        if ($internal) {
            return back()->with('status', 'Note added.');
        }

        if (! $ticket->requester_email) {
            return back()->with('status', 'Reply saved. This ticket has no email address, so nothing was sent.');
        }

        $message->forceFill(['email_message_id' => 'ticket-'.$ticket->id.'-'.$message->id.'-'.Str::random(8).'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost')])->save();

        try {
            Mail::to($ticket->requester_email, $ticket->requester_name)->send(new TicketReply($message));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', "Reply saved, but the email didn't send: {$e->getMessage()}");
        }

        return back()->with('status', "Reply sent to {$ticket->requester_email}.");
    }
}
