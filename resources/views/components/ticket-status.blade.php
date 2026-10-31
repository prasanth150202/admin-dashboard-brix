@props(['ticket'])
@php
    $color = ['open' => 'var(--series-1)', 'pending' => 'var(--warning)', 'resolved' => 'var(--good)'][$ticket->status] ?? 'var(--muted)';
@endphp
<x-dot :label="\App\Models\Ticket::STATUSES[$ticket->status] ?? $ticket->status" :color="$color" />
@if (in_array($ticket->priority, ['high', 'urgent'], true))
    <x-dot :label="\App\Models\Ticket::PRIORITIES[$ticket->priority]" :color="$ticket->priority === 'urgent' ? 'var(--critical)' : 'var(--series-2)'" />
@endif
