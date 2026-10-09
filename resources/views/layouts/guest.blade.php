<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ZIWAIT' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-50">
<header class="bg-white shadow-sm"><div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
    <a href="{{ route('home') }}" aria-label="ZIWAIT home"><x-logo /></a>
    <p class="text-sm text-ink-500">@auth <a class="text-brand-700 underline" href="{{ route('dashboard') }}">Dashboard</a> @else
        @if (request()->routeIs('login')) New here? <a class="font-semibold text-brand-700" href="{{ route('register') }}">Create an account</a>
        @else Already have an account? <a class="font-semibold text-brand-700" href="{{ route('login') }}">Login</a> @endif @endauth</p>
</div></header>
<main class="mx-auto max-w-5xl px-4 py-10">@if (session('status'))<div role="status" class="mb-4 rounded-lg bg-brand-50 p-3 text-sm text-brand-800">{{ session('status') }}</div>@endif @yield('content')</main>
</body>
</html>
