@extends('layouts.admin', ['title' => 'Advertisements'])
@section('content')
<header class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-2xl font-extrabold">Advertisements</h1><p class="mt-1 text-sm text-ink-500">Manage sponsored creative shown in customer menu placements.</p></div>
    <a href="{{ route('admin.advertisements.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> New advertisement</a>
</header>

@if ($advertisements->isEmpty())
    <div class="border-y border-ink-100 py-16 text-center">
        <x-icon name="megaphone" class="mx-auto h-9 w-9 text-ink-300" />
        <h2 class="mt-3 text-base font-bold">No advertisements yet</h2>
        <p class="mt-1 text-sm text-ink-500">Create an advert to fill the customer-menu placements.</p>
    </div>
@else
    <div class="divide-y divide-ink-100 border-y border-ink-100">
        @foreach ($advertisements as $advertisement)
            <article class="flex flex-wrap items-center gap-4 py-4">
                <img src="{{ asset('storage/'.$advertisement->image_path) }}" alt="" class="h-20 w-28 rounded-lg object-cover">
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-bold">{{ $advertisement->title }}</h2>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $advertisement->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-ink-100 text-ink-600' }}">{{ $advertisement->is_active ? 'Active' : 'Paused' }}</span>
                    </div>
                    <p class="mt-1 text-xs text-ink-500">{{ $placements[$advertisement->placement] ?? $advertisement->placement }} · {{ $advertisement->label }}</p>
                    @if ($advertisement->body)<p class="mt-1 line-clamp-2 text-sm text-ink-700">{{ $advertisement->body }}</p>@endif
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.advertisements.edit', $advertisement) }}" class="text-xs font-semibold text-brand-700 underline underline-offset-2">Edit</a>
                    <form method="POST" action="{{ route('admin.advertisements.toggle', $advertisement) }}">@csrf
                        <button class="text-xs font-semibold text-ink-600 underline underline-offset-2">{{ $advertisement->is_active ? 'Pause' : 'Activate' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.advertisements.destroy', $advertisement) }}" onsubmit="return confirm('Delete this advertisement?')">@csrf @method('DELETE')
                        <button class="text-xs font-semibold text-red-700 underline underline-offset-2">Delete</button>
                    </form>
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
