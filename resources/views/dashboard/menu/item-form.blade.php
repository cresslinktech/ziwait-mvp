@extends('layouts.app')
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $item->exists ? 'Edit item' : 'New item' }}</h1>
<form method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('menu.items.update', $item) : route('menu.items.store') }}" class="max-w-md">
    @csrf @if ($item->exists) @method('PUT') @endif
    <div class="mb-4"><label for="name" class="block text-sm font-medium">Name</label>
        <input id="name" name="name" value="{{ old('name', $item->name) }}" required class="mt-1 w-full rounded border border-ink-100 px-3 py-2">@error('name')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    <div class="mb-4"><label for="menu_category_id" class="block text-sm font-medium">Category</label>
        <select id="menu_category_id" name="menu_category_id" required class="mt-1 w-full rounded border border-ink-100 px-3 py-2">
            @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('menu_category_id', $item->menu_category_id) == $c->id)>{{ $c->name }}</option>@endforeach
        </select>@error('menu_category_id')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    <div class="mb-4"><label for="description" class="block text-sm font-medium">Description</label>
        <textarea id="description" name="description" rows="3" class="mt-1 w-full rounded border border-ink-100 px-3 py-2">{{ old('description', $item->description) }}</textarea>@error('description')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    <div class="mb-4"><label for="image" class="block text-sm font-medium">Photo (JPG, PNG or WebP, up to 2 MB)</label>
        <input id="image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full">@error('image')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    @unless ($item->exists)
    <div class="mb-4"><label for="default_price" class="block text-sm font-medium">Starting price for all branches (optional)</label>
        <input id="default_price" name="default_price" inputmode="decimal" value="{{ old('default_price') }}" class="mt-1 w-full rounded border border-ink-100 px-3 py-2">@error('default_price')<p class="text-sm text-red-700">Enter a price like 1500 or 1500.50.</p>@enderror</div>
    @endunless
    <label class="mb-4 flex items-center gap-2"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active))> Active</label>
    <button class="btn btn-primary">Save</button>
</form>
@if ($item->exists)
<form method="POST" action="{{ route('menu.items.destroy', $item) }}" class="mt-6" onsubmit="return confirm('Delete this item? Past orders are not affected.')">@csrf @method('DELETE')
    <button class="text-sm text-red-700 underline">Delete item</button></form>
@endif
@endsection
