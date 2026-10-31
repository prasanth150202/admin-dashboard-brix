<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Log in · Brix Partner Dashboard</title>
    <link rel="icon" href="{{ asset('images/brix-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased flex items-center justify-center px-4">
<form method="POST" action="{{ url('/login') }}" class="card w-full max-w-sm p-6">
    @csrf
    <div class="flex items-center gap-2.5">
        <img src="{{ asset('images/brix-icon.png') }}" alt="" class="h-8 w-8 rounded-md">
        <h1 class="text-lg font-semibold">Partner Dashboard</h1>
    </div>

    <label class="mt-6 block text-sm font-medium" for="email">Email</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field mt-1 w-full">

    <label class="mt-4 block text-sm font-medium" for="password">Password</label>
    <input id="password" name="password" type="password" required autocomplete="current-password" class="field mt-1 w-full">

    @error('email')<p class="mt-3 text-sm" style="color: var(--critical)">{{ $message }}</p>@enderror

    <label class="mt-4 flex items-center gap-2 text-sm text-ink-2">
        <input type="checkbox" name="remember" value="1"> Keep me signed in
    </label>

    <button class="btn btn-primary mt-5 w-full justify-center">Log in</button>
</form>
</body>
</html>
