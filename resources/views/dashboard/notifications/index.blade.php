@extends('layouts.app', ['title' => 'Notifications'])
@section('content')
<h1 class="mb-4 text-2xl font-extrabold">Notifications</h1>
<section class="card mb-6 p-5" aria-labelledby="alerts-h" data-push data-url="{{ route('push.store') }}" data-key="{{ config('webpush.vapid.public_key') }}">
    <h2 id="alerts-h" class="font-bold">New-order alerts</h2>
    <p class="text-sm text-ink-500">Get a push notification on this device the moment a customer places an order. If you have not turned on push on any device, we email you instead so you never miss an order.</p>
    <p class="mt-2 text-sm" data-push-status role="status" aria-live="polite"></p>
    <p class="text-xs text-ink-500">{{ $devices }} {{ Str::plural('device', $devices) }} subscribed on your account.</p>
    <button type="button" data-push-toggle hidden class="btn btn-outline mt-3"></button>
</section>
<h2 class="mb-2 font-bold">Recent delivery attempts</h2>
<div class="card overflow-x-auto"><table class="w-full text-left text-sm">
    <thead class="bg-ink-50 text-xs text-ink-500"><tr><th class="p-3">When</th><th class="p-3">Order</th><th class="p-3">Channel</th><th class="p-3">Result</th></tr></thead>
    <tbody>@forelse ($logs as $l)
        <tr class="border-t border-ink-100"><td class="p-3 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($l->created_at)->diffForHumans() }}</td><td class="p-3 font-mono text-xs">{{ $l->order?->reference }}</td>
            <td class="p-3">{{ $l->channel === 'webpush' ? 'Push' : 'Email' }}</td><td class="p-3"><x-status :value="$l->status === 'sent' ? 'active' : 'failed'" /></td></tr>
    @empty<tr><td colspan="4" class="p-8 text-center text-ink-500">No alerts sent yet.</td></tr>@endforelse</tbody>
</table></div>
@endsection
