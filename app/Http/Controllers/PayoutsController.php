<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\Partner\PayoutPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayoutsController extends Controller
{
    public function index()
    {
        $periods = Transaction::query()
            ->selectRaw('payout_period_start, payout_period_end, COUNT(*) as tx_count, COUNT(DISTINCT shop_id) as shop_count,
                SUM(gross_amount) as gross, SUM(shopify_fee) as fee, SUM(net_amount) as net')
            ->groupBy('payout_period_start', 'payout_period_end')
            ->orderByDesc('payout_period_start')
            ->paginate(24);

        return view('payouts.index', ['periods' => $periods, 'schedule' => config('partner.payout_schedule')]);
    }

    public function show(string $start)
    {
        [$from, $to] = $this->period($start);
        $base = Transaction::whereDate('payout_period_start', $from);

        abort_unless((clone $base)->exists(), 404);

        $byStore = (clone $base)
            ->selectRaw('shop_id, partner_app_id, COUNT(*) as tx_count, SUM(gross_amount) as gross, SUM(shopify_fee) as fee, SUM(net_amount) as net')
            ->groupBy('shop_id', 'partner_app_id')
            ->orderByDesc('net')
            ->with(['shop', 'app'])
            ->get();

        $byApp = $byStore->groupBy('partner_app_id')->map(fn ($rows) => [
            'app' => $rows->first()->app,
            'stores' => $rows->count(),
            'net' => $rows->sum('net'),
        ])->sortByDesc('net')->values();

        return view('payouts.show', [
            'label' => PayoutPeriod::label($from, $to),
            'start' => $start,
            'totals' => (clone $base)->selectRaw('SUM(gross_amount) as gross, SUM(shopify_fee) as fee, SUM(net_amount) as net, COUNT(*) as tx_count')->first(),
            'byStore' => $byStore,
            'byApp' => $byApp,
            'isCurrent' => now()->between($from, $to->endOfDay()),
        ]);
    }

    public function export(string $start): StreamedResponse
    {
        [$from] = $this->period($start);
        $query = Transaction::whereDate('payout_period_start', $from)->with(['shop', 'app'])->orderBy('occurred_at');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'App', 'Store', 'Domain', 'Store type', 'Transaction type', 'Gross', 'Shopify fee', 'Net', 'Currency', 'Transaction ID']);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $t) {
                    fputcsv($out, [
                        $t->occurred_at->toDateTimeString(), $t->app?->name, $t->shop?->name, $t->shop?->myshopify_domain,
                        $t->shop?->kind, $t->label(), $t->gross_amount, $t->shopify_fee, $t->net_amount, $t->currency,
                        $t->partner_transaction_id,
                    ]);
                }
            });

            fclose($out);
        }, "payout-{$start}.csv", ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function period(string $start): array
    {
        return PayoutPeriod::for(CarbonImmutable::parse($start));
    }
}
