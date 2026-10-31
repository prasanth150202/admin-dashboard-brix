@props(['setup'])
@php
    $color = ['todo' => 'var(--muted)', 'in_progress' => 'var(--series-1)', 'waiting' => 'var(--warning)', 'done' => 'var(--good)'][$setup->status] ?? 'var(--muted)';
@endphp
<x-dot :label="\App\Models\Setup::STATUSES[$setup->status] ?? $setup->status" :color="$color" />
@if ($setup->isOverdue())
    <x-dot label="Overdue" color="var(--critical)" />
@endif
