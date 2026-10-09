<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Dashboard' }} · ZIWAIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
@php
    $s = $shell ?? null;
    $nav = $s ? array_values(array_filter([
        ['Dashboard', 'home', route('dashboard'), 'dashboard', null],
        ['Branches', 'pin', route('branches.index'), 'branches.*', null],
        ['Menu', 'menu', route('menu.index'), 'menu.*', null],
        $s['canOrders'] ? ['Orders', 'receipt', route('orders.index'), 'orders.*', $s['newOrders']] : null,
        $s['canCustomers'] ? ['Customers', 'users', route('customers.index'), 'customers.*', null] : null,
        $s['isOwner'] ? ['Billing', 'card', route('billing.index'), 'billing.*', null] : null,
        ['Notifications', 'bell', route('notifications.index'), 'notifications.*', null],
        $s['isOwner'] ? ['Settings', 'cog', route('settings.edit'), 'settings.*', null] : null,
    ])) : [];
    $roleLabel = ['owner' => 'Business Owner', 'manager' => 'Branch Manager', 'staff' => 'Staff'][$s['role'] ?? ''] ?? '';
@endphp
<body class="bg-ink-50 text-ink-900">
<a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>

@if ($s)
{{-- Sidebar --}}
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 hidden w-[260px] flex-col border-r border-ink-800/50 bg-ink-900 lg:flex" aria-label="Main navigation">
    {{-- Logo --}}
    <div class="flex items-center gap-2.5 border-b border-ink-800/50 px-5 py-4">
        <a href="{{ route('dashboard') }}" class="block" aria-label="ZIWAIT dashboard"><x-logo :dark="true" /></a>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 space-y-0.5 overflow-y-auto px-3 py-4 text-sm font-medium">
        @foreach ($nav as [$label, $icon, $href, $pattern, $badge])
            <a href="{{ $href }}" @class([
                'flex items-center gap-3 rounded-xl px-3.5 py-2.5 transition-all',
                'bg-brand-600 text-white shadow-[0_2px_8px_rgba(14,145,99,0.3)]' => $pattern && request()->routeIs($pattern),
                'text-ink-300 hover:bg-ink-800/80 hover:text-white' => !$pattern || !request()->routeIs($pattern),
            ]) @if ($pattern && request()->routeIs($pattern)) aria-current="page" @endif>
                <x-icon :name="$icon" class="h-[18px] w-[18px]" />
                {{ $label }}
                @if ($badge)
                    <span class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold text-white" aria-label="{{ $badge }} new orders">{{ $badge }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    {{-- Subscription card --}}
    @if ($s['isOwner'] && ($currentSubscription ?? null))
        <div class="mx-3 mb-4 rounded-2xl border border-ink-700/50 bg-gradient-to-br from-ink-800 to-ink-800/80 p-4">
            <div class="flex items-center gap-2 text-xs text-ink-400">
                <x-icon name="layers" class="h-4 w-4" /> Your Plan
            </div>
            <p class="mt-1.5 text-lg font-bold text-white">{{ $currentSubscription->plan->name }}</p>
            <p class="mt-0.5 text-xs text-brand-300">{{ ucfirst(str_replace('_', ' ', $currentSubscription->status->value)) }}{{ $currentSubscription->current_period_end ? ' until '.$currentSubscription->current_period_end->format('M j, Y') : '' }}</p>
            <a href="{{ route('billing.index') }}" class="btn btn-primary mt-3 w-full !py-2 text-xs">
                Manage Plan →
            </a>
        </div>
    @endif
</aside>
<button type="button" id="sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-ink-950/50 lg:hidden" aria-label="Close navigation"></button>
@endif

