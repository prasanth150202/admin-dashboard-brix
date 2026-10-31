<?php

namespace App\Http\Controllers;

use App\Models\AppEvent;
use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Setup;
use App\Models\Shop;
use App\Models\SyncRun;
use App\Models\Transaction;
use App\Models\User;
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
        [$prevStart, $prevEnd] = PayoutPeriod::for($periodStart->copy()->subDay());

        $events = AppEvent::query()->when($appId, fn ($q) => $q->where('partner_app_id', $appId));
        $eventsBetween = fn (string $type, int $fromDaysAgo, int $toDaysAgo) => (clone $events)
            ->where('type', $type)
            ->whereBetween('occurred_at', [now()->subDays($fromDaysAgo), now()->subDays($toDaysAgo)])
            ->count();
        $realStores = fn ($q) => $q->where('kind', '!=', Shop::KIND_TEST);
        $pct = fn (int $part, int $whole) => $whole > 0 ? round($part / $whole * 100, 1) : null;

        // An "install" is one store × one app; a store with two apps counts twice.
        $everInstalled = (clone $installs)->count();
        $installedNow = (clone $installs)->where('status', AppInstall::STATUS_INSTALLED)->count();
        $realEver = (clone $installs)->whereHas('shop', $realStores)->distinct()->count('shop_id');
        $paidEver = (clone $installs)->whereHas('shop', fn ($q) => $q->where('kind', Shop::KIND_CLIENT))->distinct()->count('shop_id');

        $stats = [
            'total_installs' => $everInstalled,
            'installed_now' => $installedNow,
            'actual_stores' => $installedShops(Shop::KIND_CLIENT),
            'dev_stores' => $installedShops(Shop::KIND_TEST),
            'unpaid_stores' => $installedShops(Shop::KIND_UNKNOWN),
            'paying_now' => (clone $installs)->payingNow()->distinct()->count('shop_id'),

            'installs_30d' => $eventsBetween(AppEvent::INSTALLED, 30, 0),
            'installs_prev_30d' => $eventsBetween(AppEvent::INSTALLED, 60, 30),
            'uninstalls_30d' => $eventsBetween(AppEvent::UNINSTALLED, 30, 0),
            'uninstalls_prev_30d' => $eventsBetween(AppEvent::UNINSTALLED, 60, 30),
            'install_rate' => $pct($installedNow, $everInstalled),
            'uninstall_rate' => $pct($everInstalled - $installedNow, $everInstalled),
            'paid_conversion' => $pct($paidEver, $realEver),

            'period_net' => (clone $transactions)->whereDate('payout_period_start', $periodStart)->sum('net_amount'),
            'prev_period_net' => (clone $transactions)->whereDate('payout_period_start', $prevStart)->sum('net_amount'),
            'net_30d' => (clone $transactions)->where('occurred_at', '>=', now()->subDays(30))->sum('net_amount'),
            'lifetime_net' => (clone $transactions)->sum('net_amount'),
        ];

        // Actual (non-development) stores on each active, real plan right now.
        $plans = (clone $installs)
            ->with('app')
            ->where('status', AppInstall::STATUS_INSTALLED)
            ->where('plan_status', 'active')
            ->where('plan_test', false)
            ->whereHas('shop', $realStores)
            ->selectRaw('partner_app_id, plan_name, plan_amount, plan_currency, COUNT(*) as stores')
            ->groupBy('partner_app_id', 'plan_name', 'plan_amount', 'plan_currency')
            ->orderByDesc('stores')
            ->get();
        $withoutPlan = (clone $installs)
            ->where('status', AppInstall::STATUS_INSTALLED)
            ->whereHas('shop', $realStores)
            ->where(fn ($q) => $q->whereNull('plan_status')->orWhere('plan_status', '!=', 'active')->orWhere('plan_test', true))
            ->count();

        $setups = Setup::query()->when($appId, fn ($q) => $q->where('partner_app_id', $appId));
        $setupStats = [
            'todo' => (clone $setups)->where('status', 'todo')->count(),
            'in_progress' => (clone $setups)->where('status', 'in_progress')->count(),
            'waiting' => (clone $setups)->where('status', 'waiting')->count(),
            'overdue' => (clone $setups)->active()->whereDate('due_on', '<', today())->count(),
            'unassigned' => (clone $setups)->active()->whereNull('assignee_id')->count(),
            'done_30d' => (clone $setups)->where('status', 'done')->where('completed_at', '>=', now()->subDays(30))->count(),
        ];

        $forApp = fn ($q) => $q->when($appId, fn ($q) => $q->where('partner_app_id', $appId));
        $people = User::withCount([
            'assignedSetups as open_setups' => fn ($q) => $forApp($q->active()),
            'assignedSetups as overdue_setups' => fn ($q) => $forApp($q->active()->whereDate('due_on', '<', today())),
            'assignedSetups as done_setups' => fn ($q) => $forApp($q->where('status', 'done')->where('completed_at', '>=', now()->subDays(30))),
            'setupsHandedOut as handed_out' => fn ($q) => $forApp($q->where('assigned_at', '>=', now()->subDays(30))->whereColumn('assigned_by', '!=', 'assignee_id')),
        ])->orderByRaw("CASE role WHEN 'sales' THEN 0 WHEN 'team' THEN 1 ELSE 2 END")->orderBy('name')->get();

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
            'prevPeriodLabel' => PayoutPeriod::label($prevStart, $prevEnd),
            'periodStart' => $periodStart,
            'prevStart' => $prevStart,
            'plans' => $plans,
            'withoutPlan' => $withoutPlan,
            'setupStats' => $setupStats,
            'people' => $people,
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
