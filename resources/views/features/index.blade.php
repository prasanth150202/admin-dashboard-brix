<x-layout title="Feature requests">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Feature requests</h1>
            <p class="mt-1 text-sm text-ink-2">Raised by admins and the people they allow, moved along by the development team.</p>
        </div>
        @can('request-features')
            <a href="{{ route('features.create') }}" class="btn btn-primary">Request a feature</a>
        @endcan
    </div>

    {{-- One link per status, so anyone can see where things stand at a glance. --}}
    <div class="mt-5 flex flex-wrap gap-2">
        @foreach (\App\Models\FeatureRequest::STATUSES as $value => $label)
            <a href="{{ route('features.index', ['status' => $value]) }}"
               @class(['card px-3 py-2 text-sm', 'ring-1 ring-ink' => $filters['status'] === $value])>
                <span class="text-ink-2">{{ $label }}</span>
                <span class="num ml-1 font-semibold">{{ $counts[$value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <form method="GET" class="mt-4 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search requests" class="field w-full sm:w-64" aria-label="Search requests">
        <select name="status" class="field" aria-label="Status">
            <option value="active" @selected($filters['status'] === 'active')>Open</option>
            @foreach (\App\Models\FeatureRequest::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
            <option value="all" @selected($filters['status'] === 'all')>All</option>
        </select>
        <select name="assignee" class="field" aria-label="Developer">
            <option value="">Any developer</option>
            <option value="me" @selected(($filters['assignee'] ?? '') === 'me')>Assigned to me</option>
            <option value="mine" @selected(($filters['assignee'] ?? '') === 'mine')>Requested by me</option>
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
        <button class="btn btn-primary">Filter</button>
        @if (array_filter(Arr::except($filters, 'status')) || $filters['status'] !== 'active')<a href="{{ route('features.index') }}" class="text-sm text-ink-2 hover:text-ink">Clear</a>@endif
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th>Request</th>
                <th>App / brand</th>
                <th>Status</th>
                <th>Requested by</th>
                <th>Developer</th>
                <th class="r">Wanted by</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($features as $feature)
                <tr>
                    <td class="min-w-64">
                        <a href="{{ route('features.show', $feature) }}" class="font-medium hover:underline"><span class="text-muted">{{ $feature->number() }}</span> {{ $feature->title }}</a>
                        @if ($feature->updates_count)<div class="text-xs text-muted">{{ $feature->updates_count }} {{ Str::plural('update', $feature->updates_count) }} · {{ $feature->updated_at->diffForHumans() }}</div>@endif
                    </td>
                    <td class="whitespace-nowrap">
                        {{ $feature->app?->name ?? '—' }}
                        @if ($feature->shop)<div class="text-xs text-muted">{{ $feature->shop->displayName() }}</div>@endif
                    </td>
                    <td><div class="flex flex-col gap-1"><x-feature-status :feature="$feature" /></div></td>
                    <td class="whitespace-nowrap">
                        {{ $feature->requester?->name ?? '—' }}
                        <div class="text-xs text-muted">{{ $feature->created_at->format('M j') }}</div>
                    </td>
                    <td class="whitespace-nowrap">{{ $feature->assignee?->name ?? '—' }}</td>
                    <td class="r whitespace-nowrap">{{ short_date($feature->due_on) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-ink-2">No feature requests match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $features->links() }}</div>
</x-layout>
