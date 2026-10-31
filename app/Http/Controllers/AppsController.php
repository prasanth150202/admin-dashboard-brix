<?php

namespace App\Http\Controllers;

use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Shop;

class AppsController extends Controller
{
    public function index()
    {
        $apps = PartnerApp::orderBy('name')->get()->map(function (PartnerApp $app) {
            $installs = AppInstall::where('partner_app_id', $app->id);
            $installed = fn () => (clone $installs)->where('status', AppInstall::STATUS_INSTALLED);

            return [
                'app' => $app,
                'installed' => $installed()->count(),
                'ever' => (clone $installs)->count(),
                'uninstalled' => (clone $installs)->where('status', AppInstall::STATUS_UNINSTALLED)->count(),
                'paying_now' => (clone $installs)->payingNow()->count(),
                'test' => $installed()->whereHas('shop', fn ($q) => $q->where('kind', Shop::KIND_TEST))->count(),
                'plan_value' => (clone $installs)->payingNow()->sum('plan_amount'),
                'net_30d' => $app->transactions()->where('occurred_at', '>=', now()->subDays(30))->sum('net_amount'),
                'lifetime_net' => $app->transactions()->sum('net_amount'),
                'steps' => $app->setupSteps()->count(),
            ];
        });

        return view('apps.index', ['rows' => $apps]);
    }
}
