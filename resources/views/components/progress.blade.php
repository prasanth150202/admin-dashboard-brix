@props(['done', 'total'])
@php $pct = $total > 0 ? round($done / $total * 100) : 0; @endphp
<div {{ $attributes->class('flex items-center gap-2') }}>
    <div class="h-1.5 w-20 overflow-hidden rounded-full bg-surface-2" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100">
        <div class="h-full rounded-full" style="width: {{ $pct }}%; background: {{ $total > 0 && $done === $total ? 'var(--good)' : 'var(--accent)' }}"></div>
    </div>
    <span class="num text-xs text-ink-2">{{ $done }}/{{ $total }}</span>
</div>
