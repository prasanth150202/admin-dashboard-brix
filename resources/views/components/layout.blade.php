<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ isset($title) ? $title.' · ' : '' }}Brix Partner Dashboard</title>
    <link rel="icon" href="{{ asset('images/brix-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
@php
    $nav = [
        ['overview', 'Overview', 'overview'],
        ['apps.index', 'Apps', 'apps.*'],
        ['stores.index', 'Stores', 'stores.*'],
        ['payouts.index', 'Payouts', 'payouts.*'],
        ['settings', 'Settings', 'settings*'],
    ];
@endphp
<div class="lg:flex">
    <aside class="lg:w-56 lg:shrink-0 lg:min-h-screen border-b lg:border-b-0 lg:border-r border-line bg-surface">
        <div class="flex items-center justify-between gap-3 px-4 py-4 lg:px-5">
            <a href="{{ route('overview') }}" class="flex items-center gap-2.5 font-semibold">
                <img src="{{ asset('images/brix-icon.png') }}" alt="" class="h-7 w-7 rounded-md">
                <span>Partner Dashboard</span>
            </a>
            <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                @csrf
                <button class="text-sm text-ink-2 hover:text-ink">Log out</button>
            </form>
        </div>
        <nav class="flex gap-1 overflow-x-auto px-3 pb-3 lg:flex-col lg:px-3">
            @foreach ($nav as [$route, $label, $pattern])
                <a href="{{ route($route) }}"
                   @class([
                       'whitespace-nowrap rounded-lg px-3 py-2 text-sm',
                       'bg-surface-2 font-medium text-ink' => request()->routeIs($pattern),
                       'text-ink-2 hover:bg-surface-2 hover:text-ink' => ! request()->routeIs($pattern),
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="hidden lg:block px-5 pt-6 text-xs text-muted">
            <div class="truncate">{{ auth()->user()->email }}</div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button class="hover:text-ink">Log out</button>
            </form>
        </div>
    </aside>

    <main class="flex-1 min-w-0 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
        @if (session('status'))
            <div class="card mb-5 flex items-start gap-2 px-4 py-3 text-sm" role="status">
                <span aria-hidden="true" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-good"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if (session('error'))
            <div class="card mb-5 flex items-start gap-2 px-4 py-3 text-sm" role="alert">
                <span aria-hidden="true" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-critical"></span>
                <span class="whitespace-pre-line">{{ session('error') }}</span>
            </div>
        @endif

        {{ $slot }}

        <p class="mt-10 text-xs text-muted">
            Data from the Shopify Partner API. Shopify notes transaction data is for analytics, not accounting — reconcile payouts against the Partner Dashboard.
        </p>
    </main>
</div>
</body>
</html>
