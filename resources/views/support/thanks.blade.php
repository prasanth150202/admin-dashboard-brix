<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Request received · Brix</title>
    <link rel="icon" href="{{ asset('images/brix-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased flex items-center justify-center px-4">
<div class="card w-full max-w-sm p-6 text-center">
    <img src="{{ asset('images/brix-icon.png') }}" alt="" class="mx-auto h-10 w-10 rounded-md">
    <h1 class="mt-4 text-lg font-semibold">Thanks — we've got it</h1>
    <p class="mt-2 text-sm text-ink-2">
        @if (session('ticket'))Your request is ticket <b>{{ session('ticket') }}</b>. @endif
        We'll reply by email.
    </p>
</div>
</body>
</html>
