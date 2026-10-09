@extends('layouts.app')
@section('content')
<header class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div><h1 class="text-2xl font-bold">Menu</h1><p class="mt-1 text-sm text-ink-500">Manage dishes and branch prices from one place.</p></div>
    @if ($canManage)<a href="{{ route('menu.items.create') }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add item</a>@endif
</header>
@error('category')<p role="alert" class="mb-4 text-sm text-red-700">{{ $message }}</p>@enderror

@if ($branches->isNotEmpty())
    <section class="mb-6 border-y border-ink-100 py-4" aria-labelledby="branch-prices-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div><h2 id="branch-prices-heading" class="text-sm font-bold">Branch pricing</h2><p class="mt-0.5 text-xs text-ink-500">Set item prices and availability separately for each location.</p></div>
            <div class="flex flex-wrap gap-2">
                @foreach ($branches as $branch)
                    <a href="{{ route('branch.menu.edit', $branch) }}" class="btn btn-outline !px-3 !py-2 text-xs"><x-icon name="card" class="h-4 w-4" /> {{ $branch->name }}</a>
                @endforeach
            </div>
        </div>
    </section>
@endif

@if ($canManage)
<form method="POST" action="{{ route('menu.categories.store') }}" class="mb-6 flex max-w-xl gap-2">@csrf
    <label class="sr-only" for="cat-name">New category name</label>
    <input id="cat-name" name="name" required maxlength="100" placeholder="Add a category, e.g. Breakfast" class="min-w-0 flex-1 rounded-lg border border-ink-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none">
    <button class="btn btn-outline !py-2 text-sm"><x-icon name="plus" class="h-4 w-4" /> Add category</button>
</form>
@endif

@forelse ($categories as $category)
    <section class="mb-7" aria-labelledby="category-{{ $category->id }}">
        <div class="mb-2 flex items-center justify-between border-b border-ink-100 pb-2">
            <h2 id="category-{{ $category->id }}" class="font-semibold">{{ $category->name }} <span class="ml-1 text-xs font-normal text-ink-500">{{ $category->items->count() }} {{ Str::plural('item', $category->items->count()) }}</span> @unless($category->is_active)<span class="text-xs text-ink-500">(hidden)</span>@endunless</h2>
            @if ($canManage)
            <form method="POST" action="{{ route('menu.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category?')">@csrf @method('DELETE')
                <button class="text-xs font-semibold text-ink-500 underline underline-offset-2">Delete empty category</button>
            </form>
            @endif
        </div>
        <ul class="divide-y divide-ink-100">
            @forelse ($category->items as $item)
                @php
                    $pricedBranches = $item->branchItems;
                    $priceCount = $pricedBranches->count();
                    $minPrice = $pricedBranches->min('price_minor');
                    $maxPrice = $pricedBranches->max('price_minor');
                    $fallback = match (strtolower($category->name)) {
                        'appetizers', 'sides' => 'food-appetizers.jpg',
                        'drinks' => 'food-drinks.jpg',
                        'desserts' => 'food-desserts.jpg',
                        default => 'food-mains.jpg',
                    };
                @endphp
                <li class="flex items-center gap-3 py-3">
                    <img src="{{ $item->image_path ? asset('storage/'.$item->image_path) : asset('images/stock/'.$fallback) }}" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">{{ $item->name }} @unless($item->is_active)<span class="font-normal text-ink-500">(inactive)</span>@endunless</p>
                        <p class="mt-0.5 truncate text-xs text-ink-500">{{ $item->description ?: $category->name }}</p>
                    </div>
                    <div class="hidden shrink-0 text-right sm:block">
                        @if ($priceCount)
                            <p class="text-xs font-bold text-ink-800">{{ App\Support\MoneyFormat::display($minPrice, request()->attributes->get('business')->currency) }}@if($maxPrice !== $minPrice)–{{ App\Support\MoneyFormat::display($maxPrice, request()->attributes->get('business')->currency) }}@endif</p>
                            <p class="text-[10px] text-ink-500">{{ $priceCount }} of {{ $branches->count() }} branches priced</p>
                        @else
                            <p class="text-xs font-semibold text-amber-700">No branch price</p>
                        @endif
                    </div>
                    @if ($canManage)<a class="btn btn-outline !px-3 !py-1.5 text-xs" href="{{ route('menu.items.edit', $item) }}">Edit</a>@endif
                </li>
            @empty
                <li class="py-3 text-sm text-ink-500">No items yet.</li>
            @endforelse
        </ul>
    </section>
@empty
    <div class="border-y border-ink-100 py-12 text-center"><x-icon name="utensils" class="mx-auto h-8 w-8 text-ink-300" /><p class="mt-3 text-sm font-semibold">No categories yet</p><p class="mt-1 text-xs text-ink-500">Add a category to start building the restaurant menu.</p></div>
@endforelse
@endsection
