<x-layout :title="'Payout '.$label">
    <a href="{{ route('payouts.index') }}" class="text-sm text-ink-2 hover:text-ink">← Payouts</a>

    <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold">{{ $label }}</h1>
            <p class="mt-1 text-sm text-ink-2">{{ $isCurrent ? 'In progress — the total grows until the period closes.' : 'Estimated from transactions in this period.' }}</p>
        </div>
        <a href="{{ route('payouts.export', $start) }}" class="btn">Export CSV</a>
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <x-stat label="Net payout" :value="money($totals->net)" />
        <x-stat label="Gross charged" :value="money($totals->gross)" />
        <x-stat label="Shopify fee" :value="money($totals->fee)" />
        <x-stat label="Transactions" :value="number_format($totals->tx_count)" />
    </div>

    @if ($byApp->count() > 1)
        <section class="card mt-6 overflow-x-auto">
            <h2 class="px-4 pt-4 text-sm font-semibold">By app</h2>
            <table class="data-table mt-2">
                <thead><tr><th>App</th><th class="r">Stores</th><th class="r">Net</th></tr></thead>
                <tbody>
                @foreach ($byApp as $row)
                    <tr><td>{{ $row['app']?->name ?? 'Unknown app' }}</td><td class="r">{{ $row['stores'] }}</td><td class="r">{{ money($row['net']) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <section class="card mt-6 overflow-x-auto">
        <h2 class="px-4 pt-4 text-sm font-semibold">By store</h2>
        <table class="data-table mt-2">
            <thead><tr><th>Store</th><th>Type</th><th>App</th><th class="r">Transactions</th><th class="r">Gross</th><th class="r">Fee</th><th class="r">Net</th></tr></thead>
            <tbody>
            @foreach ($byStore as $row)
                <tr>
                    <td>
                        @if ($row->shop)
                            <a href="{{ route('stores.show', $row->shop) }}" class="font-medium hover:underline">{{ $row->shop->displayName() }}</a>
                            <div class="text-xs text-muted">{{ $row->shop->myshopify_domain }}</div>
                        @else
                            <span class="text-muted">Unknown store</span>
                        @endif
                    </td>
                    <td>@if ($row->shop)<x-kind-badge :shop="$row->shop" />@endif</td>
                    <td>{{ $row->app?->name }}</td>
                    <td class="r">{{ $row->tx_count }}</td>
                    <td class="r">{{ money($row->gross) }}</td>
                    <td class="r">{{ money($row->fee) }}</td>
                    <td class="r font-medium">{{ money($row->net) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</x-layout>
