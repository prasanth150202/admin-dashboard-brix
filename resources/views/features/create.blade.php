<x-layout title="Request a feature">
    <a href="{{ route('features.index') }}" class="text-sm text-ink-2 hover:text-ink">← Feature requests</a>
    <h1 class="mt-2 text-xl font-semibold">Request a feature</h1>
    <p class="mt-1 text-sm text-ink-2">The development team sees it right away and updates its status as they go.</p>

    <form method="POST" action="{{ route('features.store') }}" class="card mt-6 grid max-w-3xl gap-4 p-4 sm:grid-cols-2">
        @csrf
        <div class="sm:col-span-2">
            <label for="title" class="block text-sm font-medium">What's needed</label>
            <input id="title" name="title" required maxlength="250" value="{{ old('title') }}" placeholder="Show the upsell widget on the cart drawer" class="field mt-1 w-full">
        </div>
        <div class="sm:col-span-2">
            <label for="description" class="block text-sm font-medium">Details</label>
            <textarea id="description" name="description" rows="6" placeholder="Why it's needed, how it should work, links or screenshots" class="field mt-1 w-full">{{ old('description') }}</textarea>
        </div>
        <div>
            <label for="partner_app_id" class="block text-sm font-medium">App</label>
            <select id="partner_app_id" name="partner_app_id" class="field mt-1 w-full">
                <option value="">Not app-specific</option>
                @foreach ($apps as $app)
                    <option value="{{ $app->id }}" @selected(old('partner_app_id', request('app')) == $app->id)>{{ $app->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="shop_domain" class="block text-sm font-medium">For a brand <span class="font-normal text-muted">(optional)</span></label>
            <input id="shop_domain" name="shop_domain" list="store-domains" value="{{ old('shop_domain', request('shop')) }}" placeholder="cool-shop.myshopify.com" autocomplete="off" class="field mt-1 w-full">
            <datalist id="store-domains">
                @foreach ($domains as $domain)<option value="{{ $domain }}">@endforeach
            </datalist>
        </div>
        <div>
            <label for="priority" class="block text-sm font-medium">Priority</label>
            <select id="priority" name="priority" class="field mt-1 w-full">
                @foreach (\App\Models\FeatureRequest::PRIORITIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="assignee_id" class="block text-sm font-medium">Assign to <span class="font-normal text-muted">(optional)</span></label>
            <x-person-select id="assignee_id" :users="$users" :selected="old('assignee_id')" first="development" empty="Let development pick" class="mt-1 w-full" />
        </div>
        <div>
            <label for="due_on" class="block text-sm font-medium">Wanted by <span class="font-normal text-muted">(optional)</span></label>
            <input id="due_on" name="due_on" type="date" value="{{ old('due_on') }}" class="field mt-1 w-full">
        </div>
        @if ($errors->any())
            <div class="text-sm sm:col-span-2" style="color: var(--critical)">{{ $errors->first() }}</div>
        @endif
        <div class="sm:col-span-2">
            <button class="btn btn-primary">Send request</button>
        </div>
    </form>
</x-layout>
