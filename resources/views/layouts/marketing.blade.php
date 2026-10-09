<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'ZIWAIT: Digital menus, orders and billing for restaurants and hotels' }}</title>
    <meta name="description" content="{{ $description ?? 'ZIWAIT helps restaurants, hotels and hospitality businesses publish digital menus, take orders by QR code and manage every branch from one account.' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
<header class="sticky top-0 z-40 border-b border-ink-100 bg-white/90 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3">
        <a href="{{ route('home') }}" aria-label="ZIWAIT home"><x-logo /></a>
        <nav class="hidden items-center gap-6 text-sm font-medium text-ink-700 lg:flex" aria-label="Main">
            <a class="hover:text-brand-700" href="{{ route('home') }}#top">Home</a>
            <a class="hover:text-brand-700" href="{{ route('home') }}#features">Features</a>
            <a class="hover:text-brand-700" href="{{ route('home') }}#pricing">Pricing</a>
            <a class="hover:text-brand-700" href="{{ route('home') }}#how-it-works">How It Works</a>
            <a class="hover:text-brand-700" href="{{ route('home') }}#about">About</a>
            <a class="hover:text-brand-700" href="{{ route('home') }}#contact">Contact</a>
        </nav>
        <div class="flex items-center gap-2">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="hidden px-3 text-sm font-medium text-ink-700 hover:text-brand-700 sm:inline">Login</a>
                <a href="{{ route('register') }}" class="btn btn-primary">Get Started</a>
            @endauth
            <details class="relative lg:hidden">
                <summary class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-lg border border-ink-100" aria-label="Open menu"><x-icon name="menu" /></summary>
                <div class="absolute right-0 mt-2 w-48 rounded-xl border border-ink-100 bg-white p-2 text-sm shadow-lg">
                    @foreach (['features' => 'Features', 'pricing' => 'Pricing', 'how-it-works' => 'How It Works', 'about' => 'About', 'contact' => 'Contact'] as $id => $label)
                        <a class="block rounded px-3 py-2 hover:bg-ink-50" href="{{ route('home') }}#{{ $id }}">{{ $label }}</a>
                    @endforeach
                    @guest<a class="block rounded px-3 py-2 hover:bg-ink-50" href="{{ route('login') }}">Login</a>@endguest
                </div>
            </details>
        </div>
    </div>
</header>

<main id="main">@yield('content')</main>

<footer class="bg-ink-900 text-ink-300">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 px-4 py-8 sm:flex-row">
        <div><x-logo :dark="true" /><p class="mt-1 text-sm">Smarter Hospitality. Bigger Opportunities.</p></div>
        <nav class="flex flex-wrap justify-center gap-5 text-sm" aria-label="Footer">
            <a class="hover:text-white" href="{{ route('home') }}#top">Home</a>
            <a class="hover:text-white" href="{{ route('home') }}#features">Features</a>
            <a class="hover:text-white" href="{{ route('home') }}#pricing">Pricing</a>
            <a class="hover:text-white" href="{{ route('home') }}#about">About</a>
            <a class="hover:text-white" href="{{ route('home') }}#contact">Contact</a>
        </nav>
        <p class="text-xs">© {{ date('Y') }} ZIWAIT. All rights reserved.</p>
    </div>
</footer>
</body>
</html>
