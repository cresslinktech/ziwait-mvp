<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Create your ZIWAIT account.">
    <title>Create your account · ZIWAIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 font-sans text-ink-900 antialiased">
    <main class="mx-auto grid min-h-screen max-w-7xl items-stretch lg:grid-cols-2">
        <section class="relative hidden min-h-[720px] overflow-hidden lg:block" aria-label="ZIWAIT for hospitality">
            <img src="{{ asset('images/stock/cta-kitchen.jpg') }}" alt="Chef preparing a meal in a restaurant kitchen" class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0 bg-gradient-to-t from-ink-950/95 via-ink-950/35 to-ink-950/25"></div>
            <div class="relative flex h-full flex-col justify-between p-12 text-white xl:p-16">
                <a href="{{ route('home') }}" class="inline-flex w-fit items-center gap-3" aria-label="ZIWAIT home">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-500 text-2xl font-black">Z</span>
                    <span class="text-2xl font-black">ZIWAIT</span>
                </a>
                <div class="max-w-lg pb-8">
                    <p class="mb-4 text-xs font-bold uppercase tracking-[0.2em] text-brand-300">Built for hospitality</p>
                    <h1 class="text-4xl font-extrabold leading-tight xl:text-5xl">Make every service run smoother.</h1>
                    <p class="mt-5 max-w-md text-base leading-relaxed text-white/75">Create your owner account first. Then set up your business, branches, menu and plan.</p>
                </div>
            </div>
        </section>

        <section class="flex min-h-screen items-center justify-center bg-white px-5 py-10 sm:px-10 lg:min-h-0 lg:px-14 xl:px-20">
            <div class="w-full max-w-md">
                <a href="{{ route('home') }}" class="mb-10 inline-flex items-center gap-2 text-xl font-black text-ink-900 lg:hidden">
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-lg text-white">Z</span>
                    ZIWAIT
                </a>

                <p class="text-sm font-semibold text-brand-700">Owner account</p>
                <h2 class="mt-2 text-3xl font-extrabold tracking-tight text-ink-950">Create your account</h2>
                <p class="mt-2 text-sm text-ink-500">Your business setup starts right after registration.</p>

                <form method="POST" action="{{ url('/register') }}" class="mt-8 space-y-5">
                    @csrf
                    <div>
                        <label for="name" class="block text-sm font-semibold text-ink-800">Your name</label>
                        <input id="name" name="name" type="text" required maxlength="120" autocomplete="name" value="{{ old('name') }}" placeholder="Ada Okafor" class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        @error('name')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-semibold text-ink-800">Email address</label>
                        <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}" placeholder="you@example.com" class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        @error('email')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-semibold text-ink-800">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                        @error('password')<p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>@enderror
                        <p class="mt-1.5 text-xs text-ink-500">Use at least 10 characters, including letters and numbers.</p>
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-ink-800">Confirm password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="mt-1.5 w-full rounded-xl border border-ink-200 bg-white px-4 py-3 text-sm text-ink-900 outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-100">
                    </div>
                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 px-5 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                        Create account
                        <x-icon name="arrow" class="h-4 w-4" />
                    </button>
                </form>

                <p class="mt-7 text-center text-sm text-ink-500">Already have an account? <a href="{{ route('login') }}" class="font-semibold text-brand-700 underline decoration-brand-300 underline-offset-4 hover:text-brand-800">Log in</a></p>
            </div>
        </section>
    </main>
</body>
</html>