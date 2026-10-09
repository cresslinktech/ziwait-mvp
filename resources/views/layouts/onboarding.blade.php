<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>Set up your business · ZIWAIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f6f8] font-sans text-ink-900 antialiased">
    <header class="relative overflow-hidden border-b border-white/10 bg-ink-950 px-4 py-7 text-white sm:px-6 sm:py-9 lg:px-12">
        <div class="absolute inset-0">
            <img src="{{ asset('images/stock/cta-kitchen.jpg') }}" alt="" class="h-full w-full object-cover object-center brightness-[0.45] contrast-125">
            <div class="absolute inset-0 bg-gradient-to-r from-ink-950/90 via-ink-950/75 to-ink-950/60"></div>
        </div>
        <div class="relative z-10 mx-auto flex max-w-7xl flex-col justify-between gap-6 md:flex-row md:items-center">
            <div>
                <a href="{{ route('home') }}" class="mb-4 inline-flex items-center gap-3" aria-label="ZIWAIT home">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-500 text-2xl font-black shadow-lg shadow-brand-500/40">Z</span>
                    <span class="text-2xl font-black">ZIWAIT</span>
                </a>
                <h1 class="text-3xl font-black leading-tight sm:text-4xl">Welcome to ZIWAIT</h1>
                <p class="mt-1 text-base font-medium text-white/90 sm:text-lg">Let's get your restaurant online in a few simple steps.</p>
                <p class="mt-1.5 max-w-2xl text-sm leading-relaxed text-white/70">Create your business account, add your branches, set up your menu, and start receiving orders. It only takes a few minutes.</p>
            </div>
            <div class="flex items-center gap-3 self-start rounded-xl border border-white/10 bg-black/40 px-4 py-2 backdrop-blur-md md:self-center">
                <span class="text-sm text-white/70">Your account</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-sm font-semibold text-white underline decoration-brand-400 underline-offset-4 transition hover:text-brand-200">Log out</button></form>
            </div>
        </div>
    </header>

    <div class="border-b border-ink-100 bg-white">
        <nav class="mx-auto max-w-7xl overflow-x-auto px-4 py-3 sm:px-6 lg:px-12" aria-label="Setup progress">
            <ol class="flex min-w-max items-center space-x-2 text-sm font-medium sm:space-x-4">
                @foreach ($steps as $n => $label)
                    @php $done = $n < $step; $current = $n === $step; @endphp
                    <li class="flex items-center" @if($current) aria-current="step" @endif>
                        <span @class([
                            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold transition-colors',
                            'bg-brand-500 text-white shadow-md' => $current,
                            'bg-brand-600 text-white' => $done,
                            'bg-ink-100 text-ink-600' => !$done && !$current,
                        ])>@if ($done)<x-icon name="check" class="h-4 w-4" />@else{{ $n }}@endif</span>
                        <span @class([
                            'ml-2.5 whitespace-nowrap transition-colors',
                            'font-semibold text-ink-900' => $current || $done,
                            'text-ink-500' => !$done && !$current,
                        ])>{{ $label }}</span>
                        @unless ($loop->last)
                            <svg class="ml-3 h-5 w-5 text-ink-300 sm:ml-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>

    <main class="mx-auto max-w-7xl px-4 py-5 sm:px-6 sm:py-7 lg:px-12">

        <section class="overflow-hidden rounded-2xl border border-ink-100 bg-white shadow-sm" aria-labelledby="step-h">
            <header class="border-b border-ink-100 px-5 py-5 sm:px-8">
                <div class="flex items-center gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-500 text-sm font-bold text-white">{{ $step }}</span>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-ink-500">Step {{ $step }} of {{ count($steps) }}</p>
                        <h2 id="step-h" class="text-xl font-bold text-ink-900">{{ $steps[$step] }}</h2>
                    </div>
                </div>
            </header>
            <div class="p-5 sm:p-8">
                @if (session('status'))<div role="status" class="mb-5 rounded-xl border border-brand-200 bg-brand-50 p-3.5 text-sm text-brand-800">{{ session('status') }}</div>@endif
                @yield('content')
            </div>
        </section>
    </main>
</body>
</html>
