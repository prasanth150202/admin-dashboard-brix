<?php

namespace App\Services\Support;

use App\Models\AppInstall;
use App\Models\PartnerApp;
use App\Models\Setup;
use App\Models\Shop;
use Illuminate\Support\Facades\DB;

/**
 * Starts a setup, with a copy of the app's checklist, for each store that
 * recently installed an app. Runs after every sync.
 */
class SetupPlanner
{
    public function planRecentInstalls(): int
    {
        $created = 0;

        AppInstall::query()
            ->with(['shop', 'app'])
            ->where('status', AppInstall::STATUS_INSTALLED)
            ->where('last_installed_at', '>=', now()->subDays(config('support.setups_auto_days')))
            ->whereHas('shop', fn ($q) => $q->where('kind', '!=', Shop::KIND_TEST))
            ->each(function (AppInstall $install) use (&$created) {
                $created += $this->start($install->shop, $install->app)->wasRecentlyCreated ? 1 : 0;
            });

        return $created;
    }

    /** Returns the existing setup if this store already has one for the app. */
    public function start(Shop $shop, PartnerApp $app): Setup
    {
        return DB::transaction(function () use ($shop, $app) {
            $setup = Setup::firstOrCreate(['shop_id' => $shop->id, 'partner_app_id' => $app->id]);

            if ($setup->wasRecentlyCreated) {
                foreach ($app->setupSteps as $step) {
                    $setup->tasks()->create(['title' => $step->title, 'position' => $step->position]);
                }
            }

            return $setup;
        });
    }
}
