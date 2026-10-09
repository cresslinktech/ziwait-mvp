@extends('layouts.admin', ['title' => 'Payments'])
@section('content')
<h1 class="mb-4 text-2xl font-bold">Payments and reconciliation</h1>

<h2 class="mb-2 font-semibold">Needs review ({{ $reviews->count() }})</h2>
@forelse ($reviews as $review)
    <div class="mb-3 rounded border bg-white p-4 text-sm">
        <p><strong>{{ str_replace('_', ' ', $review->reason) }}</strong> · {{ $review->payment->business->name }} · <span class="font-mono">{{ $review->payment->reference }}</span></p>
        <p class="text-stone-600">{{ $review->details }} Expected {{ App\Support\MoneyFormat::display($review->payment->amount_minor, $review->payment->currency) }}.</p>
        <form method="POST" action="{{ route('admin.payments.resolve', $review) }}" class="mt-2 flex flex-wrap gap-2">@csrf
            <input name="note" required minlength="5" maxlength="1000" placeholder="Reason for your decision" aria-label="Resolution note" class="flex-1 rounded border px-2 py-1">
            <button name="decision" value="approve" class="rounded border px-3 py-1" onclick="return confirm('Approve and activate this payment?')">Approve</button>
            <button name="decision" value="reject" class="rounded border px-3 py-1">Reject</button>
        </form>
        @error('note')<p class="text-red-700">{{ $message }}</p>@enderror
    </div>
@empty
    <p class="mb-6 text-stone-500">Nothing needs review.</p>
@endforelse

<h2 class="mb-2 mt-8 font-semibold">All payments</h2>
<table class="w-full bg-white text-left text-sm">
    <thead><tr><th class="p-2">Reference</th><th class="p-2">Business</th><th class="p-2">Amount</th><th class="p-2">Status</th><th class="p-2"></th></tr></thead>
    <tbody>
    @foreach ($payments as $payment)
        <tr class="border-t">
            <td class="p-2 font-mono text-xs">{{ $payment->reference }}</td>
            <td class="p-2">{{ $payment->business->name }}</td>
            <td class="p-2">{{ App\Support\MoneyFormat::display($payment->amount_minor, $payment->currency) }}</td>
            <td class="p-2">{{ ucfirst($payment->status->value) }}</td>
            <td class="p-2">@if (in_array($payment->status->value, ['pending', 'failed']))
                <form method="POST" action="{{ route('admin.payments.recheck', $payment) }}">@csrf<button class="underline">Re-check</button></form>@endif</td>
        </tr>
    @endforeach
    </tbody>
</table>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
