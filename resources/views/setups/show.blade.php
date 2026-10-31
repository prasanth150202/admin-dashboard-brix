<x-layout :title="$setup->app->name.' setup · '.$setup->shop->displayName()">
    <a href="{{ route('setups.index') }}" class="text-sm text-ink-2 hover:text-ink">← Setups</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $setup->app->name }} setup</h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-2">
                <a href="{{ route('stores.show', $setup->shop) }}" class="hover:underline">{{ $setup->shop->displayName() }}</a>
                <a href="https://{{ $setup->shop->myshopify_domain }}/admin" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $setup->shop->myshopify_domain }} ↗</a>
                @if ($install)<x-install-status :status="$install->status" />@endif
                <x-setup-status :setup="$setup" />
            </p>
        </div>
        <x-progress :done="$setup->tasks->whereNotNull('done_at')->count()" :total="$setup->tasks->count()" />
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <section class="card p-4 lg:col-span-2">
            <h2 class="font-semibold">Checklist</h2>
            <ul class="mt-3 divide-y divide-line">
                @forelse ($setup->tasks as $task)
                    <li class="flex items-start gap-3 py-2.5">
                        <form method="POST" action="{{ route('setups.tasks.toggle', [$setup, $task]) }}">
                            @csrf
                            <button class="mt-0.5 flex h-5 w-5 items-center justify-center rounded border border-line text-xs"
                                    @if ($task->done_at) style="background: var(--good); border-color: var(--good); color: #fff" @endif
                                    aria-label="{{ $task->done_at ? 'Mark not done' : 'Mark done' }}: {{ $task->title }}">@if ($task->done_at)✓@endif</button>
                        </form>
                        <div class="min-w-0 flex-1">
                            <div @class(['text-sm', 'text-ink-2 line-through' => $task->done_at])>{{ $task->title }}</div>
                            @if ($task->done_at)
                                <div class="text-xs text-muted">{{ $task->doneBy?->name ?? 'Someone' }} · {{ $task->done_at->format('M j, H:i') }}</div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('setups.tasks.destroy', [$setup, $task]) }}" onsubmit="return confirm('Remove this step from this setup?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-muted hover:text-ink" aria-label="Remove step: {{ $task->title }}">Remove</button>
                        </form>
                    </li>
                @empty
                    <li class="py-2.5 text-sm text-ink-2">
                        No steps yet.
                        @can('admin')<a href="{{ route('apps.checklist', $setup->app) }}" class="underline">Write a checklist for {{ $setup->app->name }}</a> so new setups start with one, or add steps below.@else Add steps below.@endcan
                    </li>
                @endforelse
            </ul>
            <form method="POST" action="{{ route('setups.tasks.store', $setup) }}" class="mt-3 flex gap-2">
                @csrf
                <input name="title" required maxlength="250" placeholder="Add a step for this store" class="field flex-1" aria-label="New step">
                <button class="btn">Add</button>
            </form>
        </section>

        <aside class="space-y-4">
            <form method="POST" action="{{ route('setups.update', $setup) }}" class="card space-y-3 p-4">
                @csrf @method('PATCH')
                <h2 class="font-semibold">Details</h2>
                <div>
                    <label for="status" class="block text-sm text-ink-2">Status</label>
                    <select id="status" name="status" class="field mt-1 w-full">
                        @foreach (\App\Models\Setup::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($setup->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="assignee_id" class="block text-sm text-ink-2">Assignee</label>
                    <select id="assignee_id" name="assignee_id" class="field mt-1 w-full">
                        <option value="">Unassigned</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($setup->assignee_id === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="due_on" class="block text-sm text-ink-2">Due</label>
                    <input id="due_on" name="due_on" type="date" value="{{ $setup->due_on?->toDateString() }}" class="field mt-1 w-full">
                </div>
                <div>
                    <label for="notes" class="block text-sm text-ink-2">Notes</label>
                    <textarea id="notes" name="notes" rows="5" class="field mt-1 w-full">{{ $setup->notes }}</textarea>
                </div>
                <button class="btn btn-primary">Save</button>
            </form>

            <section class="card p-4">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold">Tickets from this store</h2>
                    <a href="{{ route('tickets.create', ['shop' => $setup->shop_id]) }}" class="text-sm text-ink-2 hover:text-ink">New</a>
                </div>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($tickets as $ticket)
                        <li>
                            <a href="{{ route('tickets.show', $ticket) }}" class="hover:underline"><span class="text-muted">{{ $ticket->number() }}</span> {{ $ticket->subject }}</a>
                            <div class="mt-0.5"><x-ticket-status :ticket="$ticket" /></div>
                        </li>
                    @empty
                        <li class="text-ink-2">None.</li>
                    @endforelse
                </ul>
            </section>

            <form method="POST" action="{{ route('setups.destroy', $setup) }}" onsubmit="return confirm('Delete this setup and its checklist?')">
                @csrf @method('DELETE')
                <button class="text-sm text-ink-2 hover:text-ink">Delete setup</button>
            </form>
        </aside>
    </div>
</x-layout>
