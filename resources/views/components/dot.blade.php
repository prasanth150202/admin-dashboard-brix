@props(['label', 'color' => 'var(--muted)'])
<span {{ $attributes->class('inline-flex items-center gap-1.5 text-xs text-ink-2 whitespace-nowrap') }}>
    <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $color }}"></span>{{ $label }}
</span>
