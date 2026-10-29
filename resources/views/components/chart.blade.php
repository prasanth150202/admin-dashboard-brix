@props(['title', 'spec', 'height' => 'h-64'])
{{-- A bar chart plus the same numbers as a table, for screen readers and exact values. --}}
<section {{ $attributes->class('card p-4') }}>
    <h2 class="text-sm font-semibold">{{ $title }}</h2>
    <div class="relative mt-3 {{ $height }}">
        <canvas data-chart='@json($spec)' role="img" aria-label="{{ $title }}"></canvas>
    </div>
    <details class="mt-3 text-sm">
        <summary class="cursor-pointer text-xs text-muted hover:text-ink">View as table</summary>
        <div class="mt-2 overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Month</th>
                    @foreach ($spec['series'] as $s)<th class="r">{{ $s['label'] }}</th>@endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($spec['labels'] as $i => $label)
                    <tr>
                        <td>{{ $label }}</td>
                        @foreach ($spec['series'] as $s)
                            <td class="r">{{ ($spec['format'] ?? null) === 'money' ? money($s['data'][$i]) : $s['data'][$i] }}</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
</section>
