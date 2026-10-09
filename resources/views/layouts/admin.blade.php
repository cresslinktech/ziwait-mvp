<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Super Admin' }} · ZIWAIT</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $nav = [
        ['Dashboard', 'home', 'admin.index', 'admin.index'],
        ['Businesses', 'business', 'admin.businesses', 'admin.businesses*'],
        ['Branches', 'pin', 'admin.branches', 'admin.branches*'],
        ['Users', 'users', 'admin.users', 'admin.users*'],
        ['Subscriptions', 'layers', 'admin.subscriptions', 'admin.subscriptions*'],
        ['Payments', 'receipt', 'admin.payments', 'admin.payments*'],
        ['Advertisements', 'megaphone', 'admin.advertisements', 'admin.advertisements*'],
        ['Campaigns', 'megaphone', null, null],
        ['Enquiries', 'mail', 'admin.enquiries', 'admin.enquiries*'],
        ['Reports', 'chart', null, null],
        ['Audit Logs', 'list', 'admin.audit', 'admin.audit*'],
        ['Settings', 'cog', null, null],
    ];
    $openReviews = \App\Domain\Billing\Models\ManualPaymentReview::where('status', 'open')->count();
@endphp
<body class="bg-ink-50 text-ink-900">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col overflow-y-auto bg-ink-900 p-4 text-ink-300 lg:flex" aria-label="Admin navigation">
    <a href="{{ route('admin.index') }}" class="mb-6 block px-2 pt-1" aria-label="ZIWAIT admin home"><x-logo :dark="true" /></a>
    <nav class="space-y-1 text-sm font-medium">
        @foreach ($nav as [$label, $icon, $route, $pattern])
            @if ($route)
                <a href="{{ route($route) }}" @class(['flex items-center gap-3 rounded-lg px-3 py-2.5 transition', 'bg-brand-600 text-white' => request()->routeIs($pattern), 'hover:bg-ink-800 hover:text-white' => ! request()->routeIs($pattern)]) @if (request()->routeIs($pattern)) aria-current="page" @endif>
                    <x-icon :name="$icon" /> {{ $label }}
                </a>
            @else
                <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 opacity-50" title="Coming soon"><x-icon :name="$icon" /> {{ $label }} <span class="ml-auto rounded bg-ink-700 px-1.5 py-0.5 text-[10px] uppercase">Soon</span></span>
            @endif
        @endforeach
    </nav>
</aside>

<div class="lg:pl-64">
    <header class="sticky top-0 z-30 flex items-center gap-3 border-b border-ink-100 bg-white px-4 py-3">
        <button type="button" id="sidebar-toggle" class="flex h-10 w-10 items-center justify-center rounded-lg border border-ink-100 lg:hidden" aria-label="Toggle navigation" aria-controls="sidebar"><x-icon name="menu" /></button>
        <form method="GET" action="{{ route('admin.businesses') }}" role="search" class="relative max-w-lg flex-1">
            <label for="admin-search" class="sr-only">Search businesses</label>
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-500" />
            <input id="admin-search" name="q" type="search" placeholder="Search businesses by name or email" class="w-full rounded-lg border border-ink-100 bg-ink-50 py-2 pl-9 pr-3 text-sm">
        </form>
        <div class="ml-auto flex items-center gap-3">
            <a href="{{ route('admin.payments') }}" class="relative flex h-10 w-10 items-center justify-center rounded-lg text-ink-700 hover:bg-ink-50" aria-label="{{ $openReviews }} payment cases need review">
                <x-icon name="bell" />@if ($openReviews)<span class="absolute right-1.5 top-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">{{ $openReviews }}</span>@endif
            </a>
            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <span class="hidden text-left text-sm leading-tight sm:block"><strong class="block">{{ auth()->user()->name }}</strong><span class="text-xs text-ink-500">Administrator</span></span>
                    <x-icon name="chevron" class="h-4 w-4" />
                </summary>
                <div class="absolute right-0 mt-2 w-44 rounded-xl border border-ink-100 bg-white p-2 text-sm shadow-lg">
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded px-3 py-2 text-left hover:bg-ink-50">Log out</button></form>
                </div>
            </details>
        </div>
    </header>

    <main id="main" class="mx-auto max-w-7xl p-4 sm:p-6">
        @if (session('status'))<div role="status" class="mb-4 rounded-lg bg-brand-50 p-3 text-sm text-brand-800">{{ session('status') }}</div>@endif
        @if (session('error'))<div role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-800">{{ session('error') }}</div>@endif
        @yield('content')
    </main>
</div>
<script>
    document.getElementById('sidebar-toggle').addEventListener('click', function () {
        var s = document.getElementById('sidebar');
        s.classList.toggle('hidden'); s.classList.toggle('flex');
    });
</script>
</body>
</html>
