@extends('layouts.app', ['title' => 'Customers'])
@section('content')
<h1 class="text-2xl font-extrabold">Customers</h1>
<p class="mb-4 text-sm text-ink-500">People who have ordered from your branches, grouped by phone number. Contact details are removed automatically after the retention period.</p>
<form method="GET" class="mb-4 flex gap-2"><label class="sr-only" for="q">Search</label><input id="q" name="q" value="{{ $q }}" placeholder="Name or phone" class="w-72 rounded-lg border border-ink-100 px-3 py-2 text-sm"><button class="btn btn-outline">Search</button></form>
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-3">Customer</th><th class="p-3">Phone</th><th class="p-3">Orders</th><th class="p-3">Total ordered</th><th class="p-3">Last order</th></tr></thead>
    <tbody>@forelse ($customers as $c)
        <tr class="border-t border-ink-100"><td class="p-3 font-medium">{{ $c->name }}</td><td class="p-3">{{ $c->customer_phone }}</td><td class="p-3">{{ $c->orders }}</td>
            <td class="p-3">{{ App\Support\MoneyFormat::display((int) $c->spent, $currency) }}</td><td class="p-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($c->last_order)->format('M j, Y') }}</td></tr>
    @empty<tr><td colspan="5" class="p-8 text-center text-ink-500">No customers yet.</td></tr>@endforelse</tbody>
</table></div>
<div class="mt-4">{{ $customers->links() }}</div>
@endsection
