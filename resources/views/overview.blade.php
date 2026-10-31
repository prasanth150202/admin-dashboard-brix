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

    @php
        $rate = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 1), '0'), '.').'%';
        $vsPrev = fn (int $prev) => 'Previous 30 days: '.number_format($prev);
    @endphp

    <h2 class="mt-8 text-xs font-semibold uppercase tracking-wide text-muted">Stores</h2>
    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <x-stat label="Total installs" :value="number_format($stats['total_installs'])" :hint="number_format($stats['installed_now']).' still installed'" :href="$storesUrl([])" />
        <x-stat label="Actual stores" :value="number_format($stats['actual_stores'])" hint="Installed real stores that have paid" :href="$storesUrl(['status' => 'installed', 'kind' => 'client'])" />
        <x-stat label="Development stores" :value="number_format($stats['dev_stores'])" hint="Installed, test charges only" :href="$storesUrl(['status' => 'installed', 'kind' => 'test'])" />
        <x-stat label="Unpaid stores" :value="number_format($stats['unpaid_stores'])" hint="Installed, never paid" :href="$storesUrl(['status' => 'installed', 'kind' => 'unknown'])" />
        <x-stat label="Paying now" :value="number_format($stats['paying_now'])" hint="On an active paid plan" />
    </div>

    <h2 class="mt-8 text-xs font-semibold uppercase tracking-wide text-muted">Installs &amp; uninstalls</h2>
    <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
        <x-stat label="Installs · 30 days" :value="number_format($stats['installs_30d'])" :hint="$vsPrev($stats['installs_prev_30d'])" />
        <x-stat label="Uninstalls · 30 days" :value="number_format($stats['uninstalls_30d'])" :hint="$vsPrev($stats['uninstalls_prev_30d'])" :href="$storesUrl(['status' => 'uninstalled'])" />
        <x-stat label="Install rate" :value="$rate($stats['install_rate'])" hint="Still installed ÷ ever installed" />
        <x-stat label="Uninstall rate" :value="$rate($stats['uninstall_rate'])" hint="Removed ÷ ever installed" />
        <x-stat label="Paid conversion" :value="$rate($stats['paid_conversion'])" hint="Real stores that ever paid ÷ all real stores" />
    </div>

    <h2 class="mt-8 text-xs font-semibold uppercase tracking-wide text-muted">Payouts</h2>
    <div class="mt-2 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Current payout period" :value="money($stats['period_net'])" :hint="$periodLabel.' · estimate'" :href="route('payouts.show', $periodStart->toDateString())" />
        <x-stat label="Previous payout period" :value="money($stats['prev_period_net'])" :hint="$prevPeriodLabel" :href="route('payouts.show', $prevStart->toDateString())" />
        <x-stat label="Net · last 30 days" :value="money($stats['net_30d'])" />
        <x-stat label="All-time payouts" :value="money($stats['lifetime_net'])" hint="Net after Shopify's fee" :href="route('payouts.index')" />
    </div>

    <div class="mt-8 grid gap-4 xl:grid-cols-2">
        <section class="card">
            <div class="px-4 pt-4">
                <h2 class="text-sm font-semibold">Actual stores by plan</h2>
                <p class="mt-0.5 text-xs text-ink-2">Installed now, development stores and test charges left out</p>
            </div>
            <div class="mt-2 overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Plan</th>
                        @unless ($appId)<th>App</th>@endunless
                        <th class="r">Price</th>
                        <th class="r">Stores</th>
                        <th class="r" title="Price × stores, before Shopify's fee">Plan value</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($plans as $plan)
                        <tr>
                            <td class="font-medium">{{ $plan->plan_name ?: 'Unnamed plan' }}</td>
                            @unless ($appId)<td>{{ $plan->app?->name }}</td>@endunless
                            <td class="r">{{ money($plan->plan_amount, $plan->plan_currency) }}</td>
                            <td class="r">{{ number_format($plan->stores) }}</td>
                            <td class="r">{{ money($plan->plan_amount * $plan->stores, $plan->plan_currency) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td class="text-ink-2">No active paid plan</td>
                        @unless ($appId)<td></td>@endunless
                        <td class="r text-ink-2">—</td>
                        <td class="r text-ink-2">{{ number_format($withoutPlan) }}</td>
                        <td class="r text-ink-2">—</td>
                    </tr>
                    @if ($plans->isNotEmpty())
                        <tr class="font-semibold">
                            <td>Total on a plan</td>
                            @unless ($appId)<td></td>@endunless
                            <td></td>
                            <td class="r">{{ number_format($plans->sum('stores')) }}</td>
                            <td class="r">{{ money($plans->sum(fn ($p) => $p->plan_amount * $p->stores)) }}</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <div class="flex items-start justify-between gap-2 px-4 pt-4">
                <div>
                    <h2 class="text-sm font-semibold">Setups</h2>
                    <p class="mt-0.5 text-xs text-ink-2">Who has what, and who assigned it</p>
                </div>
                <a href="{{ route('setups.index') }}" class="text-sm text-ink-2 hover:text-ink">All setups</a>
            </div>
            @php
                $setupTiles = [
                    ['To do', $setupStats['todo'], ['status' => 'todo'], null],
                    ['In progress', $setupStats['in_progress'], ['status' => 'in_progress'], null],
                    ['Waiting', $setupStats['waiting'], ['status' => 'waiting'], null],
                    ['Overdue', $setupStats['overdue'], [], 'var(--critical)'],
                    ['Unassigned', $setupStats['unassigned'], ['assignee' => 'none'], 'var(--warning)'],
                    ['Done · 30 days', $setupStats['done_30d'], ['status' => 'done'], 'var(--good)'],
                ];
            @endphp
            <div class="mt-3 grid grid-cols-3 gap-px border-y border-line bg-line sm:grid-cols-6">
                @foreach ($setupTiles as [$label, $count, $query, $color])
                    <a href="{{ route('setups.index', $query + array_filter(['app' => $appId])) }}" class="bg-surface px-3 py-2.5 hover:bg-surface-2">
                        <div class="text-xs text-muted">{{ $label }}</div>
                        <div class="mt-0.5 text-lg font-semibold num" @if ($color && $count) style="color: {{ $color }}" @endif>{{ number_format($count) }}</div>
                    </a>
                @endforeach
            </div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Person</th>
                        <th class="r">Open</th>
                        <th class="r">Overdue</th>
                        <th class="r">Done · 30d</th>
                        <th class="r" title="Setups this person assigned to someone else in the last 30 days">Assigned out · 30d</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($people as $person)
                        <tr>
                            <td>
                                <a href="{{ route('setups.index', ['assignee' => $person->id]) }}" class="font-medium hover:underline">{{ $person->name }}</a>
                                <div class="text-xs text-muted">{{ \App\Models\User::ROLES[$person->role] ?? $person->role }}</div>
                            </td>
                            <td class="r">{{ $person->open_setups }}</td>
                            <td class="r" @if ($person->overdue_setups) style="color: var(--critical)" @endif>{{ $person->overdue_setups }}</td>
                            <td class="r">{{ $person->done_setups }}</td>
                            <td class="r">{{ $person->handed_out }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-ink-2">No one yet — add people under <a href="{{ route('team.index') }}" class="underline">Team</a>.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="mt-8 grid gap-4 xl:grid-cols-2">
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
