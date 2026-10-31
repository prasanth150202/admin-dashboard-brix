<x-layout title="New setup">
    <a href="{{ route('setups.index') }}" class="text-sm text-ink-2 hover:text-ink">← Setups</a>
    <h1 class="mt-2 text-xl font-semibold">New setup</h1>
    <p class="mt-1 text-sm text-ink-2">Starts a setup with the app's checklist. Works for stores that haven't installed yet, too — it links up once they do.</p>

    <form method="POST" action="{{ route('setups.store') }}" class="card mt-6 grid max-w-3xl gap-4 p-4 sm:grid-cols-2">
        @csrf
        <div>
            <label for="shop_domain" class="block text-sm font-medium">Store</label>
            <input id="shop_domain" name="shop_domain" list="store-domains" required
                   value="{{ old('shop_domain', $shop?->myshopify_domain) }}" placeholder="cool-shop.myshopify.com" autocomplete="off" class="field mt-1 w-full">
            <datalist id="store-domains">
                @foreach ($domains as $domain)<option value="{{ $domain }}">@endforeach
            </datalist>
        </div>
        <div>
            <label for="partner_app_id" class="block text-sm font-medium">App</label>
            <select id="partner_app_id" name="partner_app_id" required class="field mt-1 w-full">
                <option value="">Choose an app</option>
                @foreach ($apps as $app)
                    <option value="{{ $app->id }}" @selected(old('partner_app_id', request('app')) == $app->id)>
                        {{ $app->name }} · {{ $app->setup_steps_count ? $app->setup_steps_count.' '.Str::plural('step', $app->setup_steps_count) : 'no checklist yet' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="assignee_id" class="block text-sm font-medium">Assign to</label>
            <select id="assignee_id" name="assignee_id" class="field mt-1 w-full">
                <option value="">Nobody yet</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('assignee_id') == $user->id)>{{ $user->name }} · {{ \App\Models\User::ROLES[$user->role] ?? $user->role }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="due_on" class="block text-sm font-medium">Due</label>
            <input id="due_on" name="due_on" type="date" value="{{ old('due_on') }}" class="field mt-1 w-full">
        </div>
        <div class="sm:col-span-2">
            <label for="notes" class="block text-sm font-medium">Notes</label>
            <textarea id="notes" name="notes" rows="4" placeholder="What the merchant bought, contact person, anything the team should know" class="field mt-1 w-full">{{ old('notes') }}</textarea>
        </div>
        @if ($errors->any())
            <div class="text-sm sm:col-span-2" style="color: var(--critical)">{{ $errors->first() }}</div>
        @endif
        <div class="sm:col-span-2">
            <button class="btn btn-primary">Create setup</button>
        </div>
    </form>
</x-layout>
