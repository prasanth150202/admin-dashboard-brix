<?php

namespace App\Http\Controllers;

use App\Models\AppEvent;
use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Shop;
use App\Models\SyncRun;
use App\Models\Transaction;
use App\Services\Partner\PayoutPeriod;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    /** Categorical slots hold 8 hues; past 7 apps the rest fold into "Other". */
    private const MAX_SERIES = 7;

    public function __invoke(Request $request)
    {
        if (! $request->user()->isAdmin()) {
            return redirect()->route('tickets.index');
        }

        $apps = PartnerApp::orderBy('id')->get();
        $appId = $apps->firstWhere('id', $request->integer('app'))?->id;

        $installs = AppInstall::query()->when($appId, fn ($q) => $q->where('partner_app_id', $appId));
        $transactions = Transaction::query()->when($appId, fn ($q) => $q->where('partner_app_id', $appId));
        $installedShops = fn (string $kind) => Shop::where('kind', $kind)
            ->whereHas('installs', fn ($q) => $q->where('status', AppInstall::STATUS_INSTALLED)
                ->when($appId, fn ($q) => $q->where('partner_app_id', $appId)))
            ->count();

        [$periodStart, $periodEnd] = PayoutPeriod::for(now());

        $stats = [
            'installed_stores' => (clone $installs)->where('status', AppInstall::STATUS_INSTALLED)->distinct()->count('shop_id'),
            'paying_now' => (clone $installs)->payingNow()->distinct()->count('shop_id'),
            'client_stores' => $installedShops(Shop::KIND_CLIENT),
            'test_stores' => $installedShops(Shop::KIND_TEST),
            'unknown_stores' => $installedShops(Shop::KIND_UNKNOWN),
            'net_30d' => (clone $transactions)->where('occurred_at', '>=', now()->subDays(30))->sum('net_amount'),
            'period_net' => (clone $transactions)->whereDate('payout_period_start', $periodStart)->sum('net_amount'),
            'lifetime_net' => (clone $transactions)->sum('net_amount'),
        ];

        $topStores = (clone $transactions)
            ->selectRaw('shop_id, SUM(net_amount) as net')
            ->whereNotNull('shop_id')
            ->groupBy('shop_id')
            ->orderByDesc('net')
            ->having('net', '>', 0)
            ->limit(10)
            ->with('shop')
            ->get();

        $recent = AppEvent::with(['app', 'shop'])
            ->when($appId, fn ($q) => $q->where('partner_app_id', $appId))
            ->latest('occurred_at')
            ->limit(12)
            ->get();

        return view('overview', [
            'apps' => $apps,
            'appId' => $appId,
            'stats' => $stats,
            'periodLabel' => PayoutPeriod::label($periodStart, $periodEnd),
            'revenueChart' => $this->revenueChart($apps, $appId),
            'installChart' => $this->installChart($appId),
            'topStores' => $topStores,
            'recent' => $recent,
            'lastSync' => SyncRun::latest('started_at')->first(),
        ]);
    }

    /** @return list<string> the last 12 months as Y-m, oldest first */
    private function months(): array
    {
        return collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i)->format('Y-m'))->all();
    }

    private function revenueChart($apps, ?int $appId): array
    {
        $months = $this->months();
        $shown = ($appId ? $apps->where('id', $appId) : $apps)->values();
        $named = $shown->take($shown->count() > self::MAX_SERIES + 1 ? self::MAX_SERIES : self::MAX_SERIES + 1);

        $series = [];
        foreach ($named as $app) {
            $series[$app->id] = ['label' => $app->name, 'data' => array_fill_keys($months, 0.0)];
        }
        if ($shown->count() > $named->count()) {
            $series['other'] = ['label' => 'Other apps', 'data' => array_fill_keys($months, 0.0)];
        }

        Transaction::query()
            ->when($appId, fn ($q) => $q->where('partner_app_id', $appId))
            ->where('occurred_at', '>=', now()->startOfMonth()->subMonths(11))
            ->get(['partner_app_id', 'occurred_at', 'net_amount'])
            ->each(function ($t) use (&$series) {
                $key = isset($series[$t->partner_app_id]) ? $t->partner_app_id : 'other';
                $month = $t->occurred_at->format('Y-m');
                if (isset($series[$key]['data'][$month])) {
                    $series[$key]['data'][$month] += (float) $t->net_amount;
                }
            });

        return [
            'labels' => array_map(fn ($m) => \Carbon\Carbon::parse("{$m}-01")->format('M y'), $months),
            'series' => array_values(array_map(fn ($s) => [
                'label' => $s['label'],
                'data' => array_map(fn ($v) => round($v, 2), array_values($s['data'])),
            ], $series)),
        ];
    }

    private function installChart(?int $appId): array
    {
        $months = $this->months();
        $counts = [
            AppEvent::INSTALLED => array_fill_keys($months, 0),
            AppEvent::UNINSTALLED => array_fill_keys($months, 0),
        ];

        AppEvent::query()
            ->when($appId, fn ($q) => $q->where('partner_app_id', $appId))
            ->whereIn('type', array_keys($counts))
            ->where('occurred_at', '>=', now()->startOfMonth()->subMonths(11))
            ->get(['type', 'occurred_at'])
            ->each(function ($e) use (&$counts) {
                $month = $e->occurred_at->format('Y-m');
                if (isset($counts[$e->type][$month])) {
                    $counts[$e->type][$month]++;
                }
            });

        return [
            'labels' => array_map(fn ($m) => \Carbon\Carbon::parse("{$m}-01")->format('M y'), $months),
            'series' => [
                ['label' => 'Installs', 'data' => array_values($counts[AppEvent::INSTALLED])],
                ['label' => 'Uninstalls', 'data' => array_values($counts[AppEvent::UNINSTALLED])],
            ],
        ];
    }
}
