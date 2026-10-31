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

    <section id="api-keys" class="card mt-4 p-4">
        <h2 class="font-semibold">API keys</h2>
        <p class="mt-1 text-sm text-ink-2">
            For the chatbot and anything else that calls the REST API at <code>{{ url('/api') }}</code>.
            Send the key as <code>Authorization: Bearer &lt;key&gt;</code>. Make one key per tool, so you can revoke one without breaking the others.
        </p>

        @if ($new = session('new_api_key'))
            <div class="mt-4 rounded-lg border p-3" style="border-color: var(--good); background: var(--surface-2)" x-data="{ copied: false }">
                <p class="text-sm font-medium">New key “{{ $new['name'] }}” — copy it now. It won't be shown again.</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="field num flex-1 select-all break-all" x-ref="key">{{ $new['token'] }}</code>
                    <button type="button" class="btn" x-on:click="navigator.clipboard.writeText($refs.key.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                    </button>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.api-keys.store') }}" class="mt-4 flex flex-wrap items-start gap-2">
            @csrf
            <div>
                <input name="name" value="{{ old('name') }}" placeholder="Name, e.g. Website chatbot" maxlength="120" required class="field w-64" aria-label="Key name">
                @error('name')<p class="mt-1 text-sm" style="color: var(--critical)">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary">Create key</button>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Key</th><th>Created</th><th>Last used</th><th></th></tr></thead>
                <tbody>
                @forelse ($apiKeys as $key)
                    <tr>
                        <td class="font-medium">{{ $key->name }}</td>
                        <td class="num text-ink-2">{{ $key->prefix }}…</td>
                        <td class="whitespace-nowrap">{{ short_date($key->created_at) }}@if ($key->creator)<span class="text-xs text-muted"> · {{ $key->creator->name }}</span>@endif</td>
                        <td class="whitespace-nowrap">{{ $key->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="text-right">
                            <form method="POST" action="{{ route('settings.api-keys.destroy', $key) }}" onsubmit="return confirm(@js('Revoke “'.$key->name.'”? Anything using it stops working immediately.'))">
                                @csrf @method('DELETE')
                                <button class="text-sm text-ink-2 hover:text-ink">Revoke</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-ink-2">No keys yet.@if (config('support.api_token')) The <code>SUPPORT_API_TOKEN</code> in .env also works.@endif</td></tr>
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
