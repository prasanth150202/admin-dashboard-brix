{{-- One row per setup or feature request, with an inline form to hand it to someone. --}}
<div class="overflow-x-auto">
    <table class="data-table">
        <thead>
        <tr><th>Work</th><th>Status</th><th>Due</th><th>Assign to · due date</th></tr>
        </thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td class="min-w-64">
                    <span class="mr-1 rounded px-1.5 py-0.5 text-xs" style="background: var(--surface-2)">{{ $item->type === 'setup' ? 'Setup' : 'Feature' }}</span>
                    <a href="{{ $item->url }}" class="font-medium hover:underline">{{ $item->title }}</a>
                    @if ($item->type === 'setup' && $item->model->tasks_count)
                        <div class="mt-1"><x-progress :done="$item->model->done_count" :total="$item->model->tasks_count" /></div>
                    @endif
                </td>
                <td><div class="flex flex-col gap-1">
                    @if ($item->type === 'setup')<x-setup-status :setup="$item->model" />@else<x-feature-status :feature="$item->model" />@endif
                </div></td>
                <td class="whitespace-nowrap">{{ short_date($item->due_on) }}</td>
                <td>
                    @if ($item->type === 'setup' || auth()->user()->can('assign-features'))
                        <form method="POST" action="{{ route('plan.assign') }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="hidden" name="type" value="{{ $item->type }}">
                            <input type="hidden" name="id" value="{{ $item->id }}">
                            <x-person-select :users="$users" :selected="$item->assignee_id" :first="$item->type === 'setup' ? 'setup' : 'development'" aria-label="Assign {{ $item->title }}" />
                            <input type="date" name="due_on" value="{{ $item->due_on?->toDateString() }}" class="field" aria-label="Due date for {{ $item->title }}">
                            <button class="btn">Save</button>
                        </form>
                    @else
                        <span class="text-xs text-muted">Only requesters and developers assign features.</span>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
