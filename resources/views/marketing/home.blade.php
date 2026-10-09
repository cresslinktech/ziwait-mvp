@extends('layouts.marketing')
@section('content')

{{-- Hero --}}
<section id="top" class="overflow-hidden bg-gradient-to-b from-brand-50 to-white">
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-14 lg:grid-cols-2 lg:py-20">
        <div>
            <span class="badge bg-brand-100 text-brand-800">The complete hospitality platform</span>
            <h1 class="mt-4 text-4xl font-extrabold leading-tight tracking-tight sm:text-5xl">Digitize Your Restaurant or Hotel. <span class="text-brand-600">Grow Your Business.</span></h1>
            <p class="mt-4 max-w-xl text-lg text-ink-500">ZIWAIT helps restaurants, hotels and hospitality businesses publish digital menus, take orders by QR code, manage every branch and run targeted ads, all from one platform.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="btn btn-primary">Get Started Free</a>
                <a href="#how-it-works" class="btn btn-outline"><x-icon name="arrow" class="h-4 w-4" /> See how it works</a>
            </div>
            @if (config('marketing.social_proof'))
                <dl class="mt-8 flex gap-8">
                    @foreach (config('marketing.stats') as [$value, $label])
                        <div><dt class="sr-only">{{ $label }}</dt><dd class="text-2xl font-extrabold">{{ $value }}</dd><p class="text-sm text-ink-500">{{ $label }}</p></div>
                    @endforeach
                </dl>
            @endif
        </div>

        <div class="relative mx-auto w-full max-w-lg" aria-label="Restaurant dining and ordering overview">
            <div class="absolute -right-6 -top-6 h-52 w-52 rounded-full bg-brand-100 blur-2xl"></div>
            <div class="relative overflow-hidden rounded-[28px] border border-ink-100 bg-white p-3 shadow-2xl">
                <img src="{{ asset('images/stock/hero-waiter.jpg') }}" alt="Hospitality team serving customers" class="h-72 w-full rounded-2xl object-cover sm:h-80">
                <div class="mt-3 grid grid-cols-3 gap-3">
                    <img src="{{ asset('images/stock/food-mains.jpg') }}" alt="Restaurant main course" class="h-24 w-full rounded-xl object-cover">
                    <img src="{{ asset('images/stock/food-drinks.jpg') }}" alt="Drinks menu" class="h-24 w-full rounded-xl object-cover">
                    <img src="{{ asset('images/stock/branch-1.jpg') }}" alt="Branch interior" class="h-24 w-full rounded-xl object-cover">
                </div>
            </div>
            <div class="absolute -bottom-6 -right-2 w-36 rounded-2xl border border-ink-200 bg-white p-3 shadow-xl sm:w-40">
                <div class="flex items-center justify-between text-[10px] font-semibold text-ink-500"><span>Your order</span><span class="rounded-full bg-emerald-100 px-1.5 py-0.5 text-[9px] text-emerald-700">Live</span></div>
                <img src="{{ asset('images/stock/food-desserts.jpg') }}" alt="Dessert order" class="mt-2 h-16 w-full rounded-lg object-cover">
                <p class="mt-2 text-xs font-semibold">Grilled chicken set</p>
                <p class="text-[10px] text-ink-500">2 items · ₦6,000</p>
                <div class="mt-2 rounded-lg bg-brand-600 px-2 py-1 text-center text-[10px] font-semibold text-white">Place order</div>
            </div>
        </div>
    </div>
</section>

{{-- Features --}}
<section id="features" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16">
    <h2 class="text-3xl font-extrabold">Powerful features</h2>
    <p class="mt-1 text-ink-500">Everything you need to run and grow your hospitality business.</p>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['menu', 'Digital menu and ordering', 'Publish a beautiful mobile menu, set prices and availability, and let customers place orders without an app or an account.'],
            ['business', 'Multi-branch management', 'Run many branches from one account, each with its own menu prices, team and orders, kept strictly separate.'],
            ['card', 'Subscription billing', 'Simple monthly or yearly plans with secure hosted checkout. Payments are verified on our servers before anything is activated.'],
            ['megaphone', 'Targeted advertising', 'Promote your business or reach the whole platform with campaigns aimed at specific businesses or branches.'],
            ['bell', 'Real-time order alerts', 'Get a push notification on your phone or computer the moment a customer places an order.'],
            ['shield', 'Powerful admin tools', 'Roles and permissions, audit logs and platform oversight keep every business safe and accountable.'],
        ] as [$icon, $title, $text])
            <div class="card p-5 transition hover:shadow-md">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700"><x-icon :name="$icon" class="h-6 w-6" /></span>
                <h3 class="mt-4 font-bold">{{ $title }}</h3>
                <p class="mt-1 text-sm text-ink-500">{{ $text }}</p>
            </div>
        @endforeach
    </div>
