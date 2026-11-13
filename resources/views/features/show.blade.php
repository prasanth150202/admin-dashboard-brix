<x-layout :title="$feature->number().' '.$feature->title">
    <a href="{{ route('features.index') }}" class="text-sm text-ink-2 hover:text-ink">← Feature requests</a>

    <div class="mt-2">
        <h1 class="text-xl font-semibold"><span class="text-muted">{{ $feature->number() }}</span> {{ $feature->title }}</h1>
        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-ink-2">
            <x-feature-status :feature="$feature" />
            <span>requested by {{ $feature->requester?->name ?? 'someone' }} · {{ $feature->created_at->format('M j, Y') }}</span>
            @if ($feature->app)<span>{{ $feature->app->name }}</span>@endif
            @if ($feature->shop)<a href="{{ route('stores.show', $feature->shop) }}" class="hover:underline">{{ $feature->shop->displayName() }}</a>@endif
        </div>
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="space-y-3 lg:col-span-2">
            <article class="card p-4">
                <h2 class="font-semibold">Details</h2>
                <div class="mt-2 whitespace-pre-line break-words text-sm">{{ $feature->description ?: 'No details given.' }}</div>
            </article>

            <h2 class="pt-2 font-semibold">History</h2>
            @forelse ($feature->updates as $update)
                <article class="card p-4">
                    <header class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                        <span class="font-medium">
                            {{ $update->user?->name ?? 'Someone' }}
                            @if ($update->to_status)
                                <span class="font-normal text-ink-2">moved it from {{ \App\Models\FeatureRequest::STATUSES[$update->from_status] ?? $update->from_status }} to <b class="text-ink">{{ \App\Models\FeatureRequest::STATUSES[$update->to_status] ?? $update->to_status }}</b></span>
                            @endif
                        </span>
                        <time class="text-xs text-muted" datetime="{{ $update->created_at->toIso8601String() }}">{{ $update->created_at->format('M j, Y H:i') }}</time>
                    </header>
                    @if ($update->body)<div class="mt-2 whitespace-pre-line break-words text-sm">{{ $update->body }}</div>@endif
                </article>
            @empty
                <p class="text-sm text-ink-2">No updates yet.</p>
            @endforelse

            <form method="POST" action="{{ route('features.comment', $feature) }}" class="card p-4">
                @csrf
                <label for="body" class="block text-sm font-medium">Add a comment</label>
                <textarea id="body" name="body" rows="3" required placeholder="A question, more context, a test result" class="field mt-1 w-full">{{ old('body') }}</textarea>
                @error('body')<p class="mt-1 text-sm" style="color: var(--critical)">{{ $message }}</p>@enderror
                <button class="btn mt-3">Comment</button>
            </form>
        </div>

        <aside class="space-y-4">
            @can('develop')
                <form method="POST" action="{{ route('features.update', $feature) }}" class="card space-y-3 p-4">
                    @csrf @method('PATCH')
                    <h2 class="font-semibold">Update status</h2>
                    <div>
                        <label for="status" class="block text-sm text-ink-2">Status</label>
                        <select id="status" name="status" class="field mt-1 w-full">
                            @foreach (\App\Models\FeatureRequest::STATUSES as $value => $label)
                                <option value="{{ $value }}" @selected($feature->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="note" class="block text-sm text-ink-2">Note for the requester <span class="text-muted">(optional)</span></label>
                        <textarea id="note" name="note" rows="3" placeholder="What changed, what's next" class="field mt-1 w-full"></textarea>
                    </div>
                    <div>
                        <label for="assignee_id" class="block text-sm text-ink-2">Developer</label>
                        <x-person-select id="assignee_id" :users="$users" :selected="$feature->assignee_id" first="development" class="mt-1 w-full" />
                    </div>
                    <div>
                        <label for="priority" class="block text-sm text-ink-2">Priority</label>
                        <select id="priority" name="priority" class="field mt-1 w-full">
                            @foreach (\App\Models\FeatureRequest::PRIORITIES as $value => $label)
                                <option value="{{ $value }}" @selected($feature->priority === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="due_on" class="block text-sm text-ink-2">Target date</label>
                        <input id="due_on" name="due_on" type="date" value="{{ $feature->due_on?->toDateString() }}" class="field mt-1 w-full">
                    </div>
                    @if ($errors->hasAny(['status', 'priority', 'assignee_id', 'due_on', 'note']))
                        <p class="text-sm" style="color: var(--critical)">{{ $errors->first() }}</p>
                    @endif
                    <button class="btn btn-primary">Save</button>
                </form>
            @else
                <section class="card space-y-2 p-4 text-sm">
                    <h2 class="font-semibold">Status</h2>
                    <div class="flex flex-col gap-1"><x-feature-status :feature="$feature" /></div>
                    <dl class="grid grid-cols-2 gap-y-1.5 pt-2">
                        <dt class="text-ink-2">Developer</dt><dd>{{ $feature->assignee?->name ?? '—' }}</dd>
                        <dt class="text-ink-2">Priority</dt><dd>{{ \App\Models\FeatureRequest::PRIORITIES[$feature->priority] ?? $feature->priority }}</dd>
                        <dt class="text-ink-2">Target</dt><dd>{{ $feature->due_on?->format('M j, Y') ?? '—' }}</dd>
                        @if ($feature->completed_at)<dt class="text-ink-2">Closed</dt><dd>{{ $feature->completed_at->format('M j, Y') }}</dd>@endif
                    </dl>
                    <p class="pt-2 text-xs text-muted">Only the development team changes the status.</p>
                </section>
                @can('assign-features')
                    <form method="POST" action="{{ route('plan.assign') }}" class="card space-y-3 p-4">
                        @csrf
                        <input type="hidden" name="type" value="feature">
                        <input type="hidden" name="id" value="{{ $feature->id }}">
                        <h2 class="font-semibold">Assign</h2>
                        <div>
                            <label for="plan_assignee" class="block text-sm text-ink-2">Developer</label>
                            <x-person-select id="plan_assignee" :users="$users" :selected="$feature->assignee_id" first="development" class="mt-1 w-full" />
                        </div>
                        <div>
                            <label for="plan_due" class="block text-sm text-ink-2">Wanted by</label>
                            <input id="plan_due" name="due_on" type="date" value="{{ $feature->due_on?->toDateString() }}" class="field mt-1 w-full">
                        </div>
                        <button class="btn">Save</button>
                    </form>
                @endcan
            @endcan

            @can('admin')
                <form method="POST" action="{{ route('features.destroy', $feature) }}" onsubmit="return confirm('Delete this feature request and its history?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-ink-2 hover:text-ink">Delete request</button>
                </form>
            @endcan
        </aside>
    </div>
</x-layout>
