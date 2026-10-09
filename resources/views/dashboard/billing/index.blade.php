@extends('layouts.app')
@section('content')
<h1 class="mb-4 text-2xl font-bold">Billing</h1>
@error('plan_price_id')<p role="alert" class="mb-4 rounded bg-red-50 p-3 text-sm text-red-800">{{ $message }}</p>@enderror

<section class="mb-6 rounded bg-white p-4 shadow-sm" aria-labelledby="cur">
    <h2 id="cur" class="font-semibold">Current plan</h2>
    @if ($subscription)
        <p>{{ $subscription->plan->name }} · <strong>{{ str_replace('_', ' ', ucfirst($subscription->status->value)) }}</strong></p>
        <p class="text-sm text-ink-500">
            {{ $subscription->status->value === 'trialing' ? 'Trial ends' : 'Current period ends' }} {{ $subscription->current_period_end?->format('j M Y') }}.
            Branches used: {{ $branchCount }} of {{ $subscription->plan->maxBranches() ?? 'unlimited' }}.
        </p>
    @else
        <p class="text-ink-500">No active plan. Choose one below to start taking orders.</p>
    @endif
</section>

<section class="mb-8 grid gap-4 sm:grid-cols-3" aria-label="Plans">
    @foreach ($plans as $plan)
        <div class="rounded border bg-white p-4">
            <h3 class="font-semibold">{{ $plan->name }}</h3>
            <p class="text-sm text-ink-500">{{ $plan->description }}</p>
            <p class="mt-1 text-xs text-ink-500">Branches: {{ $plan->maxBranches() ?? 'Unlimited' }}</p>
            @foreach ($plan->prices as $price)
                <form method="POST" action="{{ route('billing.checkout') }}" class="mt-3">@csrf
                    <input type="hidden" name="plan_price_id" value="{{ $price->id }}">
                    <button class="w-full rounded border px-3 py-2 text-sm">
                        {{ App\Support\MoneyFormat::display($price->amount_minor, $price->currency) }} / {{ $price->interval }}: Pay with Paystack
                    </button>
                </form>
            @endforeach
        </div>
    @endforeach
</section>

<h2 id="history" class="mb-2 font-semibold">Payment history</h2>
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead><tr><th class="p-2">Date</th><th class="p-2">Plan</th><th class="p-2">Amount</th><th class="p-2">Status</th><th class="p-2">Receipt</th></tr></thead>
    <tbody>
    @forelse ($payments as $payment)
        <tr class="border-t">
            <td class="p-2">{{ $payment->created_at->format('j M Y H:i') }}</td>
            <td class="p-2">{{ $payment->planPrice->plan->name }} / {{ $payment->planPrice->interval }}</td>
            <td class="p-2">{{ App\Support\MoneyFormat::display($payment->amount_minor, $payment->currency) }}</td>
            <td class="p-2">{{ ucfirst($payment->status->value) }}</td>
            <td class="p-2 font-mono text-xs">{{ $payment->invoice?->number }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="p-6 text-center text-ink-500">No payments yet.</td></tr>
    @endforelse
    </tbody>
</table></div>
@endsection
