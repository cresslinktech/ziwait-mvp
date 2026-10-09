@extends('layouts.admin', ['title' => 'Branches'])
@section('content')
<h1 class="mb-1 text-2xl font-extrabold">Branches</h1>
<form method="GET" class="mb-4 flex gap-2"><label class="sr-only" for="q">Search</label>
    <input id="q" name="q" value="{{ $q }}" placeholder="Branch name or city" class="w-72 rounded-lg border border-ink-100 px-3 py-2 text-sm"><button class="btn btn-outline">Search</button></form>
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-3">Branch</th><th class="p-3">Business</th><th class="p-3">City</th><th class="p-3">Timezone</th><th class="p-3">Status</th><th class="p-3">Created</th></tr></thead>
    <tbody>@forelse ($branches as $b)
        <tr class="border-t border-ink-100"><td class="p-3 font-medium">{{ $b->name }}</td><td class="p-3">{{ $b->business->name }}</td><td class="p-3">{{ $b->city }}</td><td class="p-3">{{ $b->timezone }}</td>
            <td class="p-3"><x-status :value="$b->is_active ? 'active' : 'disabled'" /></td><td class="p-3 whitespace-nowrap">{{ $b->created_at->format('M j, Y') }}</td></tr>
    @empty<tr><td colspan="6" class="p-8 text-center text-ink-500">No branches found.</td></tr>@endforelse</tbody>
</table></div>
<div class="mt-4">{{ $branches->links() }}</div>
@endsection
