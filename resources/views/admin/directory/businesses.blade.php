@extends('layouts.admin', ['title' => 'Businesses'])
@section('content')
<h1 class="mb-1 text-2xl font-extrabold">Businesses</h1>
<form method="GET" class="mb-4 flex gap-2"><label class="sr-only" for="bq">Search</label>
    <input id="bq" name="q" value="{{ $q }}" placeholder="Name or email" class="w-72 rounded-lg border border-ink-100 px-3 py-2 text-sm"><button class="btn btn-outline">Search</button></form>
@error('reason')<p role="alert" class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-3">Business</th><th class="p-3">Owner</th><th class="p-3">Branches</th><th class="p-3">Plan</th><th class="p-3">Subscription</th><th class="p-3">Account</th><th class="p-3">Joined</th><th class="p-3"></th></tr></thead>
    <tbody>@forelse ($businesses as $b)
        <tr class="border-t border-ink-100">
            <td class="p-3 font-medium">{{ $b->name }}</td><td class="p-3">{{ $b->owner?->email }}</td><td class="p-3">{{ $b->branches_count }}</td>
            <td class="p-3">{{ $b->subscription?->plan->name ?? '-' }}</td><td class="p-3"><x-status :value="$b->subscription?->status ?? 'none'" /></td>
            <td class="p-3"><x-status :value="$b->trashed() ? 'disabled' : $b->status" /></td><td class="p-3 whitespace-nowrap">{{ $b->created_at->format('M j, Y') }}</td>
            <td class="p-3">@unless ($b->trashed())
                <form method="POST" action="{{ route('admin.businesses.toggle', $b) }}" class="flex gap-1" onsubmit="return confirm('{{ $b->isActive() ? 'Suspend' : 'Reactivate' }} {{ e($b->name) }}?')">@csrf
                    <input name="reason" required minlength="5" maxlength="300" placeholder="Reason" aria-label="Reason" class="w-28 rounded border border-ink-100 px-2 py-1 text-xs">
                    <button class="rounded border px-2 py-1 text-xs">{{ $b->isActive() ? 'Suspend' : 'Reactivate' }}</button></form>@endunless</td>
        </tr>
    @empty<tr><td colspan="8" class="p-8 text-center text-ink-500">No businesses found.</td></tr>@endforelse</tbody>
</table></div>
<div class="mt-4">{{ $businesses->links() }}</div>
@endsection
