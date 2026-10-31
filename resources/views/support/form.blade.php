<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Contact support · Brix</title>
    <link rel="icon" href="{{ asset('images/brix-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased px-4 py-10">
<form method="POST" action="{{ route('support.submit') }}" class="card mx-auto w-full max-w-lg p-6">
    @csrf
    <div class="flex items-center gap-2.5">
        <img src="{{ asset('images/brix-icon.png') }}" alt="" class="h-8 w-8 rounded-md">
        <h1 class="text-lg font-semibold">Contact Brix support</h1>
    </div>
    <p class="mt-2 text-sm text-ink-2">Tell us what's going on and we'll get back to you by email.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="name" class="block text-sm font-medium">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name" class="field mt-1 w-full">
        </div>
        <div>
            <label for="email" class="block text-sm font-medium">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="field mt-1 w-full">
        </div>
        <div class="sm:col-span-2">
            <label for="shop_domain" class="block text-sm font-medium">Store URL</label>
            <input id="shop_domain" name="shop_domain" value="{{ old('shop_domain', $shop) }}" required placeholder="your-store.myshopify.com" class="field mt-1 w-full">
        </div>
        @if ($apps->isNotEmpty())
            <div class="sm:col-span-2">
                <label for="app" class="block text-sm font-medium">Which app?</label>
                <select id="app" name="app" class="field mt-1 w-full">
                    <option value="">Not sure / general</option>
                    @foreach ($apps as $app)
                        <option value="{{ $app->id }}" @selected(old('app', $selectedApp) == $app->id)>{{ $app->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="sm:col-span-2">
            <label for="subject" class="block text-sm font-medium">Subject</label>
            <input id="subject" name="subject" value="{{ old('subject') }}" required maxlength="200" class="field mt-1 w-full">
        </div>
        <div class="sm:col-span-2">
            <label for="message" class="block text-sm font-medium">How can we help?</label>
            <textarea id="message" name="message" rows="6" required maxlength="10000" class="field mt-1 w-full">{{ old('message') }}</textarea>
        </div>
        {{-- Honeypot: hidden from people, filled by bots. --}}
        <div aria-hidden="true" style="position: absolute; left: -10000px;">
            <label for="website">Website</label>
            <input id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
    </div>

    @if ($errors->any())
        <p class="mt-4 text-sm" style="color: var(--critical)">{{ $errors->first() }}</p>
    @endif

    <button class="btn btn-primary mt-5 w-full justify-center">Send</button>
</form>
</body>
</html>
