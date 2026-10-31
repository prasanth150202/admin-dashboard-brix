<x-layout title="Stores">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Stores</h1>
            <p class="mt-1 text-sm text-ink-2">{{ number_format($shops->total()) }} {{ Str::plural('store', $shops->total()) }} across all apps</p>
        </div>
        @can('admin')<a href="{{ route('stores.export', request()->query()) }}" class="btn">Export CSV</a>@endcan
    </div>

    <form method="GET" class="mt-5 flex flex-wrap items-center gap-2">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name or domain" class="field w-full sm:w-64" aria-label="Search stores">
        <select name="app" class="field" aria-label="App">
            <option value="">All apps</option>
            @foreach ($apps as $app)
                <option value="{{ $app->id }}" @selected(($filters['app'] ?? '') == $app->id)>{{ $app->name }}</option>
            @endforeach
        </select>
        <select name="status" class="field" aria-label="Install status">
            <option value="">Any status</option>
            <option value="installed" @selected(($filters['status'] ?? '') === 'installed')>Installed</option>
            <option value="uninstalled" @selected(($filters['status'] ?? '') === 'uninstalled')>Uninstalled</option>
        </select>
        <select name="kind" class="field" aria-label="Store type">
            <option value="">Any type</option>
            <option value="client" @selected(($filters['kind'] ?? '') === 'client')>Client</option>
            <option value="test" @selected(($filters['kind'] ?? '') === 'test')>Test</option>
            <option value="unknown" @selected(($filters['kind'] ?? '') === 'unknown')>Unpaid</option>
        </select>
        <select name="paid" class="field" aria-label="Paid">
            <option value="">Paid or not</option>
            <option value="yes" @selected(($filters['paid'] ?? '') === 'yes')>Has paid</option>
            <option value="no" @selected(($filters['paid'] ?? '') === 'no')>Never paid</option>
        </select>
        <select name="sort" class="field" aria-label="Sort">
            <option value="lifetime_net" @selected(($filters['sort'] ?? '') === 'lifetime_net')>Highest net</option>
            <option value="last_charge_at" @selected(($filters['sort'] ?? '') === 'last_charge_at')>Latest charge</option>
            <option value="first_seen_at" @selected(($filters['sort'] ?? '') === 'first_seen_at')>Newest</option>
            <option value="myshopify_domain" @selected(($filters['sort'] ?? '') === 'myshopify_domain')>Domain A–Z</option>
        </select>
        <button class="btn btn-primary">Filter</button>
        @if (array_filter($filters))<a href="{{ route('stores.index') }}" class="text-sm text-ink-2 hover:text-ink">Clear</a>@endif
    </form>

    <div class="card mt-4 overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th>Store</th>
                <th>Type</th>
                <th>Apps</th>
                @can('admin')<th class="r">Lifetime net</th>@endcan
                <th class="r">Last charge</th>
                <th class="r">First seen</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($shops as $shop)
                <tr>
                    <td class="min-w-48">
                        <a href="{{ route('stores.show', $shop) }}" class="font-medium hover:underline">{{ $shop->displayName() }}</a>
                        <div class="text-xs text-muted">{{ $shop->myshopify_domain }}</div>
                    </td>
                    <td><x-kind-badge :shop="$shop" /></td>
                    <td class="min-w-56">
                        @foreach ($shop->installs->sortBy('app.name') as $install)
                            <div class="flex flex-wrap items-center gap-x-2">
                                <span class="text-sm">{{ $install->app->name }}</span>
                                <x-install-status :status="$install->status" />
                                @if ($install->plan_status === 'active' && $install->status === 'installed')
                                    <span class="text-xs text-muted">{{ $install->plan_name }}@can('admin') · {{ money($install->plan_amount, $install->plan_currency) }}@endcan @if ($install->plan_test) · test @endif</span>
                                @endif
                            </div>
                        @endforeach
                    </td>
                    @can('admin')<td class="r">{{ money($shop->lifetime_net) }}</td>@endcan
                    <td class="r whitespace-nowrap">{{ short_date($shop->last_charge_at) }}</td>
                    <td class="r whitespace-nowrap">{{ short_date($shop->first_seen_at) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-ink-2">No stores match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $shops->links() }}</div>
</x-layout>
