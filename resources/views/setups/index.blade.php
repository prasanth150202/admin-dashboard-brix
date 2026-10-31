<x-layout title="Setups">
    <div>
        <h1 class="text-xl font-semibold">Setups</h1>
        <p class="mt-1 text-sm text-ink-2">{{ number_format($setups->total()) }} {{ Str::plural('setup', $setups->total()) }} · started automatically when a store installs an app, using that app's checklist</p>
    </div>

    <form method="GET" class="mt-5 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search store" class="field w-full sm:w-64" aria-label="Search stores">
        <select name="status" class="field" aria-label="Status">
            <option value="active" @selected($filters['status'] === 'active')>Not done</option>
            @foreach (\App\Models\Setup::STATUSES as $value => $label)
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
        <button class="btn btn-primary">Filter</button>
        @if (array_filter(Arr::except($filters, 'status')) || $filters['status'] !== 'active')<a href="{{ route('setups.index') }}" class="text-sm text-ink-2 hover:text-ink">Clear</a>@endif
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th>Store</th>
                <th>App</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Assignee</th>
                <th class="r">Due</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($setups as $setup)
                @php $install = $setup->shop->installs->firstWhere('partner_app_id', $setup->partner_app_id); @endphp
                <tr>
                    <td class="min-w-48">
                        <a href="{{ route('setups.show', $setup) }}" class="font-medium hover:underline">{{ $setup->shop->displayName() }}</a>
                        <div class="text-xs text-muted">{{ $setup->shop->myshopify_domain }}</div>
                    </td>
                    <td class="whitespace-nowrap">
                        {{ $setup->app->name }}
                        @if ($install && $install->status !== 'installed')<div><x-install-status :status="$install->status" /></div>@endif
                    </td>
                    <td><x-progress :done="$setup->done_count" :total="$setup->tasks_count" /></td>
                    <td><div class="flex flex-col gap-1"><x-setup-status :setup="$setup" /></div></td>
                    <td class="whitespace-nowrap">
                        {{ $setup->assignee?->name ?? '—' }}
                        @if ($setup->assigner && $setup->assigner->id !== $setup->assignee_id)<div class="text-xs text-muted">by {{ $setup->assigner->name }}</div>@endif
                    </td>
                    <td class="r whitespace-nowrap">{{ short_date($setup->due_on) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-ink-2">No setups match. New installs show up here after the next sync.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $setups->links() }}</div>
</x-layout>
