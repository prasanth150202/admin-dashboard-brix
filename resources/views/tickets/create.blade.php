<x-layout title="New ticket">
    <a href="{{ route('tickets.index') }}" class="text-sm text-ink-2 hover:text-ink">← Tickets</a>
    <h1 class="mt-2 text-xl font-semibold">New ticket</h1>
    <p class="mt-1 text-sm text-ink-2">Log a request a merchant sent some other way — a call, WhatsApp, a Shopify message.</p>

    <form method="POST" action="{{ route('tickets.store') }}" class="card mt-6 grid max-w-3xl gap-4 p-4 sm:grid-cols-2">
        @csrf
        <div class="sm:col-span-2">
            <label for="subject" class="block text-sm font-medium">Subject</label>
            <input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="250" class="field mt-1 w-full">
        </div>
        <div class="sm:col-span-2">
            <label for="message" class="block text-sm font-medium">What the merchant needs</label>
            <textarea id="message" name="message" rows="6" required class="field mt-1 w-full">{{ old('message') }}</textarea>
        </div>
        <div>
            <label for="name" class="block text-sm font-medium">Merchant name</label>
            <input id="name" name="name" value="{{ old('name') }}" class="field mt-1 w-full">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">Merchant email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" class="field mt-1 w-full">
            <p class="mt-1 text-xs text-muted">Replies are emailed here.</p>
        </div>
        <div>
            <label for="shop_domain" class="block text-sm font-medium">Store</label>
            <input id="shop_domain" name="shop_domain" value="{{ old('shop_domain', $shop?->myshopify_domain) }}" placeholder="store-name.myshopify.com" class="field mt-1 w-full">
        </div>
        <div>
            <label for="app" class="block text-sm font-medium">App</label>
            <select id="app" name="app" class="field mt-1 w-full">
                <option value="">Not app-specific</option>
                @foreach ($apps as $app)
                    <option value="{{ $app->id }}" @selected(old('app') == $app->id)>{{ $app->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="priority" class="block text-sm font-medium">Priority</label>
            <select id="priority" name="priority" class="field mt-1 w-full">
                @foreach (\App\Models\Ticket::PRIORITIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="assignee_id" class="block text-sm font-medium">Assign to</label>
            <select id="assignee_id" name="assignee_id" class="field mt-1 w-full">
                <option value="">Nobody yet</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected(old('assignee_id', auth()->id()) == $user->id)>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($errors->any())
            <div class="text-sm sm:col-span-2" style="color: var(--critical)">{{ $errors->first() }}</div>
        @endif
        <div class="sm:col-span-2">
            <button class="btn btn-primary">Create ticket</button>
        </div>
    </form>
</x-layout>
