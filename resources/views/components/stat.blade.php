@props(['label', 'value', 'hint' => null, 'href' => null])
<div {{ $attributes->class('card p-4') }}>
    <div class="text-xs font-medium uppercase tracking-wide text-muted">{{ $label }}</div>
    <div class="mt-2 text-2xl font-semibold">
        @if ($href)<a href="{{ $href }}" class="hover:underline">{{ $value }}</a>@else{{ $value }}@endif
    </div>
    @if ($hint)<div class="mt-1 text-xs text-ink-2">{{ $hint }}</div>@endif
</div>
