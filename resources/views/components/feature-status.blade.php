@props(['feature'])
@php
    $color = [
        'requested' => 'var(--muted)', 'planned' => 'var(--series-2)', 'in_progress' => 'var(--series-1)',
        'testing' => 'var(--warning)', 'done' => 'var(--good)', 'declined' => 'var(--axis)',
    ][$feature->status] ?? 'var(--muted)';
@endphp
<x-dot :label="\App\Models\FeatureRequest::STATUSES[$feature->status] ?? $feature->status" :color="$color" />
@if (in_array($feature->priority, ['high', 'urgent'], true))
    <x-dot :label="\App\Models\FeatureRequest::PRIORITIES[$feature->priority]" :color="$feature->priority === 'urgent' ? 'var(--critical)' : 'var(--series-2)'" />
@endif
@if ($feature->isOverdue())
    <x-dot label="Overdue" color="var(--critical)" />
@endif
