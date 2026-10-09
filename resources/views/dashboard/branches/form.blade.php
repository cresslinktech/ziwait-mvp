@extends('layouts.app')
@section('content')
<h1 class="mb-6 text-2xl font-bold">{{ $branch->exists ? 'Edit branch' : 'New branch' }}</h1>
<form method="POST" action="{{ $branch->exists ? route('branches.update', $branch) : route('branches.store') }}" enctype="multipart/form-data" class="max-w-2xl">
    @csrf
    @if ($branch->exists) @method('PUT') @endif
    <section class="mb-6 flex flex-col gap-4 rounded-xl border border-ink-100 bg-white p-4 sm:flex-row sm:items-center" aria-labelledby="branch-photo-heading">
        <img id="branch-photo-preview" src="{{ $branch->photo_path ? asset('storage/'.$branch->photo_path) : asset('images/stock/branch-1.jpg') }}" alt="{{ $branch->name ?: 'Branch' }} photo preview" class="h-28 w-full rounded-lg object-cover sm:w-40">
        <div class="min-w-0 flex-1">
            <h2 id="branch-photo-heading" class="text-sm font-bold">Branch photo</h2>
            <p class="mt-1 text-xs text-ink-500">Use a clear photo of this restaurant location. JPG, PNG or WebP, up to 5 MB.</p>
            <input id="branch-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-3 block w-full text-xs text-ink-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-800 hover:file:bg-brand-100">
            @error('photo')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    </section>
    @foreach (['name' => 'Name', 'slug' => 'URL slug', 'address' => 'Address', 'city' => 'City', 'phone' => 'Phone', 'email' => 'Email', 'timezone' => 'Timezone'] as $field => $label)
        <div class="mb-4">
            <label for="{{ $field }}" class="block text-sm font-medium">{{ $label }}</label>
            <input id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $branch->$field) }}" class="mt-1 w-full rounded border border-ink-100 px-3 py-2">
            @error($field)<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
        </div>
    @endforeach
    <button class="btn btn-primary">Save</button>
</form>
<script>
    const branchPhotoInput = document.getElementById('branch-photo');
    const branchPhotoPreview = document.getElementById('branch-photo-preview');
    if (branchPhotoInput && branchPhotoPreview) {
        branchPhotoInput.addEventListener('change', () => {
            if (branchPhotoInput.files[0]) branchPhotoPreview.src = URL.createObjectURL(branchPhotoInput.files[0]);
        });
    }
</script>
@endsection
