@props(['shop'])
@php
    $styles = [
        'client' => ['Client', 'var(--series-1)'],
        'test' => ['Test', 'var(--series-4)'],
        'unknown' => ['Unpaid', 'var(--muted)'],
    ];
    [$label, $color] = $styles[$shop->kind] ?? $styles['unknown'];
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full border border-line px-2 py-0.5 text-xs text-ink-2 whitespace-nowrap"
      title="{{ $shop->kind_override ? 'Set by hand' : 'Detected automatically' }}">
    <span class="h-2 w-2 rounded-full" style="background: {{ $color }}"></span>{{ $label }}@if ($shop->kind_override)<span class="text-muted">·manual</span>@endif
</span>
