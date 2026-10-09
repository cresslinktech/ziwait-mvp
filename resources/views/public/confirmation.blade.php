@extends('layouts.guest', ['title' => 'Order received'])
@section('content')
<div class="mx-auto max-w-lg rounded bg-white p-6 shadow-sm">
    <h1 class="text-2xl font-bold">Order received</h1>
    <p class="mt-1 text-stone-600">Your reference is <strong class="font-mono text-lg">{{ $order->reference }}</strong>.</p>
    <p class="mt-1 text-sm">Status: <strong>{{ $order->status->label() }}</strong> · {{ $order->branch->name }}</p>
    <ul class="mt-4 divide-y">
        @foreach ($order->items as $item)
            <li class="flex justify-between py-2"><span>{{ $item->quantity }} × {{ $item->name }}</span><span>{{ App\Support\MoneyFormat::display($item->line_total_minor, $order->currency) }}</span></li>
        @endforeach
    </ul>
    <p class="mt-3 flex justify-between border-t pt-3 font-semibold"><span>Total</span><span>{{ App\Support\MoneyFormat::display($order->total_minor, $order->currency) }}</span></p>
    <p class="mt-4 rounded bg-amber-50 p-3 text-sm text-amber-900">You have not paid yet. Please arrange payment directly with the restaurant. Quote your reference when you do.</p>
</div>
@endsection
