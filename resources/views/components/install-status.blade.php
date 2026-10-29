@props(['status'])
@php
    [$label, $color] = match ($status) {
        'installed' => ['Installed', 'var(--good)'],
        'deactivated' => ['Deactivated', 'var(--warning)'],
        default => ['Uninstalled', 'var(--muted)'],
    };
@endphp
<span class="inline-flex items-center gap-1.5 text-xs text-ink-2 whitespace-nowrap">
    <span class="h-2 w-2 rounded-full" style="background: {{ $color }}"></span>{{ $label }}
</span>
