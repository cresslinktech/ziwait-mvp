@extends('layouts.admin', ['title' => $advertisement->exists ? 'Edit advertisement' : 'New advertisement'])
@section('content')
<div class="mb-6 flex items-end justify-between gap-3">
    <div><h1 class="text-2xl font-extrabold">{{ $advertisement->exists ? 'Edit advertisement' : 'New advertisement' }}</h1><p class="mt-1 text-sm text-ink-500">Choose the customer-menu slot where this advert should appear.</p></div>
    <a href="{{ route('admin.advertisements') }}" class="text-sm font-semibold text-brand-700 underline underline-offset-4">All advertisements</a>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $advertisement->exists ? route('admin.advertisements.update', $advertisement) : route('admin.advertisements.store') }}" class="max-w-3xl space-y-5">
    @csrf @if($advertisement->exists) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="title" class="block text-sm font-semibold">Headline</label>
            <input id="title" name="title" value="{{ old('title', $advertisement->title) }}" maxlength="120" required class="mt-1 w-full rounded-lg border border-ink-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
            @error('title')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="placement" class="block text-sm font-semibold">Placement</label>
            <select id="placement" name="placement" required class="mt-1 w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
                @foreach ($placements as $value => $label)<option value="{{ $value }}" @selected(old('placement', $advertisement->placement) === $value)>{{ $label }}</option>@endforeach
            </select>
            @error('placement')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>
    <div>
        <label for="body" class="block text-sm font-semibold">Supporting copy</label>
        <textarea id="body" name="body" rows="3" maxlength="500" class="mt-1 w-full rounded-lg border border-ink-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">{{ old('body', $advertisement->body) }}</textarea>
        @error('body')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="label" class="block text-sm font-semibold">Placement label</label>
            <input id="label" name="label" value="{{ old('label', $advertisement->label ?: 'Sponsored') }}" maxlength="40" required class="mt-1 w-full rounded-lg border border-ink-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
            @error('label')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="cta_label" class="block text-sm font-semibold">Button label <span class="font-normal text-ink-400">(optional)</span></label>
            <input id="cta_label" name="cta_label" value="{{ old('cta_label', $advertisement->cta_label) }}" maxlength="40" class="mt-1 w-full rounded-lg border border-ink-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
            @error('cta_label')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </div>
    <div>
        <label for="target_url" class="block text-sm font-semibold">Destination URL <span class="font-normal text-ink-400">(optional)</span></label>
        <input id="target_url" name="target_url" type="url" value="{{ old('target_url', $advertisement->target_url) }}" maxlength="500" placeholder="https://example.com/offer" class="mt-1 w-full rounded-lg border border-ink-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
        @error('target_url')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="rounded-xl border border-ink-100 p-4">
        @if ($advertisement->image_path)<img src="{{ asset('storage/'.$advertisement->image_path) }}" alt="Current advertisement" class="mb-4 h-36 w-full rounded-lg object-cover">@endif
        <label for="image" class="block text-sm font-semibold">Creative image {{ $advertisement->exists ? '(optional to replace)' : '' }}</label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" {{ $advertisement->exists ? '' : 'required' }} class="mt-2 block w-full text-sm text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-800">
        @error('image')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="sort_order" class="block text-sm font-semibold">Display priority</label>
            <input id="sort_order" name="sort_order" type="number" min="0" max="9999" value="{{ old('sort_order', $advertisement->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-ink-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:outline-none">
            @error('sort_order')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label for="starts_at" class="block text-sm font-semibold">Starts <span class="font-normal text-ink-400">(optional)</span></label><input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $advertisement->starts_at?->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-lg border border-ink-200 px-2 py-2.5 text-xs focus:border-brand-500 focus:outline-none">@error('starts_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
            <div><label for="ends_at" class="block text-sm font-semibold">Ends <span class="font-normal text-ink-400">(optional)</span></label><input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $advertisement->ends_at?->format('Y-m-d\\TH:i')) }}" class="mt-1 w-full rounded-lg border border-ink-200 px-2 py-2.5 text-xs focus:border-brand-500 focus:outline-none">@error('ends_at')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror</div>
        </div>
        <label class="flex items-center gap-2 self-end pb-3 text-sm font-semibold"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $advertisement->exists ? $advertisement->is_active : true)) class="h-4 w-4 accent-brand-600"> Active and visible</label>
    </div>
    <div class="flex gap-3 border-t border-ink-100 pt-5">
        <button class="btn btn-primary">{{ $advertisement->exists ? 'Save changes' : 'Create advertisement' }}</button>
        <a href="{{ route('admin.advertisements') }}" class="btn btn-outline">Cancel</a>
    </div>
</form>
@endsection
