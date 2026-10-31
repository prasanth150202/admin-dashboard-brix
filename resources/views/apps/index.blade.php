<x-layout title="Apps">
    <h1 class="text-xl font-semibold">Apps</h1>
    <p class="mt-1 text-sm text-ink-2">Every app in the dashboard, side by side. Add apps in <a href="{{ route('settings') }}" class="underline">Settings</a>.</p>

    <div class="card mt-6 overflow-x-auto">
        <table class="data-table">
            <thead>
            <tr>
                <th>App</th>
                <th class="r">Installed</th>
                <th class="r">Paying now</th>
                <th class="r">Test</th>
                <th class="r">Uninstalled</th>
                <th class="r">Ever installed</th>
                <th class="r" title="Sum of active plan prices, before Shopify's fee">Active plans (gross)</th>
                <th class="r">Net · 30 days</th>
                <th class="r">Lifetime net</th>
                <th>Setup checklist</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="font-medium"><a href="{{ route('overview', ['app' => $row['app']->id]) }}" class="hover:underline">{{ $row['app']->name }}</a></td>
                    <td class="r"><a href="{{ route('stores.index', ['app' => $row['app']->id, 'status' => 'installed']) }}" class="hover:underline">{{ number_format($row['installed']) }}</a></td>
                    <td class="r">{{ number_format($row['paying_now']) }}</td>
                    <td class="r">{{ number_format($row['test']) }}</td>
                    <td class="r">{{ number_format($row['uninstalled']) }}</td>
                    <td class="r">{{ number_format($row['ever']) }}</td>
                    <td class="r">{{ money($row['plan_value']) }}</td>
                    <td class="r">{{ money($row['net_30d']) }}</td>
                    <td class="r">{{ money($row['lifetime_net']) }}</td>
                    <td class="whitespace-nowrap"><a href="{{ route('apps.checklist', $row['app']) }}" class="hover:underline">{{ $row['steps'] ? $row['steps'].' '.Str::plural('step', $row['steps']) : 'Write checklist' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="10"class="text-ink-2">No apps yet. <a href="{{ route('settings') }}" class="underline">Add one in Settings</a>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layout>
