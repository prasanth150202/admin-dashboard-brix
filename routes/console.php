<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\Partner\PayoutPeriod;
use App\Services\Partner\SyncRunner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('partner:sync {--full : Re-read everything since PARTNER_BACKFILL_FROM} {--trigger=cli}', function (SyncRunner $runner) {
    $this->info($this->option('full') ? 'Running full sync…' : 'Running incremental sync…');

    $run = $runner->run((bool) $this->option('full'), $this->option('trigger'));

    $this->line("Events: {$run->events_fetched}, transactions: {$run->transactions_fetched}");
    $run->status === 'ok' ? $this->info($run->message) : $this->error($run->message);

    return $run->status === 'ok' ? 0 : 1;
})->purpose('Pull app events and transactions from the Shopify Partner API');

Artisan::command('partner:payout-periods', function () {
    $count = 0;

    Transaction::query()->chunkById(500, function ($rows) use (&$count) {
        foreach ($rows as $row) {
            [$start, $end] = PayoutPeriod::for($row->occurred_at);
            $row->forceFill(['payout_period_start' => $start, 'payout_period_end' => $end])->save();
            $count++;
        }
    });

    $this->info("Regrouped {$count} transactions using the '".config('partner.payout_schedule')."' schedule.");
})->purpose('Regroup transactions into payout periods after changing PARTNER_PAYOUT_SCHEDULE');

Artisan::command('admin:create {email} {--name=Admin}', function (string $email) {
    $password = $this->secret('Password (min 10 characters)');

    if (strlen((string) $password) < 10) {
        $this->error('Password must be at least 10 characters.');

        return 1;
    }

    User::updateOrCreate(['email' => strtolower($email)], ['name' => $this->option('name'), 'password' => $password]);
    $this->info("Admin {$email} saved.");
})->purpose('Create or reset a dashboard login');

Schedule::command('partner:sync --trigger=schedule')->hourly()->withoutOverlapping(120);
