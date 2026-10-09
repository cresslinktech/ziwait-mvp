@extends('layouts.admin', ['title' => 'Dashboard'])
@section('content')
@php
    $cur = $o->currency;
    $trend = fn (?float $g, string $suffix = 'from previous period') => $g === null
        ? '<span class="text-ink-500">Not enough history yet</span>'
        : '<span class="'.($g >= 0 ? 'text-emerald-600' : 'text-red-600').'">'.($g >= 0 ? '+' : '').$g.'%</span> <span class="text-ink-500">'.$suffix.'</span>';
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-2xl font-extrabold">Super Admin Dashboard</h1><p class="text-sm text-ink-500">Platform overview and key metrics</p></div>
    <form method="GET" class="flex items-center gap-2"><label for="days" class="sr-only">Period</label>
        <select id="days" name="days" onchange="this.form.submit()" class="rounded-lg border border-ink-100 bg-white px-3 py-2 text-sm">
            @foreach ([7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'] as $d => $label)<option value="{{ $d }}" @selected($days === $d)>{{ $label }}</option>@endforeach
        </select><noscript><button class="btn btn-outline">Apply</button></noscript></form>
</div>

{{-- KPI tiles --}}
<section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Key metrics">
    @foreach ([
        ['Total Businesses', number_format($kpis['businesses']['value']), $trend($kpis['businesses']['growth'], 'vs '.$days.' days ago'), 'business', 'bg-emerald-50 text-emerald-600'],
        ['Total Branches', number_format($kpis['branches']['value']), $trend($kpis['branches']['growth'], 'vs '.$days.' days ago'), 'pin', 'bg-sky-50 text-sky-600'],
        ['Active Subscriptions', number_format($kpis['subscriptions']['value']), '<span class="text-ink-500">'.number_format($kpis['subscriptions']['trialing']).' on free trial</span>', 'layers', 'bg-violet-50 text-violet-600'],
        ['Revenue (last '.$days.' days)', App\Support\MoneyFormat::display($kpis['revenue']['value'], $cur), $trend($kpis['revenue']['growth'], 'vs previous '.$days.' days'), 'card', 'bg-amber-50 text-amber-600'],
    ] as [$label, $value, $sub, $icon, $tone])
        <div class="card p-5">
            <div class="flex items-center gap-3"><span class="flex h-11 w-11 items-center justify-center rounded-xl {{ $tone }}"><x-icon :name="$icon" class="h-6 w-6" /></span><p class="text-sm font-medium text-ink-500">{{ $label }}</p></div>
            <p class="mt-3 text-3xl font-extrabold tracking-tight">{{ $value }}</p>
            <p class="mt-1 text-xs">{!! $sub !!}</p>
        </div>
    @endforeach
</section>

{{-- Revenue chart and plan donut --}}
<section class="mt-6 grid gap-4 xl:grid-cols-3">
    <div class="card p-5 xl:col-span-2">
        <h2 class="font-bold">Revenue overview</h2>
        <p class="text-xs text-ink-500">Successful subscription payments in {{ $cur }}, per day</p>
        @if ($chart['empty'])
            <p class="flex h-56 items-center justify-center text-sm text-ink-500">No payments in this period yet.</p>
        @else
            <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" class="mt-3 w-full" role="img" aria-label="Daily revenue for the last {{ $days }} days, {{ App\Support\MoneyFormat::display($chart['total'], $cur) }} in total">
                <defs><linearGradient id="rev" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#14a674" stop-opacity=".28"/><stop offset="1" stop-color="#14a674" stop-opacity="0"/></linearGradient></defs>
                @foreach ($chart['yLabels'] as [$y, $label])
                    <line x1="{{ $chart['left'] }}" x2="{{ $chart['right'] }}" y1="{{ $y }}" y2="{{ $y }}" stroke="#e6ebef" stroke-width="1"/>
                    <text x="{{ $chart['left'] - 6 }}" y="{{ $y + 3 }}" text-anchor="end" font-size="10" fill="#5b6b78">{{ $label }}</text>
                @endforeach
                <path d="{{ $chart['area'] }}" fill="url(#rev)"/>
                <polyline points="{{ $chart['line'] }}" fill="none" stroke="#0e9163" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
                @foreach ($chart['xLabels'] as [$x, $label])<text x="{{ $x }}" y="{{ $chart['height'] - 6 }}" text-anchor="middle" font-size="10" fill="#5b6b78">{{ $label }}</text>@endforeach
            </svg>
        @endif
    </div>
    <div class="card p-5">
        <h2 class="font-bold">Subscription plans</h2>
        <p class="text-xs text-ink-500">Active subscriptions by plan</p>
        @if ($plans['total'] === 0)
            <p class="flex h-56 items-center justify-center text-sm text-ink-500">No active subscriptions yet.</p>
        @else
            <div class="mt-4 flex flex-wrap items-center justify-center gap-6">
                <svg viewBox="0 0 100 100" class="h-40 w-40" role="img" aria-label="Active subscriptions by plan">
                    <g transform="rotate(-90 50 50)">
                        @foreach ($plans['slices'] as $s)<circle cx="50" cy="50" r="40" fill="none" stroke="{{ $s['color'] }}" stroke-width="14" stroke-dasharray="{{ $s['dash'] }} {{ $s['gap'] }}" stroke-dashoffset="{{ $s['offset'] }}"/>@endforeach
                    </g>
                    <text x="50" y="50" text-anchor="middle" font-size="14" font-weight="800" fill="#0b1f2d">{{ $plans['total'] }}</text>
                    <text x="50" y="62" text-anchor="middle" font-size="7" fill="#5b6b78">Active</text>
                </svg>
                <ul class="space-y-2 text-sm">
                    @foreach ($plans['slices'] as $s)<li class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $s['color'] }}"></span>{{ $s['name'] }} <span class="ml-auto pl-4 font-semibold">{{ $s['percent'] }}%</span></li>@endforeach
                </ul>
            </div>
        @endif
    </div>
