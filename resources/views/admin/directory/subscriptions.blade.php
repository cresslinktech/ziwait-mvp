@extends('layouts.admin', ['title' => 'Subscriptions'])
@section('content')
<h1 class="mb-1 text-2xl font-extrabold">Subscriptions</h1>
<nav class="mb-4 flex flex-wrap gap-2 text-sm" aria-label="Filter by status">
    <a class="badge {{ ! $status ? 'bg-ink-900 text-white' : 'bg-ink-100' }}" href="{{ route('admin.subscriptions') }}">All</a>
    @foreach (['active', 'trialing', 'past_due', 'expired', 'cancelled'] as $s)<a class="badge {{ $status === $s ? 'bg-ink-900 text-white' : 'bg-ink-100' }}" href="{{ route('admin.subscriptions', ['status' => $s]) }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</a>@endforeach
</nav>
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-3">Business</th><th class="p-3">Plan</th><th class="p-3">Status</th><th class="p-3">Period ends</th><th class="p-3">Grace ends</th></tr></thead>
    <tbody>@forelse ($subscriptions as $s)
        <tr class="border-t border-ink-100"><td class="p-3 font-medium">{{ $s->business->name }}</td><td class="p-3">{{ $s->plan->name }}</td><td class="p-3"><x-status :value="$s->status" /></td>
            <td class="p-3">{{ $s->current_period_end?->format('M j, Y') }}</td><td class="p-3">{{ $s->grace_ends_at?->format('M j, Y') }}</td></tr>
    @empty<tr><td colspan="5" class="p-8 text-center text-ink-500">No subscriptions found.</td></tr>@endforelse</tbody>
</table></div>
<div class="mt-4">{{ $subscriptions->links() }}</div>
@endsection
