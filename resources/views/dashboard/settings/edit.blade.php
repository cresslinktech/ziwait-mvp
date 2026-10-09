@extends('layouts.app', ['title' => 'Settings'])
@section('content')
<h1 class="mb-4 text-2xl font-extrabold">Business settings</h1>
<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="card max-w-xl space-y-4 p-6">@csrf @method('PUT')
    @php $f = 'mt-1 w-full rounded-lg border border-ink-100 px-3 py-2 text-sm'; @endphp
    <div><label for="name" class="text-sm font-medium">Business name</label><input id="name" name="name" required maxlength="120" value="{{ old('name', $business->name) }}" class="{{ $f }}">@error('name')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    <div><label for="business_type" class="text-sm font-medium">Business type</label><select id="business_type" name="business_type" class="{{ $f }}">
        @foreach (App\Domain\Tenancy\Models\Business::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('business_type', $business->business_type) === $v)>{{ $l }}</option>@endforeach</select>@error('business_type')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    <div><label for="description" class="text-sm font-medium">Description</label><textarea id="description" name="description" rows="3" maxlength="500" class="{{ $f }}">{{ old('description', $business->description) }}</textarea>@error('description')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    <div><label for="logo" class="text-sm font-medium">Logo</label>@if($business->logoUrl())<img src="{{ $business->logoUrl() }}" alt="Current logo" class="my-2 h-16 w-16 rounded-full object-cover">@endif
        <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="{{ $f }}">@error('logo')<p class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
    <button class="btn btn-primary">Save changes</button>
</form>
@endsection
