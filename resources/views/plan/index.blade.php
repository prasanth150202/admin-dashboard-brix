<x-layout title="Plan">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Plan</h1>
            <p class="mt-1 text-sm text-ink-2">Everyone's open setups and feature requests. Hand out the unassigned work and set due dates here.</p>
        </div>
        <div class="flex gap-1 text-sm">
            @foreach (['' => 'Everyone'] + \App\Models\User::TEAMS as $value => $label)
                <a href="{{ route('plan.index', array_filter(['team' => $value])) }}"
                   @class(['rounded-lg px-3 py-1.5', 'bg-surface-2 font-medium text-ink' => (string) $team === (string) $value, 'text-ink-2 hover:bg-surface-2' => (string) $team !== (string) $value])>{{ $label }}{{ $value ? ' team' : '' }}</a>
            @endforeach
        </div>
    </div>

    @if ($unassigned->isNotEmpty())
        <section class="card mt-6" style="border-color: var(--warning)">
            <header class="flex flex-wrap items-baseline justify-between gap-2 px-4 pt-4">
                <h2 class="font-semibold">Unassigned</h2>
                <span class="text-sm text-ink-2">{{ $unassigned->where('type', 'setup')->count() }} setups · {{ $unassigned->where('type', 'feature')->count() }} feature requests</span>
            </header>
            @include('plan._items', ['items' => $unassigned])
        </section>
    @endif

    @foreach ($people as $person)
        @php
            $overdue = $person->items->where('overdue', true)->count();
            $thisWeek = $person->items->filter(fn ($i) => $i->due_on && ! $i->overdue && $i->due_on->lte(today()->addDays(7)))->count();
        @endphp
        <section class="card mt-4">
            <header class="flex flex-wrap items-baseline justify-between gap-2 px-4 py-4">
                <div>
                    <h2 class="font-semibold">{{ $person->user->name }}@if ($person->user->is(auth()->user()))<span class="text-xs font-normal text-muted"> · you</span>@endif</h2>
                    <div class="text-xs text-muted">{{ \App\Models\User::TEAMS[$person->user->team] ?? 'No team' }} · {{ \App\Models\User::ROLES[$person->user->role] ?? $person->user->role }}</div>
                </div>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-ink-2">
                    <span><b class="num text-ink">{{ $person->items->where('type', 'setup')->count() }}</b> setups</span>
                    <span><b class="num text-ink">{{ $person->items->where('type', 'feature')->count() }}</b> features</span>
                    <span><b class="num text-ink">{{ $thisWeek }}</b> due this week</span>
                    @if ($overdue)<x-dot :label="$overdue.' overdue'" color="var(--critical)" />@endif
                </div>
            </header>
            @if ($person->items->isNotEmpty())
                @include('plan._items', ['items' => $person->items])
            @else
                <p class="border-t border-line px-4 py-3 text-sm text-ink-2">Nothing open — free to take work.</p>
            @endif
        </section>
    @endforeach

    @if ($people->isEmpty())
        <p class="mt-6 text-sm text-ink-2">Nobody is in this team yet. Set teams on the @can('admin')<a href="{{ route('team.index') }}" class="underline">Team</a>@else Team @endcan page.</p>
    @endif
</x-layout>
