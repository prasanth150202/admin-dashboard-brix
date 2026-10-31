<?php

namespace App\Services\Partner;

use App\Models\PartnerApp;
use App\Models\SyncRun;
use App\Services\Support\SetupPlanner;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * One sync pass over every configured app: events, then transactions,
 * then a rollup of the shops either feed touched. Each app is synced on
 * its own, so one failing app doesn't stop the others.
 */
class SyncRunner
{
    public function __construct(
        private readonly EventSync $events,
        private readonly TransactionSync $transactions,
        private readonly ShopRollup $rollup,
        private readonly SetupPlanner $setups,
    ) {}

    public function run(bool $full = false, string $trigger = 'cli'): SyncRun
    {
        $run = SyncRun::create([
            'trigger' => $trigger,
            'mode' => $full ? 'full' : 'incremental',
            'started_at' => now(),
        ]);

        $errors = [];
        $touched = [];

        foreach (PartnerApp::orderBy('name')->get() as $app) {
            try {
                $result = $this->events->sync($app, $this->since($app->events_synced_until, $full));
                $run->increment('events_fetched', $result['fetched']);
                $touched = array_merge($touched, $result['shop_ids']);

                $result = $this->transactions->sync($app, $this->since($app->transactions_synced_until, $full));
                $run->increment('transactions_fetched', $result['fetched']);
                $touched = array_merge($touched, $result['shop_ids']);
            } catch (Throwable $e) {
                report($e);
                $errors[] = "{$app->name}: {$e->getMessage()}";
            }
        }

        $rebuilt = $this->rollup->rebuild($full ? null : array_values(array_unique($touched)));

        try {
            $planned = $this->setups->planRecentInstalls();
        } catch (Throwable $e) {
            report($e);
            $planned = 0;
            $errors[] = "Setups: {$e->getMessage()}";
        }

        $run->forceFill([
            'status' => $errors === [] ? 'ok' : 'failed',
            'message' => $errors === [] ? "Rebuilt {$rebuilt} stores.".($planned ? " Started {$planned} setups." : '') : implode("\n", $errors),
            'finished_at' => now(),
        ])->save();

        return $run;
    }

    private function since(?CarbonInterface $syncedUntil, bool $full): CarbonImmutable
    {
        $backfill = CarbonImmutable::parse(config('partner.backfill_from'))->utc();

        if ($full || $syncedUntil === null) {
            return $backfill;
        }

        return CarbonImmutable::parse($syncedUntil)->subDays(config('partner.overlap_days'))->max($backfill);
    }
}
