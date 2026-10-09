@extends('layouts.app')
@section('content')
<h1 class="text-2xl font-bold">Order <span class="font-mono">{{ $order->reference }}</span></h1>
<p class="mb-4 text-sm text-ink-500">{{ $order->branch->name }} · {{ $order->placed_at->format('d M Y H:i') }} · <strong>{{ $order->status->label() }}</strong></p>
@error('status')<p role="alert" class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror

<div class="mb-4 rounded bg-white p-4 shadow-sm">
    @if ($order->table_number)
        <p><strong>Table {{ $order->table_number }}</strong></p>
    @else
        <p><strong>{{ $order->customer_name }}</strong> · {{ $order->customer_phone }} @if($order->customer_email)· {{ $order->customer_email }}@endif</p>
    @endif
    @if ($order->notes)<p class="mt-2 rounded bg-amber-50 p-2 text-sm">Special instructions: {{ $order->notes }}</p>@endif
    <ul class="mt-3 divide-y text-sm">
        @foreach ($order->items as $item)
            <li class="flex justify-between py-1"><span>{{ $item->quantity }} × {{ $item->name }} @ {{ App\Support\MoneyFormat::display($item->unit_price_minor, $order->currency) }}</span><span>{{ App\Support\MoneyFormat::display($item->line_total_minor, $order->currency) }}</span></li>
        @endforeach
    </ul>
    <p class="mt-2 flex justify-between border-t pt-2 font-semibold"><span>Total (payment collected outside ZIWAIT)</span><span>{{ App\Support\MoneyFormat::display($order->total_minor, $order->currency) }}</span></p>
</div>

@if ($order->status->allowedNext())
<form method="POST" action="{{ route('orders.transition', $order) }}" class="mb-6 flex flex-wrap items-center gap-2">@csrf
    <input name="note" placeholder="Note (optional)" maxlength="190" aria-label="Note" class="rounded border px-2 py-1 text-sm">
    @foreach ($order->status->allowedNext() as $next)
        <button name="status" value="{{ $next->value }}" class="rounded border px-3 py-1 text-sm"
            @if (in_array($next->value, ['rejected', 'cancelled'])) onclick="return confirm('Mark this order as {{ $next->label() }}?')" @endif>{{ $next->label() }}</button>
    @endforeach
</form>
@endif

<h2 class="mb-2 font-semibold">History</h2>
<ol class="space-y-1 text-sm">
    @foreach ($order->history as $h)
        <li>{{ $h->created_at->format('d M H:i') }} · {{ $h->to_status->label() }} · {{ $h->user?->name ?? 'Customer' }}@if($h->note) · {{ $h->note }}@endif</li>
    @endforeach
</ol>
@endsection
