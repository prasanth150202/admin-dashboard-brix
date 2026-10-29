<x-layout title="Payouts">
    <h1 class="text-xl font-semibold">Payouts</h1>
    <p class="mt-1 max-w-2xl text-sm text-ink-2">
        Transactions grouped into {{ $schedule }} payout periods. The Partner API doesn't return the payouts themselves, so these are estimates — check them against the payout history in the Partner Dashboard.
    </p>

    <div class="card mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr><th>Period</th><th class="r">Stores</th><th class="r">Transactions</th><th class="r">Gross</th><th class="r">Shopify fee</th><th class="r">Net</th></tr>
            </thead>
            <tbody>
            @forelse ($periods as $p)
                @php
                    $start = \Carbon\CarbonImmutable::parse($p->payout_period_start);
                    $end = \Carbon\CarbonImmutable::parse($p->payout_period_end);
                @endphp
                <tr>
                    <td class="whitespace-nowrap">
                        <a href="{{ route('payouts.show', $start->toDateString()) }}" class="font-medium hover:underline">{{ \App\Services\Partner\PayoutPeriod::label($start, $end) }}</a>
                        @if (now()->between($start, $end->endOfDay()))<span class="ml-1 text-xs text-muted">in progress</span>@endif
                    </td>
                    <td class="r">{{ number_format($p->shop_count) }}</td>
                    <td class="r">{{ number_format($p->tx_count) }}</td>
                    <td class="r">{{ money($p->gross) }}</td>
                    <td class="r">{{ money($p->fee) }}</td>
                    <td class="r font-medium">{{ money($p->net) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-ink-2">No transactions synced yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $periods->links() }}</div>
</x-layout>
