@extends('layouts.app')
@section('content')
<header class="mb-5 flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Orders</h1><p class="mt-1 text-sm text-ink-500">Review new orders, check table numbers, and update kitchen status.</p></div>
    <p class="text-sm text-ink-500">{{ $orders->total() }} {{ Str::plural('order', $orders->total()) }}</p>
</header>
<form method="GET" class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-ink-100 bg-white p-3 text-sm">
    <div><label for="order-status" class="mb-1 block text-[10px] font-semibold uppercase text-ink-500">Status</label><select id="order-status" name="status" class="rounded-lg border border-ink-200 px-2.5 py-2"><option value="">All statuses</option>
        @foreach ($statuses as $s)<option value="{{ $s->value }}" @selected(request('status') === $s->value)>{{ $s->label() }}</option>@endforeach</select>
    </div><div><label for="order-branch" class="mb-1 block text-[10px] font-semibold uppercase text-ink-500">Branch</label><select id="order-branch" name="branch_id" class="rounded-lg border border-ink-200 px-2.5 py-2"><option value="">All branches</option>
        @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch_id') == $b->id)>{{ $b->name }}</option>@endforeach</select>
    </div><div class="min-w-40 flex-1"><label for="order-search" class="mb-1 block text-[10px] font-semibold uppercase text-ink-500">Search</label><input id="order-search" name="q" value="{{ request('q') }}" placeholder="Reference, table, or customer" class="w-full rounded-lg border border-ink-200 px-2.5 py-2"></div>
    <div><label for="order-from" class="mb-1 block text-[10px] font-semibold uppercase text-ink-500">From</label><input id="order-from" type="date" name="from" value="{{ request('from') }}" class="rounded-lg border border-ink-200 px-2.5 py-2"></div>
    <div><label for="order-to" class="mb-1 block text-[10px] font-semibold uppercase text-ink-500">To</label><input id="order-to" type="date" name="to" value="{{ request('to') }}" class="rounded-lg border border-ink-200 px-2.5 py-2"></div>
    <button class="btn btn-primary !px-4 !py-2">Apply</button>
    @if (request()->query())<a href="{{ route('orders.index') }}" class="px-2 py-2 text-xs font-semibold text-ink-500 underline">Clear</a>@endif
</form>
<div class="card overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm">
    <thead class="bg-ink-50 text-[10px] uppercase tracking-wide text-ink-500"><tr><th class="p-3">Order</th><th class="p-3">Branch / table</th><th class="p-3">Items</th><th class="p-3">Total</th><th class="p-3">Status</th><th class="p-3">Placed</th><th class="p-3"></th></tr></thead>
    <tbody>
    @forelse ($orders as $order)
        <tr class="border-t border-ink-100 hover:bg-ink-50/60">
            <td class="p-3"><a class="font-mono text-xs font-bold text-brand-800 underline underline-offset-2" href="{{ route('orders.show', $order) }}">{{ $order->reference }}</a><span class="mt-1 block text-[10px] text-ink-500">{{ $order->placed_at->format('M j, g:i A') }}</span></td>
            <td class="p-3"><span class="block font-medium">{{ $order->branch->name }}</span><span class="text-xs text-ink-500">{{ $order->table_number ? 'Table '.$order->table_number : $order->customer_name }}</span></td>
            <td class="p-3">{{ $order->items_count }} {{ Str::plural('item', $order->items_count) }}</td>
            <td class="p-3 font-semibold">{{ App\Support\MoneyFormat::display($order->total_minor, $order->currency) }}</td>
            <td class="p-3"><x-status :value="$order->status" /></td>
            <td class="p-3 text-xs text-ink-500">{{ $order->placed_at->diffForHumans() }}</td>
            <td class="p-3"><a class="btn btn-outline !px-3 !py-1.5 text-xs" href="{{ route('orders.show', $order) }}">Open</a></td>
        </tr>
    @empty
        <tr><td colspan="7" class="p-10 text-center"><p class="text-sm font-semibold">No matching orders</p><p class="mt-1 text-xs text-ink-500">Try clearing filters or check another date range.</p></td></tr>
    @endforelse
    </tbody>
</table></div>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