</section>

{{-- How it works --}}
<section id="how-it-works" class="scroll-mt-16 bg-ink-50 py-16">
    <div class="mx-auto max-w-6xl px-4">
        <h2 class="text-3xl font-extrabold">How it works</h2>
        <ol class="mt-8 grid gap-4 md:grid-cols-3">
            @foreach ([
                ['Create your account', 'Register your business and your first branch in a couple of minutes. Start with a free trial.'],
                ['Build your menu', 'Add categories and items once, then set the price and availability for each branch.'],
                ['Share your QR code and take orders', 'Print the QR code for each branch. Customers scan, order, and you are alerted instantly. Payment is settled directly with your customer.'],
            ] as [$title, $text])
                <li class="card overflow-hidden p-0"><img src="{{ asset(['images/stock/branch-2.jpg', 'images/stock/food-appetizers.jpg', 'images/stock/cta-kitchen.jpg'][$loop->index]) }}" alt="" class="h-40 w-full object-cover">
                    <div class="p-5"><span class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 font-bold text-white">{{ $loop->iteration }}</span>
                    <h3 class="mt-3 font-bold">{{ $title }}</h3><p class="mt-1 text-sm text-ink-500">{{ $text }}</p></div></li>
            @endforeach
        </ol>
    </div>
</section>

{{-- Call to action band --}}
<section class="mx-auto max-w-6xl px-4 py-16">
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-brand-900 to-brand-700 p-8 text-white sm:p-12">
        <div class="relative max-w-xl">
            <span class="badge bg-white/15 text-white">Built for hospitality</span>
            <h2 class="mt-3 text-3xl font-extrabold sm:text-4xl">More orders. Better management. Higher profits.</h2>
            <p class="mt-3 text-brand-100">Replace paper menus and missed orders with one simple QR code per branch.</p>
            <a href="{{ route('register') }}" class="btn mt-6 bg-brand-500 text-white hover:bg-brand-600">Start your free trial</a>
        </div>
        <div class="absolute -right-4 bottom-0 hidden w-44 rotate-3 rounded-2xl bg-white p-3 text-center text-ink-900 shadow-2xl md:block" aria-hidden="true">
            <p class="text-xs font-bold">Scan to view our menu</p>
            <x-icon name="qr" class="mx-auto my-2 h-24 w-24 text-ink-900" />
        </div>
    </div>
</section>

{{-- Pricing (prices come from the plans table) --}}
<section id="pricing" class="mx-auto max-w-6xl scroll-mt-16 px-4 pb-16">
    <h2 class="text-3xl font-extrabold">Simple, transparent pricing</h2>
    <p class="mt-1 text-ink-500">Choose the plan that fits your business. Start with a free trial, no card needed.</p>
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($plans as $plan)
            @php $monthly = $plan->prices->firstWhere('interval', 'month'); $popular = $plan->code === config('marketing.popular_plan'); @endphp
            <div class="card relative flex flex-col p-5 {{ $popular ? 'border-brand-500 bg-brand-50/50 ring-2 ring-brand-500' : '' }}">
                @if ($popular)<span class="badge absolute -top-3 right-4 bg-brand-600 text-white">Most popular</span>@endif
                <h3 class="font-bold">{{ $plan->name }}</h3>
                <p class="mt-2"><span class="text-3xl font-extrabold">{{ App\Support\MoneyFormat::display($monthly->amount_minor, $monthly->currency) }}</span><span class="text-sm text-ink-500"> /month</span></p>
                <ul class="mt-4 flex-1 space-y-2 text-sm">
                    @foreach (config("marketing.plan_features.{$plan->code}", []) as $feature)
                        <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" />{{ $feature }}</li>
                    @endforeach
                </ul>
                <a href="{{ route('register') }}" class="btn mt-5 {{ $popular ? 'btn-primary' : 'btn-outline' }}">Get Started</a>
            </div>
        @endforeach
        <div class="card flex flex-col p-5">
            <h3 class="font-bold">Enterprise</h3>
            <p class="mt-2 text-2xl font-extrabold">Custom pricing</p>
            <ul class="mt-4 flex-1 space-y-2 text-sm">
                @foreach (['Unlimited branches', 'Custom onboarding', 'Dedicated support'] as $feature)
                    <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" />{{ $feature }}</li>
                @endforeach
            </ul>
            <a href="#contact" class="btn btn-outline mt-5">Contact Sales</a>
        </div>
    </div>
    @if ($plans->isEmpty())<p class="mt-4 text-sm text-ink-500">Plans are being set up. Please get in touch for pricing.</p>@endif
