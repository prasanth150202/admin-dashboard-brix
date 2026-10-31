<x-layout :title="$ticket->number().' '.$ticket->subject">
    <a href="{{ route('tickets.index') }}" class="text-sm text-ink-2 hover:text-ink">← Tickets</a>

    <div class="mt-2">
        <h1 class="text-xl font-semibold"><span class="text-muted">{{ $ticket->number() }}</span> {{ $ticket->subject }}</h1>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-2">
            <x-ticket-status :ticket="$ticket" />
            <span>{{ $ticket->requesterLabel() }}@if ($ticket->requester_name && $ticket->requester_email) · {{ $ticket->requester_email }}@endif</span>
            <span>via {{ \App\Models\Ticket::SOURCES[$ticket->source] ?? $ticket->source }}</span>
            <span>opened {{ $ticket->created_at->format('M j, Y H:i') }}</span>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            @foreach ($ticket->messages as $message)
                <article @class([
                    'card p-4',
                    'border-dashed' => $message->is_internal,
                ]) @if ($message->is_internal) style="background: var(--surface-2)" @endif>
                    <header class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span class="font-medium">
                            {{ $message->authorLabel() }}
                            @if ($message->is_internal)
                                <span class="text-xs font-normal text-muted">· internal note</span>
                            @elseif ($message->fromTeam())
                                <span class="text-xs font-normal text-muted">· team reply</span>
                            @else
                                <span class="text-xs font-normal text-muted">· merchant</span>
                            @endif
                        </span>
                        <time class="text-xs text-muted" datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('M j, Y H:i') }}</time>
                    </header>
                    <div class="mt-2 whitespace-pre-line break-words text-sm">{{ $message->body }}</div>
                </article>
            @endforeach

            <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="card p-4" x-data="{ internal: {{ old('internal') ? 'true' : 'false' }} }">
                @csrf
                <label for="body" class="block text-sm font-medium" x-text="internal ? 'Internal note (only the team sees this)' : 'Reply to the merchant'">Reply to the merchant</label>
                <textarea id="body" name="body" rows="5" required class="field mt-1 w-full">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-sm" style="color: var(--critical)">{{ $message }}</p>@enderror
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-ink-2">
                        <input type="checkbox" name="internal" value="1" x-model="internal"> Internal note
                    </label>
                    <label class="flex items-center gap-2 text-sm text-ink-2" x-show="! internal">
                        then set status
                        <select name="status" class="field" x-bind:disabled="internal">
                            <option value="pending">Waiting on merchant</option>
                            <option value="resolved">Resolved</option>
                            <option value="open">Open</option>
                        </select>
                    </label>
                    <button class="btn btn-primary ml-auto" x-text="internal ? 'Add note' : '{{ $ticket->requester_email ? 'Send reply' : 'Save reply' }}'">Send reply</button>
                </div>
                @if (! $ticket->requester_email)
                    <p class="mt-2 text-xs text-muted">No email address on this ticket, so replies are saved but not sent.</p>
                @endif
            </form>
        </div>

        <aside class="space-y-4">
            <form method="POST" action="{{ route('tickets.update', $ticket) }}" class="card space-y-3 p-4">
                @csrf @method('PATCH')
                <h2 class="font-semibold">Details</h2>
                <div>
                    <label for="status" class="block text-sm text-ink-2">Status</label>
                    <select id="status" name="status" class="field mt-1 w-full">
                        @foreach (\App\Models\Ticket::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($ticket->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="priority" class="block text-sm text-ink-2">Priority</label>
                    <select id="priority" name="priority" class="field mt-1 w-full">
                        @foreach (\App\Models\Ticket::PRIORITIES as $value => $label)
                            <option value="{{ $value }}" @selected($ticket->priority === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="assignee_id" class="block text-sm text-ink-2">Assignee</label>
                    <select id="assignee_id" name="assignee_id" class="field mt-1 w-full">
                        <option value="">Unassigned</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($ticket->assignee_id === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="partner_app_id" class="block text-sm text-ink-2">App</label>
                    <select id="partner_app_id" name="partner_app_id" class="field mt-1 w-full">
                        <option value="">Not app-specific</option>
                        @foreach ($apps as $app)
                            <option value="{{ $app->id }}" @selected($ticket->partner_app_id === $app->id)>{{ $app->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="shop_domain" class="block text-sm text-ink-2">Store</label>
                    <input id="shop_domain" name="shop_domain" value="{{ $ticket->shop_domain }}" placeholder="store-name.myshopify.com" class="field mt-1 w-full">
                    @if ($ticket->shop_domain && ! $ticket->shop)
                        <p class="mt-1 text-xs text-muted">No synced store with this domain.</p>
                    @endif
                </div>
                <button class="btn btn-primary">Save</button>
            </form>

            @if ($ticket->shop)
                <section class="card p-4">
                    <h2 class="font-semibold"><a href="{{ route('stores.show', $ticket->shop) }}" class="hover:underline">{{ $ticket->shop->displayName() }}</a></h2>
                    <div class="mt-1 flex items-center gap-2 text-xs text-ink-2">{{ $ticket->shop->myshopify_domain }} <x-kind-badge :shop="$ticket->shop" /></div>
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach ($ticket->shop->installs->sortBy('app.name') as $install)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $install->app->name }}</span>
                                <x-install-status :status="$install->status" />
                            </li>
                        @endforeach
                    </ul>
                    @if ($ticket->shop->setups->isNotEmpty())
                        <h3 class="mt-4 text-xs font-medium uppercase tracking-wide text-muted">Setups</h3>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($ticket->shop->setups as $setup)
                                <li class="flex items-center justify-between gap-2">
                                    <a href="{{ route('setups.show', $setup) }}" class="hover:underline">{{ $setup->app->name }}</a>
                                    <x-setup-status :setup="$setup" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            @if ($others->isNotEmpty())
                <section class="card p-4">
                    <h2 class="font-semibold">Other tickets from this merchant</h2>
                    <ul class="mt-3 space-y-2 text-sm">
                        @foreach ($others as $other)
                            <li>
                                <a href="{{ route('tickets.show', $other) }}" class="hover:underline"><span class="text-muted">{{ $other->number() }}</span> {{ $other->subject }}</a>
                                <div class="mt-0.5"><x-ticket-status :ticket="$other" /></div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</x-layout>
