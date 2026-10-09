@extends('layouts.admin', ['title' => 'Users'])
@section('content')
<h1 class="mb-1 text-2xl font-extrabold">Users</h1>
<form method="GET" class="mb-4 flex gap-2"><label class="sr-only" for="q">Search</label>
    <input id="q" name="q" value="{{ $q }}" placeholder="Name or email" class="w-72 rounded-lg border border-ink-100 px-3 py-2 text-sm"><button class="btn btn-outline">Search</button></form>
@error('reason')<p role="alert" class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-3">Name</th><th class="p-3">Email</th><th class="p-3">Businesses</th><th class="p-3">Type</th><th class="p-3">Account</th><th class="p-3">Joined</th><th class="p-3"></th></tr></thead>
    <tbody>@forelse ($users as $u)
        <tr class="border-t border-ink-100"><td class="p-3 font-medium">{{ $u->name }}</td><td class="p-3">{{ $u->email }}</td><td class="p-3">{{ $u->business_memberships_count }}</td>
            <td class="p-3">{{ $u->is_super_admin ? 'Super admin' : 'Business user' }}</td><td class="p-3"><x-status :value="$u->disabled_at ? 'disabled' : 'active'" /></td><td class="p-3 whitespace-nowrap">{{ $u->created_at->format('M j, Y') }}</td>
            <td class="p-3">@unless ($u->is(auth()->user()))
                <form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="flex gap-1" onsubmit="return confirm('{{ $u->disabled_at ? 'Re-enable' : 'Disable' }} {{ e($u->email) }}?')">@csrf
                    <input name="reason" required minlength="5" maxlength="300" placeholder="Reason" aria-label="Reason" class="w-28 rounded border border-ink-100 px-2 py-1 text-xs">
                    <button class="rounded border px-2 py-1 text-xs">{{ $u->disabled_at ? 'Enable' : 'Disable' }}</button></form>@endunless</td></tr>
    @empty<tr><td colspan="7" class="p-8 text-center text-ink-500">No users found.</td></tr>@endforelse</tbody>
</table></div>
<div class="mt-4">{{ $users->links() }}</div>
@endsection
