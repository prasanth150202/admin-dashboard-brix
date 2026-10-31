<x-layout title="Tickets">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Tickets</h1>
            <p class="mt-1 text-sm text-ink-2">{{ number_format($tickets->total()) }} {{ Str::plural('ticket', $tickets->total()) }} · from the team, the <a href="{{ route('support.form') }}" class="underline" target="_blank">support form</a>, email and the chatbot</p>
        </div>
        <a href="{{ route('tickets.create') }}" class="btn btn-primary">New ticket</a>
    </div>

    <form method="GET" class="mt-5 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search #, subject, email, store" class="field w-full sm:w-64" aria-label="Search tickets">
        <select name="status" class="field" aria-label="Status">
            <option value="active" @selected($filters['status'] === 'active')>Not resolved</option>
            @foreach (\App\Models\Ticket::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
            <option value="all" @selected($filters['status'] === 'all')>All</option>
        </select>
        <select name="assignee" class="field" aria-label="Assignee">
            <option value="">Anyone</option>
            <option value="me" @selected(($filters['assignee'] ?? '') === 'me')>Assigned to me</option>
            <option value="none" @selected(($filters['assignee'] ?? '') === 'none')>Unassigned</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected(($filters['assignee'] ?? '') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>
        <select name="app" class="field" aria-label="App">
            <option value="">All apps</option>
            @foreach ($apps as $app)
                <option value="{{ $app->id }}" @selected(($filters['app'] ?? '') == $app->id)>{{ $app->name }}</option>
            @endforeach
        </select>
        <select name="source" class="field" aria-label="Source">
            <option value="">Any source</option>
            @foreach (\App\Models\Ticket::SOURCES as $value => $label)
                <option value="{{ $value }}" @selected(($filters['source'] ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary">Filter</button>
        @if (array_filter(Arr::except($filters, 'status')) || $filters['status'] !== 'active')<a href="{{ route('tickets.index') }}" class="text-sm text-ink-2 hover:text-ink">Clear</a>@endif
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th>Ticket</th>
                <th>Store</th>
                <th>App</th>
                <th>Status</th>
                <th>Assignee</th>
                <th class="r">Last activity</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($tickets as $ticket)
                <tr>
                    <td class="min-w-64">
                        <a href="{{ route('tickets.show', $ticket) }}" class="font-medium hover:underline"><span class="text-muted">{{ $ticket->number() }}</span> {{ $ticket->subject }}</a>
                        <div class="text-xs text-muted">{{ $ticket->requesterLabel() }} · {{ \App\Models\Ticket::SOURCES[$ticket->source] ?? $ticket->source }}</div>
                    </td>
                    <td class="min-w-40">
                        @if ($ticket->shop)
                            <a href="{{ route('stores.show', $ticket->shop) }}" class="hover:underline">{{ $ticket->shop->displayName() }}</a>
                        @else
                            <span class="text-ink-2">{{ $ticket->shop_domain ?? '—' }}</span>
                        @endif
                    </td>
                    <td>{{ $ticket->app?->name ?? '—' }}</td>
                    <td><div class="flex flex-col gap-1"><x-ticket-status :ticket="$ticket" /></div></td>
                    <td class="whitespace-nowrap">{{ $ticket->assignee?->name ?? '—' }}</td>
                    <td class="r whitespace-nowrap" title="{{ $ticket->last_activity_at }}">{{ $ticket->last_activity_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-ink-2">No tickets match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-layout>
