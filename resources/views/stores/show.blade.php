<x-layout :title="$shop->displayName()">
    <a href="{{ route('stores.index') }}" class="text-sm text-ink-2 hover:text-ink">← Stores</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="flex flex-wrap items-center gap-3 text-xl font-semibold">{{ $shop->displayName() }} <x-kind-badge :shop="$shop" /></h1>
            <p class="mt-1 text-sm text-ink-2">
                <a href="https://{{ $shop->myshopify_domain }}" target="_blank" rel="noopener noreferrer" class="hover:underline">{{ $shop->myshopify_domain }}</a>
                · first seen {{ short_date($shop->first_seen_at) }}
            </p>
        </div>
        <div class="text-right">
            <div class="text-xs font-medium uppercase tracking-wide text-muted">Lifetime net</div>
            <div class="text-2xl font-semibold">{{ money($shop->lifetime_net) }}</div>
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="grid gap-4 lg:col-span-2 sm:grid-cols-2">
            @forelse ($shop->installs->sortBy('app.name') as $install)
                <section class="card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-semibold">{{ $install->app->name }}</h2>
                        <x-install-status :status="$install->status" />
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-y-2 text-sm">
                        <dt class="text-ink-2">Plan</dt>
                        <dd class="text-right">
                            @if ($install->plan_name)
                                {{ $install->plan_name }} · {{ money($install->plan_amount, $install->plan_currency) }}
                                <div class="text-xs text-muted">{{ $install->plan_status }}@if ($install->plan_test) · test charge @endif</div>
                            @else
                                <span class="text-muted">No plan</span>
                            @endif
                        </dd>
                        <dt class="text-ink-2">Net earned</dt><dd class="text-right num">{{ money($install->lifetime_net) }}</dd>
                        <dt class="text-ink-2">First installed</dt><dd class="text-right">{{ short_date($install->first_installed_at) }}</dd>
                        @if ($install->install_count > 1)
                            <dt class="text-ink-2">Installs</dt><dd class="text-right">{{ $install->install_count }}×</dd>
                        @endif
                        @if ($install->uninstalled_at)
                            <dt class="text-ink-2">Uninstalled</dt><dd class="text-right">{{ short_date($install->uninstalled_at) }}</dd>
                        @endif
                        @if ($install->plan_billing_on && $install->plan_status === 'active')
                            <dt class="text-ink-2">Next bill</dt><dd class="text-right">{{ short_date($install->plan_billing_on) }}</dd>
                        @endif
                    </dl>
                    @if ($install->uninstall_reason)
                        <p class="mt-3 text-xs text-ink-2">Reason: {{ $install->uninstall_reason }}</p>
                    @endif
                </section>
            @empty
                <p class="text-sm text-ink-2">No install events for this store — it only appears in transactions.</p>
            @endforelse
        </div>

        <form method="POST" action="{{ route('stores.update', $shop) }}" class="card p-4">
            @csrf @method('PATCH')
            <h2 class="font-semibold">Store type</h2>
            <p class="mt-1 text-xs text-ink-2">Detected: paid → Client, test charges only → Test, otherwise Unpaid. Override it if that's wrong.</p>
            <select name="kind_override" class="field mt-3 w-full">
                <option value="">Automatic</option>
                <option value="client" @selected($shop->kind_override === 'client')>Client</option>
                <option value="test" @selected($shop->kind_override === 'test')>Test</option>
            </select>
            <label for="notes" class="mt-4 block text-sm font-medium">Notes</label>
            <textarea id="notes" name="notes" rows="4" class="field mt-1 w-full">{{ old('notes', $shop->notes) }}</textarea>
            <button class="btn btn-primary mt-3">Save</button>
        </form>
    </div>

    <section class="card mt-6">
        <h2 class="px-4 pt-4 text-sm font-semibold">Transactions</h2>
        <div class="mt-2 overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Date</th><th>App</th><th>Type</th><th>Payout period</th><th class="r">Gross</th><th class="r">Fee</th><th class="r">Net</th></tr></thead>
                <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td class="whitespace-nowrap">{{ short_date($t->occurred_at) }}</td>
                        <td>{{ $t->app?->name }}</td>
                        <td>{{ $t->label() }}@if ($t->billing_interval)<span class="text-xs text-muted"> · {{ strtolower($t->billing_interval) }}</span>@endif</td>
                        <td class="whitespace-nowrap"><a href="{{ route('payouts.show', $t->payout_period_start->toDateString()) }}" class="hover:underline">{{ \App\Services\Partner\PayoutPeriod::label($t->payout_period_start, $t->payout_period_end) }}</a></td>
                        <td class="r">{{ money($t->gross_amount, $t->currency) }}</td>
                        <td class="r">{{ money($t->shopify_fee, $t->currency) }}</td>
                        <td class="r font-medium">{{ money($t->net_amount, $t->currency) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-ink-2">No transactions — this store hasn't paid for any app.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())<div class="px-4 pb-4">{{ $transactions->links() }}</div>@endif
    </section>

    <section class="card mt-6">
        <h2 class="px-4 pt-4 text-sm font-semibold">Event timeline</h2>
        <div class="mt-2 overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>When</th><th>App</th><th>Event</th><th>Details</th></tr></thead>
                <tbody>
                @forelse ($events as $e)
                    <tr>
                        <td class="whitespace-nowrap" title="{{ $e->occurred_at }}">{{ $e->occurred_at->format('M j, Y H:i') }}</td>
                        <td>{{ $e->app->name }}</td>
                        <td>{{ $e->label() }}</td>
                        <td class="text-ink-2">
                            @if ($e->charge_name){{ $e->charge_name }} · {{ money($e->charge_amount, $e->charge_currency) }}@if ($e->charge_test) · test @endif @endif
                            @if ($e->reason){{ $e->reason }}@endif
                            @if ($e->description)<div class="text-xs">{{ $e->description }}</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-ink-2">No events.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
