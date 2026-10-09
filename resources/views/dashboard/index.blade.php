@extends('layouts.app', ['title' => 'Dashboard'])
@section('content')
@php
    $first = explode(' ', auth()->user()->name)[0];
    $trend = fn ($g, $label) => $g === null ? '<span class="text-ink-500">'.$label.'</span>' : '<span class="'.($g >= 0 ? 'text-emerald-600' : 'text-red-600').'">'.($g >= 0 ? '↑' : '↓').' '.abs($g).'%</span> <span class="text-ink-500">'.$label.'</span>';
    $statusTone = ['submitted' => 'bg-sky-50 text-sky-700', 'acknowledged' => 'bg-indigo-50 text-indigo-700', 'preparing' => 'bg-amber-50 text-amber-800', 'ready' => 'bg-emerald-50 text-emerald-700', 'completed' => 'bg-brand-100 text-brand-800', 'rejected' => 'bg-red-50 text-red-700', 'cancelled' => 'bg-ink-100 text-ink-700'];
@endphp

{{-- Greeting header --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-extrabold text-ink-900 sm:text-[1.75rem]">{{ $o->greeting() }}, {{ $first }} 👋</h1>
        <p class="mt-1 text-sm text-ink-500">Here's what's happening with your restaurant today.</p>
    </div>
    <p class="flex items-center gap-2 rounded-xl border border-ink-100 bg-white px-4 py-2.5 text-sm font-medium text-ink-700 shadow-sm">
        <x-icon name="chart" class="h-4 w-4 text-ink-400" />
        {{ now()->format('M j, Y') }}
    </p>
</div>

{{-- KPI cards --}}
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key figures">
    <div class="card overflow-hidden p-5">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><x-icon name="receipt" class="h-6 w-6" /></span>
            <p class="text-sm font-medium text-ink-500">Total Orders</p>
        </div>
        <p class="mt-3 text-3xl font-extrabold text-ink-900">{{ $o->canSeeOrders() ? $kpis['orders_today'] : '-' }}</p>
        <p class="mt-1 text-xs">{!! $o->canSeeOrders() ? $trend($kpis['orders_growth'], 'vs. yesterday') : '<span class="text-ink-500">Orders are not enabled for your role</span>' !!}</p>
    </div>
    <div class="card overflow-hidden p-5">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-600"><x-icon name="pin" class="h-6 w-6" /></span>
            <p class="text-sm font-medium text-ink-500">Active Branches</p>
        </div>
        <p class="mt-3 text-3xl font-extrabold text-ink-900">{{ $kpis['branches_active'] }} / {{ $kpis['branches_total'] }}</p>
        <p class="mt-1 text-xs {{ $kpis['branches_active'] === $kpis['branches_total'] ? 'text-emerald-600' : 'text-amber-700' }}">{{ $kpis['branches_active'] === $kpis['branches_total'] ? 'All branches online' : ($kpis['branches_total'] - $kpis['branches_active']).' inactive' }}</p>
    </div>
    <div class="card overflow-hidden p-5">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600"><x-icon name="menu" class="h-6 w-6" /></span>
            <p class="text-sm font-medium text-ink-500">Total Menu Items</p>
        </div>
        <p class="mt-3 text-3xl font-extrabold text-ink-900">{{ $kpis['items'] }}</p>
        <p class="mt-1 text-xs">{!! $trend($kpis['items_growth'], 'vs. last week') !!}</p>
    </div>
    <div class="card overflow-hidden p-5">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600"><x-icon name="layers" class="h-6 w-6" /></span>
            <p class="text-sm font-medium text-ink-500">Subscription</p>
        </div>
        @if ($subscription)
            <p class="mt-3 text-3xl font-extrabold text-ink-900">{{ $subscription->plan->name }}</p>
            <p class="mt-1 text-xs"><x-status :value="$subscription->status" /></p>
        @else
            <p class="mt-3 text-3xl font-extrabold text-ink-900">{{ $isOwner ? 'None' : '-' }}</p>
            <p class="mt-1 text-xs text-ink-500">{{ $isOwner ? 'Choose a plan to take orders' : 'Managed by the owner' }}</p>
        @endif
    </div>
</section>

{{-- Orders + Notifications --}}
<section class="mt-6 grid gap-4 xl:grid-cols-3">
    {{-- Today's orders --}}
    <div class="card overflow-hidden xl:col-span-2">
        <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
            <h2 class="text-base font-bold text-ink-900">Today's Orders</h2>
            @if($o->canSeeOrders())<a class="text-sm font-medium text-brand-700 transition hover:text-brand-800" href="{{ route('orders.index') }}">View all →</a>@endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm" id="orders-table">
                <thead class="border-b border-ink-100 bg-ink-50/50 text-xs font-semibold uppercase tracking-wide text-ink-500">
                    <tr><th class="px-5 py-3">#</th><th class="px-5 py-3">Customer</th><th class="px-5 py-3">Items</th><th class="px-5 py-3">Time</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Action</th></tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="border-b border-ink-100 transition hover:bg-ink-50/50">
                            <td class="px-5 py-3.5">
                                <span class="font-mono text-xs font-semibold text-ink-900">{{ $order->reference }}</span>
                                @if($order->branch && ! $o->selected)<span class="block font-sans text-[11px] text-ink-500">{{ $order->branch->name }}</span>@endif
                            </td>
                            <td class="px-5 py-3.5 font-medium">{{ $order->customer_name }}</td>
                            <td class="px-5 py-3.5">{{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-ink-600">{{ $order->placed_at->format('h:i A') }}</td>
                            <td class="px-5 py-3.5"><span class="badge {{ $statusTone[$order->status->value] }}">{{ $order->status->label() }}</span></td>
                            <td class="px-5 py-3.5"><a class="btn btn-outline !rounded-lg !px-3.5 !py-1.5 text-xs" href="{{ route('orders.show', $order) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-12 text-center text-ink-500">{{ $o->canSeeOrders() ? 'No orders yet today. Share your branch QR code to start receiving them.' : 'You do not have access to orders.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Recent notifications --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
            <h2 class="text-base font-bold text-ink-900">Recent Notifications</h2>
            @if($o->canSeeOrders())<a class="text-sm font-medium text-brand-700 transition hover:text-brand-800" href="{{ route('notifications.index') }}">View all →</a>@endif
        </div>
        <ul class="divide-y divide-ink-100">
            @forelse ($activity as $a)
                <li>
                    <a href="{{ $a['url'] }}" class="flex items-start gap-3 px-5 py-3.5 transition hover:bg-ink-50/50">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ ['amber' => 'bg-amber-50 text-amber-600', 'green' => 'bg-emerald-50 text-emerald-600', 'slate' => 'bg-ink-100 text-ink-700'][$a['tone']] }}">
                            <x-icon :name="$a['icon']" class="h-4 w-4" />
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block truncate text-sm font-semibold text-ink-900">{{ $a['title'] }}</span>
                            <span class="block truncate text-xs text-ink-500">{{ $a['sub'] }}{{ $a['sub'] ? ' · ' : '' }}{{ $a['at']->diffForHumans() }}</span>
                        </span>
                    </a>
                </li>
            @empty
                <li class="px-5 py-12 text-center text-sm text-ink-500">Nothing yet. Activity shows up here as it happens.</li>
            @endforelse
        </ul>
    </div>
</section>

{{-- Menu management + Quick actions --}}
<section class="mt-6 grid gap-4 xl:grid-cols-3">
    {{-- Menu management --}}
    <div class="card overflow-hidden xl:col-span-2">
        <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
            <div>
                <h2 class="text-base font-bold text-ink-900">Menu Management</h2>
                <p class="mt-0.5 text-xs text-ink-500">Manage your categories, items, prices and availability</p>
            </div>
            @if ($canMenu)<a href="{{ route('menu.index') }}" class="btn btn-primary !py-2">Manage Menu</a>@endif
        </div>
        {{-- Category tabs --}}
        <div class="border-b border-ink-100 px-5">
            <div class="flex gap-6 text-sm font-medium">
                <span class="border-b-2 border-brand-600 py-3 text-brand-700">Categories</span>
                <span class="py-3 text-ink-400">Items</span>
                <span class="py-3 text-ink-400">Prices & Availability</span>
            </div>
        </div>
        <ul class="divide-y divide-ink-100">
            @forelse ($categories as $c)
                <li class="flex items-center gap-4 px-5 py-3.5 transition hover:bg-ink-50/30">
                    <img src="{{ $c->picture }}" alt="" class="h-14 w-14 rounded-xl object-cover shadow-sm" loading="lazy">
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-ink-900">{{ $c->name }}</p>
                        <p class="text-xs text-ink-500">{{ $c->items_count }} {{ Str::plural('item', $c->items_count) }}</p>
                    </div>
                    <button class="flex h-8 w-8 items-center justify-center rounded-lg text-ink-400 transition hover:bg-ink-100 hover:text-ink-700" aria-label="Edit {{ $c->name }}">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3l4 4L7 21H3v-4z"/></svg>
                    </button>
                </li>
            @empty
                <li class="px-5 py-12 text-center text-ink-500">
                    No categories yet.
                    @if($isOwner)<a class="font-semibold text-brand-700 underline" href="{{ route('menu.index') }}">Add your first category.</a>@endif
                </li>
            @endforelse
        </ul>
    </div>

    {{-- Quick actions + ad card --}}
    <div class="space-y-4">
        <div class="card p-5">
            <h2 class="text-base font-bold text-ink-900">Quick Actions</h2>
            <div class="mt-4 grid grid-cols-2 gap-3">
                @foreach (array_filter([
                    $isOwner ? ['Add Menu Item', 'plus', route('menu.items.create')] : null,
                    ['Manage Branches', 'pin', route('branches.index')],
                    $o->canSeeOrders() ? ['View Orders', 'receipt', route('orders.index')] : null,
                    $canMenu && $o->branches->isNotEmpty() ? ['Update Prices', 'card', route('branch.menu.edit', $o->selected ?? $o->branches->first())] : null,
                ]) as [$label, $icon, $href])
                    <a href="{{ $href }}" class="group flex flex-col items-center gap-2.5 rounded-2xl border border-ink-100 bg-white p-4 text-center text-xs font-medium transition hover:border-brand-500 hover:shadow-md">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 transition group-hover:bg-brand-100">
                            <x-icon :name="$icon" class="h-5 w-5" />
                        </span>
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Grow your business card --}}
        <div class="overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-violet-100/50 p-5">
            <p class="flex items-center gap-2 text-base font-bold text-violet-900"><x-icon name="megaphone" class="h-5 w-5" /> Grow Your Business</p>
            <p class="mt-2 text-xs text-violet-700">Run targeted ads on ZIWAIT and reach more customers.</p>
            <span class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-violet-600 px-4 py-2 text-xs font-semibold text-white shadow-sm">
                Create Campaign
                <x-icon name="arrow" class="h-3.5 w-3.5" />
            </span>
        </div>
    </div>
</section>

{{-- Billing, chart, branches --}}
<section class="mt-6 grid gap-4 xl:grid-cols-3">
    @if ($isOwner)
    <div class="card overflow-hidden">
        <div class="border-b border-ink-100 px-5 py-4">
            <h2 class="text-base font-bold text-ink-900">Subscription & Billing</h2>
        </div>
        <div class="p-5">
            @if ($subscription)
                <div class="flex items-center justify-between">
                    <p class="font-semibold text-ink-900">{{ $subscription->plan->name }} Plan</p>
                    <x-status :value="$subscription->status" />
                </div>
                @if ($subscription->planPrice)
                    <p class="mt-2 text-2xl font-extrabold text-ink-900">{{ App\Support\MoneyFormat::display($subscription->planPrice->amount_minor, $subscription->planPrice->currency) }}<span class="text-sm font-normal text-ink-500">/{{ $subscription->planPrice->interval }}</span></p>
                @endif
                <p class="mt-1 text-xs text-ink-500">{{ $subscription->status->value === 'trialing' ? 'Trial ends' : 'Next billing' }}: {{ $subscription->current_period_end?->format('M j, Y') }}</p>
                <div class="mt-4 flex gap-2">
                    <a class="btn btn-outline flex-1 !py-2 text-xs" href="{{ route('billing.index') }}#history">View Invoices</a>
                    <a class="btn btn-primary flex-1 !py-2 text-xs" href="{{ route('billing.index') }}">Manage Plan</a>
                </div>
            @else
                <p class="text-sm text-ink-500">No plan yet.</p>
                <a class="btn btn-primary mt-4 w-full" href="{{ route('billing.index') }}">Choose a plan</a>
            @endif
        </div>
    </div>
    @endif

    {{-- Performance chart --}}
    <div @class(['card overflow-hidden', 'xl:col-span-1' => $isOwner, 'xl:col-span-2' => ! $isOwner])>
        <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
            <div>
                <h2 class="text-base font-bold text-ink-900">Performance Overview</h2>
                <p class="text-xs text-ink-500">Last 7 days</p>
            </div>
            <span class="rounded-lg bg-ink-100 px-3 py-1.5 text-[10px] font-semibold text-ink-600">Last 7 days ▾</span>
        </div>
        <div class="p-5">
            @if ($chart['empty'])
                <p class="flex h-36 items-center justify-center text-center text-sm text-ink-500">No orders in the last 7 days yet.</p>
            @else
                <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="w-full" role="img" aria-label="Orders per day for the last 7 days, {{ $chart['total'] }} in total">
                    <defs><linearGradient id="ord" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#14a674" stop-opacity=".25"/><stop offset="1" stop-color="#14a674" stop-opacity="0"/></linearGradient></defs>
                    @foreach ($chart['yLabels'] as [$y, $label])<line x1="{{ $chart['left'] }}" x2="{{ $chart['right'] }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e6ebef"/>
                    <text x="{{ $chart['left'] - 5 }}" y="{{ $y + 3 }}" text-anchor="end" font-size="9" fill="#5b6b78">{{ $label }}</text>@endforeach
                    <path d="{{ $chart['area'] }}" fill="url(#ord)"/>
                    <polyline points="{{ $chart['line'] }}" fill="none" stroke="#0e9163" stroke-width="2.2" stroke-linejoin="round"/>
                    @foreach ($chart['points'] as [$x, $y])<circle cx="{{ $x }}" cy="{{ $y }}" r="3" fill="#fff" stroke="#0e9163" stroke-width="2"/>@endforeach
                    @foreach ($chart['xLabels'] as [$x, $label])<text x="{{ $x }}" y="{{ $chart['height'] - 6 }}" text-anchor="middle" font-size="9" fill="#5b6b78">{{ $label }}</text>@endforeach
                </svg>
            @endif
        </div>
    </div>

    {{-- Branches --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4">
            <h2 class="text-base font-bold text-ink-900">Your Branches</h2>
            <a class="text-sm font-medium text-brand-700 transition hover:text-brand-800" href="{{ route('branches.index') }}">View all →</a>
        </div>
        <ul class="divide-y divide-ink-100">
            @forelse ($o->branches->take(3) as $b)
                <li class="flex items-center gap-3 px-5 py-3.5 transition hover:bg-ink-50/50">
                    <img src="{{ $b->photo_path ? asset('storage/'.$b->photo_path) : asset('images/stock/branch-'.(($b->id % 2) + 1).'.jpg') }}" alt="" class="h-12 w-12 rounded-xl object-cover shadow-sm" loading="lazy">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-ink-900">{{ $b->name }}</p>
                        <p class="truncate text-xs text-ink-500">{{ $b->address ?: $b->city }}</p>
                    </div>
                    <x-status :value="$b->is_active ? 'active' : 'disabled'" />
                </li>
            @empty
                <li class="px-5 py-12 text-center text-sm text-ink-500">No branches assigned to you.</li>
            @endforelse
        </ul>
    </div>
</section>
@endsection
