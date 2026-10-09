@extends('layouts.admin', ['title' => 'Enquiries'])
@section('content')
<h1 class="mb-4 text-2xl font-extrabold">Enquiries</h1>
<div class="space-y-3">
    @forelse ($enquiries as $e)
        <article class="card p-4 text-sm">
            <div class="flex flex-wrap items-center gap-2"><strong>{{ $e->name }}</strong><a class="text-brand-700 underline" href="mailto:{{ $e->email }}">{{ $e->email }}</a>@if($e->phone)<span>{{ $e->phone }}</span>@endif@if($e->business_name)<span class="text-ink-500">· {{ $e->business_name }}</span>@endif
                <x-status :value="$e->status" /><span class="ml-auto text-xs text-ink-500">{{ $e->created_at->diffForHumans() }}</span></div>
            <p class="mt-2 whitespace-pre-line">{{ $e->message }}</p>
            @if ($e->status === 'new')<form method="POST" action="{{ route('admin.enquiries.handled', $e) }}" class="mt-2">@csrf<button class="rounded border px-3 py-1 text-xs">Mark as handled</button></form>@endif
        </article>
    @empty<p class="card p-8 text-center text-ink-500">No enquiries yet.</p>@endforelse
</div>
<div class="mt-4">{{ $enquiries->links() }}</div>
@endsection
