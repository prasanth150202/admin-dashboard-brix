<x-layout title="Overview">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">Overview</h1>
            <p class="mt-1 text-sm text-ink-2">
                @if ($lastSync)
                    Last sync {{ $lastSync->started_at->diffForHumans() }}
                    @if ($lastSync->status === 'failed')· <a class="underline" href="{{ route('settings') }}">had errors</a>@endif
                @else
                    Not synced yet — <a class="underline" href="{{ route('settings') }}">add your apps and run a sync</a>.
                @endif
            </p>
        </div>
        @if ($apps->count() > 1)
            <form method="GET" class="flex items-center gap-2">
                <label for="app" class="text-sm text-ink-2">App</label>
                <select id="app" name="app" class="field" onchange="this.form.submit()">
                    <option value="">All apps</option>
                    @foreach ($apps as $app)
                        <option value="{{ $app->id }}" @selected($appId === $app->id)>{{ $app->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    @php $storesUrl = fn (array $q) => route('stores.index', array_filter($q + ['app' => $appId])); @endphp

    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Installed stores" :value="number_format($stats['installed_stores'])" :href="$storesUrl(['status' => 'installed'])" />
        <x-stat label="Paying now" :value="number_format($stats['paying_now'])" hint="Active, non-test plan" />
        <x-stat label="Net · last 30 days" :value="money($stats['net_30d'])" />
        <x-stat label="Current payout period" :value="money($stats['period_net'])" :hint="$periodLabel.' · estimate'" :href="route('payouts.index')" />
    </div>

    <div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Client stores" :value="number_format($stats['client_stores'])" hint="Installed, has paid" :href="$storesUrl(['status' => 'installed', 'kind' => 'client'])" />
        <x-stat label="Test stores" :value="number_format($stats['test_stores'])" hint="Installed, test charges only" :href="$storesUrl(['status' => 'installed', 'kind' => 'test'])" />
        <x-stat label="Unpaid stores" :value="number_format($stats['unknown_stores'])" hint="Installed, never paid" :href="$storesUrl(['status' => 'installed', 'kind' => 'unknown'])" />
        <x-stat label="Lifetime net" :value="money($stats['lifetime_net'])" />
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <x-chart title="Net revenue by month" :spec="$revenueChart + ['format' => 'money', 'stacked' => true]" />
        <x-chart title="Installs and uninstalls by month" :spec="$installChart + ['format' => 'count']" />
    </div>

    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <section class="card">
            <h2 class="px-4 pt-4 text-sm font-semibold">Top stores by lifetime net</h2>
            <div class="mt-2 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Store</th><th>Type</th><th class="r">Net</th></tr></thead>
                    <tbody>
                    @forelse ($topStores as $row)
                        <tr>
                            <td>
                                <a href="{{ route('stores.show', $row->shop) }}" class="font-medium hover:underline">{{ $row->shop->displayName() }}</a>
                                <div class="text-xs text-muted">{{ $row->shop->myshopify_domain }}</div>
                            </td>
                            <td><x-kind-badge :shop="$row->shop" /></td>
                            <td class="r">{{ money($row->net) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-ink-2">No revenue yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2 class="px-4 pt-4 text-sm font-semibold">Recent activity</h2>
            <div class="mt-2 overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>When</th><th>Store</th><th>Event</th></tr></thead>
                    <tbody>
                    @forelse ($recent as $event)
                        <tr>
                            <td class="whitespace-nowrap text-ink-2" title="{{ $event->occurred_at }}">{{ $event->occurred_at->diffForHumans(short: true) }}</td>
                            <td>
                                <a href="{{ route('stores.show', $event->shop) }}" class="hover:underline">{{ $event->shop->displayName() }}</a>
                                @if (! $appId && $apps->count() > 1)<div class="text-xs text-muted">{{ $event->app->name }}</div>@endif
                            </td>
                            <td>
                                {{ $event->label() }}
                                @if ($event->charge_name)
                                    <div class="text-xs text-muted">{{ $event->charge_name }} · {{ money($event->charge_amount, $event->charge_currency) }}@if ($event->charge_test) · test @endif</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-ink-2">No events yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layout>