<div @class(['min-h-screen', 'lg:pl-[260px]' => $s])>
    {{-- Top bar --}}
    <header class="sticky top-0 z-30 border-b border-ink-100 bg-white/95 backdrop-blur-md">
        <div class="flex items-center gap-3 px-4 py-3 sm:px-6">
            @if ($s)
                <button type="button" id="sidebar-toggle" aria-expanded="false" class="flex h-10 w-10 items-center justify-center rounded-xl border border-ink-100 transition hover:bg-ink-50 lg:hidden" aria-label="Toggle navigation" aria-controls="sidebar">
                    <x-icon name="menu" />
                </button>
                <form method="POST" action="{{ route('branch.switch') }}" class="relative">@csrf
                    <label for="branch-switch" class="sr-only">Branch</label>
                    <x-icon name="pin" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-600" />
                    <select id="branch-switch" name="branch_id" onchange="this.form.submit()" class="max-w-[14rem] appearance-none rounded-xl border border-ink-100 bg-white py-2.5 pl-10 pr-8 text-sm font-medium transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        <option value="">All branches</option>
                        @foreach ($s['branches'] as $b)<option value="{{ $b->id }}" @selected($s['currentBranch']?->id === $b->id)>{{ $b->name }}</option>@endforeach
                    </select>
                    <noscript><button class="btn btn-outline">Switch</button></noscript>
                </form>
            @else
                <a href="{{ route('home') }}"><x-logo /></a>
            @endif

            <div class="ml-auto flex items-center gap-2">
                @auth
                    {{-- Notification bell --}}
                    <a href="{{ route('notifications.index') }}" class="relative flex h-10 w-10 items-center justify-center rounded-xl text-ink-600 transition hover:bg-ink-50" aria-label="Notifications">
                        <x-icon name="bell" />
                        @if (($s['newOrders'] ?? 0) > 0)
                            <span class="absolute right-2 top-2 h-2.5 w-2.5 rounded-full border-2 border-white bg-red-500"></span>
                        @endif
                    </a>

                    {{-- User avatar --}}
                    <details class="relative">
                        <summary class="flex cursor-pointer list-none items-center gap-2.5 rounded-xl px-2 py-1.5 transition hover:bg-ink-50">
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-800">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                            <span class="hidden text-left text-sm leading-tight sm:block">
                                <strong class="block">{{ auth()->user()->name }}</strong>
                                <span class="text-xs text-ink-500">{{ $roleLabel }}</span>
                            </span>
                            <x-icon name="chevron" class="hidden h-4 w-4 text-ink-400 sm:block" />
                        </summary>
                        <div class="absolute right-0 mt-2 w-52 rounded-2xl border border-ink-100 bg-white p-2 text-sm shadow-xl">
                            @if (auth()->user()->businesses()->count() > 1)
                                <p class="px-3 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wide text-ink-500">Switch business</p>
                                @foreach (auth()->user()->businesses as $biz)
                                    <form method="POST" action="{{ route('business.switch', $biz) }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left transition hover:bg-ink-50">{{ $biz->name }}</button></form>
                                @endforeach
                                <hr class="my-1 border-ink-100">
                            @endif
                            <form method="POST" action="{{ route('logout') }}">@csrf<button class="w-full rounded-lg px-3 py-2 text-left transition hover:bg-ink-50">Log out</button></form>
                        </div>
                    </details>
                @endauth
            </div>
        </div>
    </header>

    {{-- Main content --}}
    <main id="main" class="mx-auto max-w-7xl p-4 sm:p-6">
        @isset($currentSubscription)
            @if ($currentSubscription->status !== App\Domain\Billing\SubscriptionStatus::Active)
                <div role="alert" class="mb-5 flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <x-icon name="bell" class="h-5 w-5 shrink-0 text-amber-600" />
                    <div>
                        @switch($currentSubscription->status->value)
                            @case('trialing') Free trial ends {{ $currentSubscription->current_period_end->diffForHumans() }}. @break
                            @case('past_due') Your plan has ended. Renew by {{ $currentSubscription->grace_ends_at->format('j M') }} to keep taking orders. @break
                            @default Your subscription is not active, so customers cannot place orders.
                        @endswitch
                        @if ($s['isOwner'] ?? false)<a class="ml-1 font-semibold underline" href="{{ route('billing.index') }}">Manage billing</a>@endif
                    </div>
                </div>
            @endif
        @endisset
        @if (session('error'))<div role="alert" class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div>@endif
        @if (session('status'))<div role="status" class="mb-5 rounded-2xl border border-brand-200 bg-brand-50 p-4 text-sm text-brand-800">{{ session('status') }}</div>@endif
        @yield('content')
    </main>
</div>

@if ($s)
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarBackdrop = document.getElementById('sidebar-backdrop');
    const setSidebarOpen = (open) => {
        sidebar.classList.toggle('hidden', !open);
        sidebar.classList.toggle('flex', open);
        sidebarBackdrop.classList.toggle('hidden', !open);
        sidebarToggle.setAttribute('aria-expanded', String(open));
        document.body.classList.toggle('overflow-hidden', open);
    };

    sidebarToggle.addEventListener('click', () => setSidebarOpen(sidebar.classList.contains('hidden')));
    sidebarBackdrop.addEventListener('click', () => setSidebarOpen(false));
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setSidebarOpen(false)));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') setSidebarOpen(false);
    });
</script>
@endif
</body>
</html>
