@extends('layouts.app')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Branches</h1><p class="mt-1 text-sm text-ink-500">Manage each restaurant location, menu, QR code and operating status.</p></div>
    @can('create', [App\Domain\Tenancy\Models\Branch::class, request()->attributes->get('business')])
        <a href="{{ route('branches.create') }}" class="rounded btn btn-primary">Add branch</a>
    @endcan
</div>
<div class="card overflow-x-auto"><table class="w-full min-w-[680px] text-left text-sm">
    <thead class="bg-ink-50 text-xs uppercase text-ink-500"><tr><th class="p-3">Branch</th><th class="p-3">Address</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
    <tbody>
    @foreach ($branches as $branch)
        <tr class="border-t border-ink-100 align-middle">
            <td class="p-3"><div class="flex items-center gap-3"><img src="{{ $branch->photo_path ? asset('storage/'.$branch->photo_path) : asset('images/stock/branch-'.(($branch->id % 2) + 1).'.jpg') }}" alt="" class="h-12 w-12 rounded-lg object-cover"><div><p class="font-semibold">{{ $branch->name }}</p><p class="text-xs text-ink-500">{{ $branch->slug }}</p></div></div></td>
            <td class="p-3 text-ink-600">{{ $branch->address ?: $branch->city ?: 'Address not set' }}@if($branch->city && $branch->address)<span class="block text-xs text-ink-500">{{ $branch->city }}</span>@endif</td>
            <td class="p-3"><x-status :value="$branch->is_active ? 'active' : 'disabled'" /></td>
            <td class="p-3"><div class="flex flex-wrap items-center gap-2">
                @can('managePricing', $branch)<a class="btn btn-outline !px-3 !py-1.5 text-xs" href="{{ route('branch.menu.edit', $branch) }}">Menu</a><a class="btn btn-outline !px-3 !py-1.5 text-xs" href="{{ route('branch.qr', $branch) }}">QR</a>@endcan
                @can('update', $branch)<a class="btn btn-outline !px-3 !py-1.5 text-xs" href="{{ route('branches.edit', $branch) }}">Edit</a>@endcan
                @can('toggleActive', $branch)
                    <form method="POST" action="{{ route('branches.toggle', $branch) }}" onsubmit="return confirm('Change branch status?')">@csrf
                        <button class="text-xs font-semibold text-ink-600 underline underline-offset-2">{{ $branch->is_active ? 'Deactivate' : 'Activate' }}</button>
                    </form>
                @endcan
            </div></td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endsection