</section>

{{-- Recent businesses and payments --}}
<section class="mt-6 grid gap-4 xl:grid-cols-2">
    <div class="card p-5">
        <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Recent businesses</h2><a class="text-sm font-medium text-brand-700" href="{{ route('admin.businesses') }}">View all →</a></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-2">Business</th><th class="p-2">Branches</th><th class="p-2">Plan</th><th class="p-2">Status</th><th class="p-2">Joined</th></tr></thead>
            <tbody>@forelse ($businesses as $b)
                <tr class="border-t border-ink-100"><td class="p-2 font-medium">{{ $b->name }}</td><td class="p-2">{{ $b->branches_count }}</td><td class="p-2">{{ $b->subscription?->plan->name ?? '-' }}</td>
                    <td class="p-2"><x-status :value="$b->subscription?->status ?? 'none'" /></td><td class="p-2 whitespace-nowrap">{{ $b->created_at->format('M j, Y') }}</td></tr>
            @empty<tr><td colspan="5" class="p-6 text-center text-ink-500">No businesses have registered yet.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
    <div class="card p-5">
        <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Recent payments</h2><a class="text-sm font-medium text-brand-700" href="{{ route('admin.payments') }}">View all →</a></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-2">Business</th><th class="p-2">Amount</th><th class="p-2">Status</th><th class="p-2">Date</th></tr></thead>
            <tbody>@forelse ($payments as $p)
                <tr class="border-t border-ink-100"><td class="p-2 font-medium">{{ $p->business->name }}</td><td class="p-2">{{ App\Support\MoneyFormat::display($p->amount_minor, $p->currency) }}</td>
                    <td class="p-2"><x-status :value="$p->status" /></td><td class="p-2 whitespace-nowrap">{{ $p->created_at->format('M j, Y') }}</td></tr>
            @empty<tr><td colspan="4" class="p-6 text-center text-ink-500">No payments yet.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