</section>

{{-- Testimonials: placeholder content, hidden unless explicitly enabled --}}
@if (config('marketing.social_proof'))
<section class="mx-auto max-w-6xl px-4 pb-16" aria-labelledby="loved">
    <h2 id="loved" class="text-2xl font-extrabold">What our customers say</h2>
    <div class="mt-6 grid gap-4 md:grid-cols-3">
        @foreach (config('marketing.testimonials') as $t)
            <figure class="card p-5"><blockquote class="text-sm text-ink-700">“{{ $t['quote'] }}”</blockquote>
                <figcaption class="mt-4 text-sm"><strong>{{ $t['name'] }}</strong><span class="block text-ink-500">{{ $t['role'] }}</span></figcaption></figure>
        @endforeach
    </div>
</section>
@endif

{{-- About --}}
<section id="about" class="scroll-mt-16 bg-ink-50 py-16">
    <div class="mx-auto max-w-3xl px-4 text-center">
        <h2 class="text-3xl font-extrabold">About ZIWAIT</h2>
        <p class="mt-3 text-ink-500">ZIWAIT is built for Nigerian restaurants, hotels and hospitality teams who want to spend less time on paperwork and more time with guests. One account, every branch, and a menu that is always up to date.</p>
    </div>
</section>

{{-- Contact --}}
<section id="contact" class="mx-auto max-w-6xl scroll-mt-16 px-4 py-16">
    <div class="grid gap-8 lg:grid-cols-2">
        <div>
            <h2 class="text-3xl font-extrabold">Talk to us</h2>
            <p class="mt-2 text-ink-500">Questions about plans, multi-branch setups or Enterprise pricing? Send us a message and we will reply by email.</p>
            <p class="mt-4 flex items-center gap-2 text-sm"><x-icon name="mail" class="h-5 w-5 text-brand-600" /> {{ config('marketing.contact_email') }}</p>
        </div>
        <form method="POST" action="{{ route('contact') }}" class="card space-y-4 p-6">
            @csrf
            @if (session('enquiry_sent'))<p role="status" class="rounded-lg bg-brand-50 p-3 text-sm text-brand-800">Thank you. We have received your message and will be in touch.</p>@endif
            <div class="hidden" aria-hidden="true"><label>Leave this empty <input name="website" tabindex="-1" autocomplete="off"></label></div>
            @foreach ([['name', 'Your name', 'text', true], ['email', 'Email', 'email', true], ['phone', 'Phone (optional)', 'tel', false], ['business_name', 'Business name (optional)', 'text', false]] as [$f, $label, $type, $req])
                <div>
                    <label for="c-{{ $f }}" class="block text-sm font-medium">{{ $label }}</label>
                    <input id="c-{{ $f }}" name="{{ $f }}" type="{{ $type }}" value="{{ old($f) }}" @required($req) class="mt-1 w-full rounded-lg border border-ink-100 px-3 py-2">
                    @error($f)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <div>
                <label for="c-message" class="block text-sm font-medium">Message</label>
                <textarea id="c-message" name="message" rows="4" required minlength="10" maxlength="2000" class="mt-1 w-full rounded-lg border border-ink-100 px-3 py-2">{{ old('message') }}</textarea>
                @error('message')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-primary">Send message</button>
        </form>
    </div>
</section>
@endsection
