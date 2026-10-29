<x-layout title="Settings">
    <h1 class="text-xl font-semibold">Settings</h1>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <section class="card p-4">
            <h2 class="font-semibold">Partner API connection</h2>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                <dt class="text-ink-2">Organization ID</dt><dd>{{ $orgId ?: '—' }}</dd>
                <dt class="text-ink-2">Access token</dt>
                <dd class="flex items-center gap-1.5">
                    <span class="h-2 w-2 rounded-full" style="background: {{ $configured ? 'var(--good)' : 'var(--critical)' }}"></span>
                    {{ $configured ? 'Set' : 'Missing' }}
                </dd>
                <dt class="text-ink-2">API version</dt><dd>{{ $apiVersion }}</dd>
                <dt class="text-ink-2">Backfill from</dt><dd>{{ $backfillFrom }}</dd>
                <dt class="text-ink-2">Payout schedule</dt><dd>{{ $schedule }}</dd>
            </dl>
            <p class="mt-3 text-xs text-ink-2">These come from <code>.env</code> on the server (<code>SHOPIFY_PARTNER_ORG_ID</code>, <code>SHOPIFY_PARTNER_API_TOKEN</code>). The token needs <b>View apps</b> and <b>View financials</b>.</p>
            <form method="POST" action="{{ route('settings.test') }}" class="mt-4">
                @csrf
                <button class="btn" @disabled(! $configured)>Test connection</button>
            </form>
        </section>

        <section class="card p-4">
            <h2 class="font-semibold">Sync</h2>
            <p class="mt-1 text-sm text-ink-2">Runs hourly via cron. An incremental sync re-reads the last {{ config('partner.overlap_days') }} days onward; a full sync re-reads everything since {{ $backfillFrom }}.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <form method="POST" action="{{ route('settings.sync') }}">
                    @csrf
                    <button class="btn btn-primary" @disabled(! $configured || $apps->isEmpty())>Sync now</button>
                </form>
                <form method="POST" action="{{ route('settings.sync') }}" onsubmit="return confirm('A full sync can take several minutes. Over SSH, `php artisan partner:sync --full` is safer. Run it here anyway?')">
                    @csrf
                    <input type="hidden" name="full" value="1">
                    <button class="btn" @disabled(! $configured || $apps->isEmpty())>Full resync</button>
                </form>
            </div>
        </section>
    </div>

    <section class="card mt-4 p-4">
        <h2 class="font-semibold">Apps</h2>
        <p class="mt-1 text-sm text-ink-2">The Partner API can't list your apps, so add each one by its app ID — the number in its Partner Dashboard URL (<code>…/apps/<b>1234567</b></code>).</p>

        <form method="POST" action="{{ route('settings.apps.store') }}" class="mt-4 flex flex-wrap items-start gap-2">
            @csrf
            <div>
                <input name="partner_app_id" value="{{ old('partner_app_id') }}" placeholder="App ID or URL" class="field w-64" aria-label="Partner app ID" required>
                @error('partner_app_id')<p class="mt-1 text-sm" style="color: var(--critical)">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary" @disabled(! $configured)>Add app</button>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>App</th><th>App ID</th><th class="r">Stores</th><th>Events synced to</th><th>Transactions synced to</th><th></th></tr></thead>
                <tbody>
                @forelse ($apps as $app)
                    <tr>
                        <td class="font-medium">{{ $app->name }}</td>
                        <td class="num text-ink-2">{{ $app->partner_app_id }}</td>
                        <td class="r">{{ number_format($app->installs_count) }}</td>
                        <td class="whitespace-nowrap">{{ $app->events_synced_until?->format('M j, Y H:i') ?? 'Never' }}</td>
                        <td class="whitespace-nowrap">{{ $app->transactions_synced_until?->format('M j, Y H:i') ?? 'Never' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('settings.apps.destroy', $app) }}" onsubmit="return confirm(@js('Remove '.$app->name.' and all of its synced data?'))">
                                @csrf @method('DELETE')
                                <button class="text-sm text-ink-2 hover:text-ink">Remove</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-ink-2">No apps added yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card mt-4 p-4">
        <h2 class="font-semibold">Recent syncs</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Started</th><th>Trigger</th><th>Mode</th><th>Result</th><th class="r">Events</th><th class="r">Transactions</th><th>Message</th></tr></thead>
                <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td class="whitespace-nowrap">{{ $run->started_at->format('M j, H:i') }}</td>
                        <td>{{ $run->trigger }}</td>
                        <td>{{ $run->mode }}</td>
                        <td class="whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" style="background: {{ ['ok' => 'var(--good)', 'failed' => 'var(--critical)'][$run->status] ?? 'var(--warning)' }}"></span>{{ $run->status }}
                            </span>
                        </td>
                        <td class="r">{{ number_format($run->events_fetched) }}</td>
                        <td class="r">{{ number_format($run->transactions_fetched) }}</td>
                        <td class="text-ink-2 whitespace-pre-line text-xs">{{ $run->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-ink-2">No syncs yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</x-layout>