</section>

{{-- Advertisements and system health --}}
<section class="mt-6 grid gap-4 xl:grid-cols-3">
    <div class="card p-5 xl:col-span-2">
        <div class="mb-3 flex items-center justify-between gap-3"><h2 class="font-bold">Advertisements</h2><a href="{{ route('admin.advertisements') }}" class="text-sm font-semibold text-brand-700">Manage adverts →</a></div>
        @forelse ($advertisements as $advertisement)
            <div class="flex items-center gap-3 border-t border-ink-100 py-3">
                <img src="{{ asset('storage/'.$advertisement->image_path) }}" alt="" class="h-12 w-16 rounded-lg object-cover">
                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold">{{ $advertisement->title }}</p><p class="text-xs text-ink-500">{{ \App\Domain\Marketing\Advertisement::PLACEMENTS[$advertisement->placement] ?? $advertisement->placement }}</p></div>
                <span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $advertisement->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-ink-100 text-ink-500' }}">{{ $advertisement->is_active ? 'Active' : 'Paused' }}</span>
            </div>
        @empty
            <p class="flex h-32 items-center justify-center text-center text-sm text-ink-500">No advertisements have been placed yet.</p>
        @endforelse
    </div>
    <div class="card p-5">
        <h2 class="font-bold">System health</h2>
        <ul class="mt-3 space-y-3 text-sm">
            @foreach ($health as [$name, $state, $label])
                <li class="flex items-center gap-2"><span class="flex h-5 w-5 items-center justify-center rounded-full {{ $state === 'ok' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}"><x-icon name="{{ $state === 'ok' ? 'check' : 'bell' }}" class="h-3 w-3" /></span>
                    {{ $name }}<span class="ml-auto font-medium {{ $state === 'ok' ? 'text-emerald-700' : 'text-amber-700' }}">{{ $label }}</span></li>
            @endforeach
        </ul>
    </div>
</section>

{{-- Quick actions and activity --}}
<section class="mt-6 grid gap-4 xl:grid-cols-2">
    <div class="card p-5">
        <h2 class="font-bold">Quick actions</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([
                ['Review payments', 'receipt', route('admin.payments'), $counts['open_reviews'].' open'],
                ['Businesses', 'business', route('admin.businesses'), null],
                ['Subscriptions', 'layers', route('admin.subscriptions'), null],
                ['Enquiries', 'mail', route('admin.enquiries'), $counts['new_enquiries'].' new'],
            ] as [$label, $icon, $href, $note])
                <a href="{{ $href }}" class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-ink-300 p-4 text-center text-xs font-medium text-ink-700 hover:border-brand-600 hover:text-brand-700">
                    <x-icon :name="$icon" class="h-6 w-6 text-brand-600" />{{ $label }}@if($note)<span class="text-ink-500">{{ $note }}</span>@endif
                </a>
            @endforeach
        </div>
    </div>
    <div class="card p-5">
        <div class="mb-3 flex items-center justify-between"><h2 class="font-bold">Recent activity</h2><a class="text-sm font-medium text-brand-700" href="{{ route('admin.audit') }}">View all →</a></div>
        <ul class="space-y-3 text-sm">
            @forelse ($activity as $log)
                <li class="flex gap-3"><span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                    <div class="flex-1"><p>{{ App\Domain\Reporting\PlatformOverview::describe($log->action) }}@if($log->business_name): <strong>{{ $log->business_name }}</strong>@endif</p>
                        <p class="text-xs text-ink-500">{{ $log->user_name ? 'by '.$log->user_name.' · ' : '' }}{{ \Illuminate\Support\Carbon::parse($log->created_at)->diffForHumans() }}</p></div></li>
            @empty<li class="text-ink-500">Nothing has happened yet.</li>@endforelse
        </ul>
    </div>
</section>
@endsection
