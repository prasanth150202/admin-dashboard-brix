<?php

use App\Models\Transaction;
use App\Models\User;
use App\Services\Partner\PayoutPeriod;
use App\Services\Support\InboxFetcher;
use Illuminate\Support\Str;
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

Artisan::command('admin:create {email} {--name=Admin} {--role=admin : admin or team}', function (string $email) {
    if (! array_key_exists($this->option('role'), User::ROLES)) {
        $this->error('Role must be admin or team.');

        return 1;
    }

    $password = $this->secret('Password (min 10 characters)');

    if (strlen((string) $password) < 10) {
        $this->error('Password must be at least 10 characters.');

        return 1;
    }

    User::updateOrCreate(['email' => strtolower($email)], ['name' => $this->option('name'), 'role' => $this->option('role'), 'password' => $password]);
    $this->info("Login {$email} saved ({$this->option('role')}).");
})->purpose('Create or reset a dashboard login');

Artisan::command('tickets:fetch-mail {--limit=50}', function (InboxFetcher $fetcher) {
    if (! InboxFetcher::isConfigured()) {
        $this->line('Support inbox not configured (SUPPORT_IMAP_* in .env) — skipping.');

        return 0;
    }

    try {
        $result = $fetcher->fetch((int) $this->option('limit'));
    } catch (Throwable $e) {
        report($e);
        $this->error("Couldn't read the support inbox: {$e->getMessage()}");

        return 1;
    }

    $this->info("Read {$result['read']} emails into {$result['tickets']} tickets.");
    foreach ($result['errors'] as $error) {
        $this->error($error);
    }

    return $result['errors'] === [] ? 0 : 1;
})->purpose('Turn unread support-inbox mail into tickets and replies');

Artisan::command('support:token', function () {
    $this->line('Add this to .env as SUPPORT_API_TOKEN, then give the same value to the chatbot:');
    $this->info(Str::random(48));
})->purpose('Generate a token for the chatbot ticket API');

Schedule::command('partner:sync --trigger=schedule')->hourly()->withoutOverlapping(120);
Schedule::command('tickets:fetch-mail')->everyFiveMinutes()->withoutOverlapping(10);
