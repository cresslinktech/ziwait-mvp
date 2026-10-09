@extends('layouts.admin', ['title' => 'Audit logs'])
@section('content')
<h1 class="mb-1 text-2xl font-extrabold">Audit logs</h1>
<form method="GET" class="mb-4 flex gap-2"><label class="sr-only" for="a">Action prefix</label>
    <input id="a" name="action" value="{{ $action }}" placeholder="Action, e.g. payment or admin" class="w-72 rounded-lg border border-ink-100 px-3 py-2 text-sm"><button class="btn btn-outline">Filter</button></form>
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-3">When</th><th class="p-3">Event</th><th class="p-3">Action code</th><th class="p-3">By</th><th class="p-3">Business</th><th class="p-3">IP</th></tr></thead>
    <tbody>@forelse ($logs as $l)
        <tr class="border-t border-ink-100"><td class="p-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($l->created_at)->format('M j, Y H:i') }}</td><td class="p-3">{{ App\Domain\Reporting\PlatformOverview::describe($l->action) }}</td>
            <td class="p-3 font-mono text-xs">{{ $l->action }}</td><td class="p-3">{{ $l->user_name ?? 'System' }}</td><td class="p-3">{{ $l->business_name }}</td><td class="p-3 font-mono text-xs">{{ $l->ip_address }}</td></tr>
    @empty<tr><td colspan="6" class="p-8 text-center text-ink-500">No entries.</td></tr>@endforelse</tbody>
</table></div>
<div class="mt-4">{{ $logs->links() }}</div>
@endsection
