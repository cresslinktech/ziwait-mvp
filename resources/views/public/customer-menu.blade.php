<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $business->name }} · {{ $branch->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .screen-view { display: none; }
        .screen-view.active { display: block; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="min-h-screen bg-ink-950/5 md:bg-ink-100 font-sans text-ink-900 antialiased flex flex-col items-center" data-has-errors="{{ $errors->any() ? '1' : '0' }}">

<form method="POST" action="{{ route('menu.order', $link->token) }}" id="order-form" class="w-full max-w-[430px] min-h-screen bg-white shadow-2xl relative pb-20 flex flex-col justify-between" novalidate>
    @csrf

    @php
        $heroImage = $branch->photo_path
            ? asset('storage/'.$branch->photo_path)
            : ($business->cover_path ? asset('storage/'.$business->cover_path) : asset('images/stock/cta-kitchen.jpg'));
        $logoImage = $business->logo_path
            ? asset('storage/'.$business->logo_path)
            : null;
        $allOffers = $groups->flatten(1);
        $featured = $allOffers->take(4);
    @endphp

    <!-- Top Sticky Mobile Header -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-ink-100/80 px-4 py-3 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <a href="#home" onclick="showScreen('home')" class="flex items-center gap-2">
                @if ($logoImage)
                    <img src="{{ $logoImage }}" alt="{{ $business->name }}" class="h-7 w-7 rounded-full object-cover border border-brand-500/30">
                @else
                    <div class="h-7 w-7 rounded-lg bg-brand-500 flex items-center justify-center font-black text-sm text-white shadow-xs">Z</div>
                @endif
                <span class="text-sm font-black tracking-tight text-ink-900">ZIWAIT</span>
            </a>
        </div>
        <div class="flex items-center gap-2.5 text-ink-600">
            <button type="button" onclick="navigator.share ? navigator.share({title: '{{ addslashes($business->name) }}', url: window.location.href}) : alert('Menu URL copied to clipboard!')" class="p-1.5 rounded-full hover:bg-ink-50 transition" aria-label="Share">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
            </button>
            <button type="button" class="p-1.5 rounded-full hover:bg-ink-50 transition" aria-label="Bookmark">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
            </button>
        </div>
    </header>

    @if (! $branch->is_active)
        <div class="mx-4 mt-3 rounded-xl bg-amber-50 p-3 text-xs font-semibold text-amber-900 border border-amber-200">
            This branch is currently not accepting orders.
        </div>
    @endif

    @if ($errors->any())
        <div role="alert" class="mx-4 mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800">
            <ul class="list-disc pl-4 space-y-1">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <!-- Hidden Qty Inputs for all real menu items from Database -->
    <div id="hidden-qty-container">
        @foreach ($allOffers as $offer)
            <input type="hidden" data-qty name="qty[{{ $offer->menuItem->id }}]" id="qty-input-{{ $offer->menuItem->id }}" value="{{ old('qty.'.$offer->menuItem->id, 0) }}">
        @endforeach
    </div>

    <div class="flex-1 overflow-y-auto">

        <!-- ==================== SCREEN 1: HOME ==================== -->
        <div class="screen-view active" id="screen-home">
            <!-- Hero Cover Header with Restaurant Images uploaded during Onboarding -->
            <div class="relative h-52 bg-ink-950 overflow-hidden">
                <img src="{{ $heroImage }}" alt="{{ $business->name }}" class="w-full h-full object-cover filter brightness-[0.7]">
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>
                <div class="absolute bottom-4 left-4 right-4 text-white">
                    <h1 class="text-2xl font-black tracking-tight drop-shadow-sm">{{ $business->name }}</h1>
                    <p class="text-xs text-white/90 font-medium mt-0.5">{{ $business->business_type ?: 'Restaurant' }} • {{ $branch->city ?: 'Lagos' }}</p>
                    <div class="mt-2.5 inline-flex items-center gap-1.5 px-3 py-1 rounded-full {{ $branch->is_active ? 'bg-brand-500/90' : 'bg-ink-600/90' }} backdrop-blur-xs text-[11px] font-bold text-white shadow-xs">
                        <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                        {{ $branch->is_active ? 'Open for orders' : 'Currently Closed' }}
                    </div>
                </div>
            </div>

            <!-- Restaurant Quick Details -->
            <div class="p-4 border-b border-ink-100/80 bg-white space-y-2 text-xs">
                <div class="flex items-center justify-between text-ink-700">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-brand-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="font-medium truncate max-w-[200px]">{{ $branch->address ?: ($branch->city ?: 'Lagos, Nigeria') }}</span>
                    </div>
                    @if ($branch->phone)
                        <a href="tel:{{ $branch->phone }}" class="flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-700">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            Call
                        </a>
                    @endif
                </div>
            </div>

            @foreach ($advertisements->get('home_banner', collect()) as $advertisement)
                <aside class="p-4" aria-label="{{ $advertisement->label }}">
                    <div class="relative min-h-36 overflow-hidden rounded-2xl bg-ink-900 text-white">
                        <img src="{{ asset('storage/'.$advertisement->image_path) }}" alt="{{ $advertisement->title }}" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/10"></div>
                        <div class="relative max-w-[76%] space-y-2 p-4">
                            <span class="inline-flex rounded bg-black/45 px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider text-white">{{ $advertisement->label }}</span>
                            <h2 class="text-sm font-extrabold leading-tight">{{ $advertisement->title }}</h2>
                            @if ($advertisement->body)<p class="text-[11px] leading-relaxed text-white/85">{{ $advertisement->body }}</p>@endif
                            @if ($advertisement->target_url && $advertisement->cta_label)<a href="{{ $advertisement->target_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-full bg-white px-3 py-1.5 text-[10px] font-bold text-ink-900">{{ $advertisement->cta_label }}</a>@endif
                        </div>
                    </div>
                </aside>
            @endforeach

            <!-- Menu Categories Icons Grid -->
            <div class="px-4 py-2">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-extrabold text-ink-900">Menu Categories</h3>
                    <button type="button" onclick="showScreen('menu')" class="text-xs font-semibold text-brand-600 hover:text-brand-700">View menu</button>
                </div>
                <div class="flex gap-3 overflow-x-auto hide-scrollbar pb-2">
                    <button type="button" onclick="filterCategory('all')" class="flex flex-col items-center gap-1.5 shrink-0 group">
                        <div class="w-14 h-14 rounded-2xl bg-brand-50 border border-brand-200 flex items-center justify-center text-brand-600 group-hover:bg-brand-500 group-hover:text-white transition shadow-2xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                        </div>
                        <span class="text-[11px] font-bold text-ink-800">All Items</span>
                    </button>
                    @foreach ($groups as $category => $offers)
                        @php
                            $iconPath = match($category) {
                                'Appetizers' => 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
                                'Main Courses' => 'M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z',
                                'Drinks' => 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
                                'Desserts' => 'M21 15.546c-.523 0-1.046.151-1.5.454a2.7 2.7 0 01-3 0 2.7 2.7 0 00-3 0 2.7 2.7 0 01-3 0 2.7 2.7 0 00-3 0 2.7 2.7 0 01-1.5-.454M3 6h18M3 10h18M3 14h18',
                                default => 'M12 6v6m0 0v6m0-6h6m-6 0H6'
                            };
                        @endphp
                        <button type="button" onclick="filterCategory('{{ $category }}')" class="flex flex-col items-center gap-1.5 shrink-0 group">
                            <div class="w-14 h-14 rounded-2xl bg-ink-50 border border-ink-100 flex items-center justify-center text-ink-600 group-hover:bg-brand-500 group-hover:text-white transition shadow-2xs">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $iconPath }}"/></svg>
                            </div>
                            <span class="text-[11px] font-bold text-ink-800 truncate max-w-[64px] text-center">{{ $category }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Real Menu Featured Section -->
            @if ($featured->isNotEmpty())
                <div class="px-4 py-4 border-t border-ink-100/80 mt-2">
                    <h3 class="text-sm font-extrabold text-ink-900 mb-3">Featured Dishes</h3>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($featured as $offer)
                            @php
                                $item = $offer->menuItem;
                                $fallback = match ($item->category->name ?? '') {
                                    'Appetizers' => 'food-appetizers.jpg',
                                    'Drinks' => 'food-drinks.jpg',
                                    'Desserts' => 'food-desserts.jpg',
                                    default => 'food-mains.jpg',
                                };
                                $img = $item->image_path ? asset('storage/'.$item->image_path) : asset('images/stock/'.$fallback);
                            @endphp
                            <div class="rounded-xl border border-ink-100 bg-white overflow-hidden shadow-2xs hover:shadow-xs transition cursor-pointer" onclick="openItemModal({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}', {{ $offer->price_minor }}, '{{ addslashes($item->description ?? '') }}', '{{ $img }}', {{ $offer->is_available ? 'true' : 'false' }})">
                                <div class="h-28 bg-ink-100 relative">
                                    <img src="{{ $img }}" alt="{{ $item->name }}" class="w-full h-full object-cover">
                                    <span class="absolute top-2 right-2 px-2 py-0.5 {{ $offer->is_available ? 'bg-brand-500 text-white' : 'bg-ink-600 text-white' }} font-bold text-[10px] rounded-full shadow-2xs">
                                        {{ $offer->is_available ? 'Available' : 'Sold out' }}
                                    </span>
                                </div>
                                <div class="p-3">
                                    <h4 class="text-xs font-bold text-ink-900 truncate">{{ $item->name }}</h4>
                                    <p class="text-xs font-black text-brand-600 mt-1">{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <!-- ==================== SCREEN 2: MENU LISTING ==================== -->
        <div class="screen-view" id="screen-menu">
            <div class="p-4 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-black text-ink-900" id="current-category-title">All Items</h2>
                    <div class="relative flex-1 max-w-[180px] ml-3">
                        <input type="text" id="search-input" onkeyup="searchMenuItems(this.value)" placeholder="Search menu..." class="w-full pl-8 pr-3 py-1.5 text-xs bg-ink-50 border border-ink-200 rounded-full focus:outline-none focus:border-brand-500">
                        <svg class="w-4 h-4 text-ink-400 absolute left-2.5 top-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                </div>

                <!-- Category Filter Pills -->
                <div class="flex gap-2 overflow-x-auto hide-scrollbar pb-1">
                    <button type="button" onclick="filterCategory('all')" class="category-pill active px-4 py-1.5 bg-brand-500 text-white text-xs font-bold rounded-full transition shrink-0" data-category="all">All</button>
                    @foreach ($groups as $category => $offers)
                        <button type="button" onclick="filterCategory('{{ $category }}')" class="category-pill px-4 py-1.5 bg-ink-100 text-ink-700 text-xs font-bold rounded-full hover:bg-brand-50 transition shrink-0" data-category="{{ $category }}">{{ $category }}</button>
                    @endforeach
                </div>

                <!-- Menu Items List from Database -->
                <div class="space-y-3.5 pt-2" id="menu-items-container">
                    @forelse ($groups as $category => $offers)
                        @foreach ($offers as $offer)
                            @php
                                $item = $offer->menuItem;
                                $fallback = match ($category) {
                                    'Appetizers' => 'food-appetizers.jpg',
                                    'Drinks' => 'food-drinks.jpg',
                                    'Desserts' => 'food-desserts.jpg',
                                    default => 'food-mains.jpg',
                                };
                                $image = $item->image_path ? asset('storage/'.$item->image_path) : asset('images/stock/'.$fallback);
                            @endphp
                            <div class="menu-item-row p-3 rounded-2xl border border-ink-100 bg-white flex items-center gap-3 shadow-2xs hover:border-brand-300 transition" data-category="{{ $category }}" data-item-name="{{ strtolower($item->name) }}">
                                <img src="{{ $image }}" alt="{{ $item->name }}" class="w-20 h-20 rounded-xl object-cover shrink-0 cursor-pointer" onclick="openItemModal({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}', {{ $offer->price_minor }}, '{{ addslashes($item->description ?? '') }}', '{{ $image }}', {{ $offer->is_available ? 'true' : 'false' }})">
                                <div class="flex-1 min-w-0 cursor-pointer" onclick="openItemModal({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}', {{ $offer->price_minor }}, '{{ addslashes($item->description ?? '') }}', '{{ $image }}', {{ $offer->is_available ? 'true' : 'false' }})">
                                    <h4 class="text-xs font-bold text-ink-900 truncate">{{ $item->name }}</h4>
                                    <p class="text-[11px] text-ink-500 line-clamp-2 mt-0.5 leading-relaxed">{{ $item->description ?: 'Made fresh for you.' }}</p>
                                    <div class="flex items-center gap-2 mt-1.5">
                                        <span class="text-xs font-black text-brand-600">{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}</span>
                                        <span class="px-2 py-0.5 {{ $offer->is_available ? 'bg-emerald-50 text-emerald-700' : 'bg-ink-100 text-ink-500' }} font-bold text-[9px] rounded-full">
                                            {{ $offer->is_available ? 'Available' : 'Sold out' }}
                                        </span>
                                    </div>
                                </div>
                                @if ($offer->is_available)
                                    <button type="button" onclick="addToCart({{ $item->id }}, '{{ addslashes($item->name) }}', {{ $offer->price_minor }}, '{{ $image }}')" class="w-8 h-8 rounded-full bg-brand-500 hover:bg-brand-600 text-white font-bold flex items-center justify-center text-lg shrink-0 shadow-xs transition">
                                        +
                                    </button>
                                @else
                                    <span class="text-[10px] font-bold text-ink-400">N/A</span>
                                @endif
                            </div>
                        @endforeach
                    @empty
                        <p class="text-xs text-ink-400 text-center py-10">No menu items available right now.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ==================== SCREEN 3: ITEM DETAIL MODAL ==================== -->
        <div class="screen-view" id="screen-item">
            <div class="relative bg-white pb-6">
                <!-- Top Cover Image with Floating Back & Heart Buttons -->
                <div class="relative h-64 bg-ink-900">
                    <img id="modal-item-img" src="{{ asset('images/stock/food-mains.jpg') }}" alt="Dish" class="w-full h-full object-cover">
                    <button type="button" onclick="showScreen('menu')" class="absolute top-4 left-4 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md text-ink-900 flex items-center justify-center shadow-md hover:bg-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button type="button" class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/90 backdrop-blur-md text-ink-900 flex items-center justify-center shadow-md hover:bg-white transition">
                        <svg class="w-5 h-5 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"/></svg>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-black text-ink-900" id="modal-item-name">Menu Item</h2>
                            <p class="text-lg font-black text-brand-600 mt-1" id="modal-item-price">₦0</p>
                        </div>
                        <span class="px-3 py-1 bg-emerald-50 text-emerald-700 font-bold text-xs rounded-full border border-emerald-200" id="modal-item-status">Available</span>
                    </div>

                    <p class="text-xs text-ink-600 leading-relaxed" id="modal-item-desc">
                        Made fresh for you.
                    </p>

                    <!-- Quantity Counter & Add Button -->
                    <div class="pt-4 space-y-3">
                        <div class="flex items-center justify-center gap-4 p-2 bg-ink-50 rounded-xl border border-ink-100 max-w-[160px] mx-auto">
                            <button type="button" onclick="updateModalQty(-1)" class="w-8 h-8 rounded-lg bg-white text-ink-800 font-extrabold text-base flex items-center justify-center border border-ink-200 shadow-2xs">-</button>
                            <span class="font-extrabold text-sm text-ink-900" id="modal-qty">1</span>
                            <button type="button" onclick="updateModalQty(1)" class="w-8 h-8 rounded-lg bg-white text-ink-800 font-extrabold text-base flex items-center justify-center border border-ink-200 shadow-2xs">+</button>
                        </div>

                        <button type="button" id="modal-add-btn" class="w-full py-3.5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm rounded-xl shadow-md shadow-brand-500/20 transition flex items-center justify-center gap-2">
                            Add to Cart • <span id="modal-total-btn-price">₦0</span>
                        </button>
                    </div>

                    @foreach ($advertisements->get('meal_detail', collect()) as $advertisement)
                        <aside class="pt-4" aria-label="{{ $advertisement->label }}">
                            <div class="relative min-h-32 overflow-hidden rounded-2xl bg-ink-900 text-white">
                                <img src="{{ asset('storage/'.$advertisement->image_path) }}" alt="{{ $advertisement->title }}" class="absolute inset-0 h-full w-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/10"></div>
                                <div class="relative max-w-[76%] space-y-1.5 p-4">
                                    <span class="inline-flex rounded bg-black/45 px-2 py-0.5 text-[8px] font-bold uppercase tracking-wider text-white">{{ $advertisement->label }}</span>
                                    <h3 class="text-xs font-extrabold">{{ $advertisement->title }}</h3>
                                    @if ($advertisement->body)<p class="text-[10px] leading-relaxed text-white/85">{{ $advertisement->body }}</p>@endif
                                    @if ($advertisement->target_url && $advertisement->cta_label)<a href="{{ $advertisement->target_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-full bg-white px-3 py-1 text-[10px] font-bold text-ink-900">{{ $advertisement->cta_label }}</a>@endif
                                </div>
                            </div>
                        </aside>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- ==================== SCREEN 4: YOUR CART ==================== -->
        <div class="screen-view" id="screen-cart">
            <div class="p-4 space-y-5">
                <div class="flex items-center gap-3 border-b border-ink-100 pb-3">
                    <button type="button" onclick="showScreen('menu')" class="p-1 rounded-full text-ink-600 hover:bg-ink-50">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <h2 class="text-xl font-black text-ink-900">Your Cart</h2>
                </div>

                <!-- Cart Items List -->
                <div class="space-y-3" id="cart-items-list">
                    <p class="text-xs text-ink-400 text-center py-8">Your cart is empty. Browse the menu to add items.</p>
                </div>

                <!-- Customer Order Details -->
                <div class="space-y-3 pt-2">
                    <div>
                        <label for="table_number" class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Table Number <span class="text-red-500">*</span></label>
                        <input type="text" name="table_number" id="table_number" value="{{ old('table_number') }}" placeholder="e.g. 12 or Table A" required class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none transition">
                    </div>

                    <div>
                        <label for="notes" class="block text-xs font-semibold text-ink-700 uppercase tracking-wider mb-1">Special Instructions <span class="font-normal text-ink-400">(optional)</span></label>
                        <textarea name="notes" id="notes" rows="2" placeholder="e.g. No onions, extra spicy..." class="w-full rounded-xl border border-ink-200 bg-white px-3.5 py-2.5 text-sm text-ink-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-100 focus:outline-none transition">{{ old('notes') }}</textarea>
                    </div>
                </div>

                <!-- Cost Summary -->
                <div class="p-4 rounded-2xl bg-ink-50 border border-ink-100 space-y-2.5 text-xs">
                    <div class="flex justify-between text-ink-600">
                        <span>Subtotal</span>
                        <span class="font-bold text-ink-900" id="cart-subtotal-text">₦0</span>
                    </div>
                    <div class="flex justify-between text-ink-600">
                        <span>Delivery/Service Charge</span>
                        <span class="font-bold text-emerald-600">₦0</span>
                    </div>
                    <hr class="border-ink-200">
                    <div class="flex justify-between text-base font-black text-ink-900 pt-1">
                        <span>Total</span>
                        <span class="text-brand-600" id="cart-total-text">₦0</span>
                    </div>
                </div>

                <!-- Form Submit Button to POST /m/{token}/order -->
                <button type="submit" id="submit-order-btn" class="w-full py-3.5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm rounded-xl shadow-lg shadow-brand-500/30 transition flex items-center justify-center gap-2">
                    Proceed to Order
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7-7 7M3 12h18"/></svg>
                </button>
            </div>
        </div>

        <!-- ==================== SCREEN 5: SESSION ORDERS ==================== -->
        <div class="screen-view" id="screen-confirmation">
            <div class="p-4 space-y-4">
                <div class="flex items-center justify-between border-b border-ink-100 pb-3">
                    <div><p class="text-[10px] font-bold uppercase tracking-wider text-brand-700">This table session</p><h2 class="text-xl font-black text-ink-900">Your Orders</h2></div>
                    <button type="button" onclick="refreshSessionOrders()" class="text-xs font-semibold text-brand-700">Refresh status</button>
                </div>
                <p class="text-xs leading-relaxed text-ink-500">Orders placed from this browser appear here until the restaurant serves them.</p>
                <div id="session-orders-list" data-orders-url="{{ route('menu.orders', $link->token) }}" aria-live="polite" class="space-y-3">
                    @forelse ($sessionOrders as $sessionOrder)
                        <article class="rounded-xl border border-ink-100 bg-white p-4 shadow-2xs" data-tracked-order="{{ $sessionOrder->reference }}">
                            <div class="flex items-start justify-between gap-3">
                                <div><p class="text-[9px] font-bold uppercase tracking-wider text-ink-400">Tracking ID</p><p class="font-mono text-sm font-black text-ink-900">{{ $sessionOrder->reference }}</p></div>
                                <span class="rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-bold text-brand-800">{{ $sessionOrder->status->value === 'completed' ? 'Served' : $sessionOrder->status->label() }}</span>
                            </div>
                            <p class="mt-2 text-xs font-semibold text-ink-700">Table {{ $sessionOrder->table_number }} <span class="font-normal text-ink-400">· {{ $sessionOrder->placed_at->diffForHumans() }}</span></p>
                            <ul class="mt-3 space-y-1 border-t border-ink-100 pt-2 text-xs text-ink-600">
                                @foreach ($sessionOrder->items as $orderedItem)<li>{{ $orderedItem->quantity }} × {{ $orderedItem->name }}</li>@endforeach
                            </ul>
                            <p class="mt-3 flex justify-between border-t border-ink-100 pt-2 text-xs"><span class="text-ink-500">Total</span><strong>{{ App\Support\MoneyFormat::display($sessionOrder->total_minor, $sessionOrder->currency) }}</strong></p>
                        </article>
                    @empty
                        <div id="session-orders-empty" class="rounded-xl border border-dashed border-ink-200 px-5 py-10 text-center">
                            <p class="text-sm font-bold text-ink-700">No orders in this session yet</p>
                            <p class="mt-1 text-xs text-ink-500">Place an order and its table number and tracking ID will appear here.</p>
                            <button type="button" onclick="showScreen('menu')" class="mt-4 text-xs font-bold text-brand-700 underline underline-offset-2">Browse menu</button>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- ==================== SCREEN 6: MORE / DISCOVER LANDING ==================== -->
        <div class="screen-view" id="screen-more">
            <div class="p-4 space-y-4">
                <!-- Top Header Restaurant Info -->
                <div class="flex items-center gap-3 p-3 rounded-2xl bg-ink-50 border border-ink-100">
                    <img src="{{ $heroImage }}" class="w-12 h-12 rounded-xl object-cover">
                    <div>
                        <h3 class="text-sm font-bold text-ink-900">{{ $business->name }}</h3>
                        <p class="text-[11px] text-ink-500">{{ $business->business_type ?: 'Restaurant' }} • {{ $branch->city ?: 'Lagos' }}</p>
                    </div>
                </div>

                @foreach ($advertisements->get('discover_primary', collect()) as $advertisement)
                    <aside class="relative min-h-32 overflow-hidden rounded-2xl bg-ink-900 text-white" aria-label="{{ $advertisement->label }}">
                        <img src="{{ asset('storage/'.$advertisement->image_path) }}" alt="{{ $advertisement->title }}" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/10"></div>
                        <div class="relative max-w-[76%] space-y-1.5 p-4">
                            <span class="inline-flex rounded bg-black/45 px-2 py-0.5 text-[8px] font-bold uppercase tracking-wider text-white">{{ $advertisement->label }}</span>
                            <h2 class="text-xs font-extrabold">{{ $advertisement->title }}</h2>
                            @if ($advertisement->body)<p class="text-[10px] text-white/85">{{ $advertisement->body }}</p>@endif
                            @if ($advertisement->target_url && $advertisement->cta_label)<a href="{{ $advertisement->target_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-full bg-white px-3 py-1 text-[10px] font-bold text-ink-900">{{ $advertisement->cta_label }}</a>@endif
                        </div>
                    </aside>
                @endforeach

                <!-- Real menu recommendations -->
                <div class="space-y-2.5 pt-2">
                    <h3 class="text-sm font-extrabold text-ink-900">You Might Also Like</h3>
                    <div class="grid grid-cols-2 gap-3">
                        @foreach ($featured as $offer)
                            @php $item = $offer->menuItem; $fallback = match ($item->category->name ?? '') { 'Drinks' => 'food-drinks.jpg', 'Desserts' => 'food-desserts.jpg', 'Appetizers', 'Sides' => 'food-appetizers.jpg', default => 'food-mains.jpg' }; $image = $item->image_path ? asset('storage/'.$item->image_path) : asset('images/stock/'.$fallback); @endphp
                            <button type="button" onclick="openItemModal({{ $item->id }}, '{{ addslashes($item->name) }}', '{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}', {{ $offer->price_minor }}, '{{ addslashes($item->description ?? '') }}', '{{ $image }}', {{ $offer->is_available ? 'true' : 'false' }})" class="overflow-hidden rounded-xl border border-ink-100 bg-white text-left">
                                <img src="{{ $image }}" alt="{{ $item->name }}" class="h-24 w-full object-cover">
                                <span class="block p-2.5"><strong class="block truncate text-xs">{{ $item->name }}</strong><span class="mt-1 block text-[10px] font-bold text-brand-700">{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}</span></span>
                            </button>
                        @endforeach
                    </div>
                </div>

                @foreach ($advertisements->get('discover_secondary', collect()) as $advertisement)
                    <aside class="relative min-h-28 overflow-hidden rounded-2xl bg-ink-900 text-white" aria-label="{{ $advertisement->label }}">
                        <img src="{{ asset('storage/'.$advertisement->image_path) }}" alt="{{ $advertisement->title }}" class="absolute inset-0 h-full w-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/55 to-black/10"></div>
                        <div class="relative max-w-[76%] space-y-1 p-4">
                            <span class="inline-flex rounded bg-black/45 px-2 py-0.5 text-[8px] font-bold uppercase tracking-wider text-white">{{ $advertisement->label }}</span>
                            <h2 class="text-xs font-extrabold">{{ $advertisement->title }}</h2>
                            @if ($advertisement->body)<p class="text-[10px] text-white/85">{{ $advertisement->body }}</p>@endif
                            @if ($advertisement->target_url && $advertisement->cta_label)<a href="{{ $advertisement->target_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-full bg-white px-3 py-1 text-[10px] font-bold text-ink-900">{{ $advertisement->cta_label }}</a>@endif
                        </div>
                    </aside>
                @endforeach
            </div>
        </div>

    </div>

    <!-- Fixed Bottom Navigation Bar -->
    <nav class="fixed bottom-0 z-40 w-full max-w-[430px] bg-white/95 backdrop-blur-md border-t border-ink-100 px-3 py-2">
        <div class="grid grid-cols-5 gap-1 text-center">
            <button type="button" onclick="showScreen('home')" class="nav-tab active flex flex-col items-center gap-0.5 py-1 text-brand-600" id="nav-home">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                <span class="text-[10px] font-bold">Home</span>
            </button>
            <button type="button" onclick="showScreen('menu')" class="nav-tab flex flex-col items-center gap-0.5 py-1 text-ink-400 hover:text-ink-700" id="nav-menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                <span class="text-[10px] font-bold">Menu</span>
            </button>
            <button type="button" onclick="showScreen('cart')" class="nav-tab relative flex flex-col items-center gap-0.5 py-1 text-ink-400 hover:text-ink-700" id="nav-cart">
                <div class="relative">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
                    <span id="cart-badge-count" class="absolute -top-1.5 -right-2.5 w-4 h-4 rounded-full bg-brand-500 text-white font-black text-[9px] flex items-center justify-center shadow-2xs">0</span>
                </div>
                <span class="text-[10px] font-bold">Cart</span>
            </button>
            <button type="button" onclick="showScreen('confirmation')" class="nav-tab flex flex-col items-center gap-0.5 py-1 text-ink-400 hover:text-ink-700" id="nav-confirmation">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span class="text-[10px] font-bold">Orders</span>
            </button>
            <button type="button" onclick="showScreen('more')" class="nav-tab flex flex-col items-center gap-0.5 py-1 text-ink-400 hover:text-ink-700" id="nav-more">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                <span class="text-[10px] font-bold">More</span>
            </button>
        </div>
    </nav>
</form>

<script>
    let activeModalItemId = null;
    let activeModalItemRawPrice = 0;
    let modalQuantity = 1;
    let activeScreenName = 'home';

    async function refreshSessionOrders() {
        const container = document.getElementById('session-orders-list');
        if (!container) return;

        try {
            const response = await fetch(container.dataset.ordersUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) return;
            const data = await response.json();
            container.replaceChildren();

            if (!data.orders.length) {
                const empty = document.createElement('div');
                empty.className = 'rounded-xl border border-dashed border-ink-200 px-5 py-10 text-center';
                empty.innerHTML = '<p class="text-sm font-bold text-ink-700">No orders in this session yet</p><p class="mt-1 text-xs text-ink-500">Place an order and its tracking ID will appear here.</p>';
                container.append(empty);
                return;
            }

            data.orders.forEach((order) => {
                const card = document.createElement('article');
                card.className = 'rounded-xl border border-ink-100 bg-white p-4 shadow-2xs';
                const heading = document.createElement('div');
                heading.className = 'flex items-start justify-between gap-3';
                const idGroup = document.createElement('div');
                const label = document.createElement('p');
                label.className = 'text-[9px] font-bold uppercase tracking-wider text-ink-400';
                label.textContent = 'Tracking ID';
                const reference = document.createElement('p');
                reference.className = 'font-mono text-sm font-black text-ink-900';
                reference.textContent = order.reference;
                idGroup.append(label, reference);
                const status = document.createElement('span');
                status.className = order.served ? 'rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-800' : 'rounded-full bg-brand-50 px-2.5 py-1 text-[10px] font-bold text-brand-800';
                status.textContent = order.status_label;
                heading.append(idGroup, status);

                const table = document.createElement('p');
                table.className = 'mt-2 text-xs font-semibold text-ink-700';
                table.textContent = `Table ${order.table_number} · ${order.placed_at}`;
                const items = document.createElement('ul');
                items.className = 'mt-3 space-y-1 border-t border-ink-100 pt-2 text-xs text-ink-600';
                order.items.forEach((item) => {
                    const line = document.createElement('li');
                    line.textContent = `${item.quantity} × ${item.name}`;
                    items.append(line);
                });
                const total = document.createElement('p');
                total.className = 'mt-3 flex justify-between border-t border-ink-100 pt-2 text-xs';
                const totalLabel = document.createElement('span');
                totalLabel.className = 'text-ink-500';
                totalLabel.textContent = 'Total';
                const amount = document.createElement('strong');
                amount.textContent = order.total;
                total.append(totalLabel, amount);
                card.append(heading, table, items, total);
                container.append(card);
            });
        } catch (error) {
            console.error('Could not refresh session orders.', error);
        }
    }

    function showScreen(name) {
        activeScreenName = name;
        document.querySelectorAll('.screen-view').forEach(el => el.classList.remove('active'));
        const target = document.getElementById(`screen-${name}`);
        if (target) target.classList.add('active');

        // Update nav styling
        document.querySelectorAll('.nav-tab').forEach(el => {
            el.classList.remove('text-brand-600', 'active');
            el.classList.add('text-ink-400');
        });
        const activeNav = document.getElementById(`nav-${name}`);
        if (activeNav) {
            activeNav.classList.add('text-brand-600', 'active');
            activeNav.classList.remove('text-ink-400');
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
        if (name === 'confirmation') refreshSessionOrders();
    }

    function filterCategory(cat) {
        document.getElementById('current-category-title').textContent = cat === 'all' ? 'All Items' : cat;

        document.querySelectorAll('.category-pill').forEach(btn => {
            if (btn.dataset.category === cat) {
                btn.className = 'category-pill active px-4 py-1.5 bg-brand-500 text-white text-xs font-bold rounded-full transition shrink-0';
            } else {
                btn.className = 'category-pill px-4 py-1.5 bg-ink-100 text-ink-700 text-xs font-bold rounded-full hover:bg-brand-50 transition shrink-0';
            }
        });

        document.querySelectorAll('.menu-item-row').forEach(row => {
            if (cat === 'all' || row.dataset.category === cat) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });

        showScreen('menu');
    }

    function searchMenuItems(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll('.menu-item-row').forEach(row => {
            const name = row.dataset.itemName || '';
            if (name.includes(q)) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function openItemModal(id, name, priceDisplay, priceMinor, desc, image, isAvailable) {
        activeModalItemId = id;
        activeModalItemRawPrice = priceMinor;
        modalQuantity = 1;

        document.getElementById('modal-item-img').src = image;
        document.getElementById('modal-item-name').textContent = name;
        document.getElementById('modal-item-price').textContent = priceDisplay;
        document.getElementById('modal-item-desc').textContent = desc || 'Made fresh for you.';
        document.getElementById('modal-item-status').textContent = isAvailable ? 'Available' : 'Sold out';
        document.getElementById('modal-qty').textContent = modalQuantity;
        document.getElementById('modal-total-btn-price').textContent = priceDisplay;

        const addBtn = document.getElementById('modal-add-btn');
        addBtn.disabled = !isAvailable;
        addBtn.onclick = function() {
            setQuantity(id, getQuantity(id) + modalQuantity);
            showScreen('cart');
        };

        showScreen('item');
    }

    function updateModalQty(delta) {
        modalQuantity = Math.max(1, modalQuantity + delta);
        document.getElementById('modal-qty').textContent = modalQuantity;
        const totalMinor = activeModalItemRawPrice * modalQuantity;
        document.getElementById('modal-total-btn-price').textContent = formatPrice(totalMinor);
    }

    function getQuantity(itemId) {
        const input = document.getElementById(`qty-input-${itemId}`);
        return input ? (parseInt(input.value) || 0) : 0;
    }

    function setQuantity(itemId, qty) {
        const input = document.getElementById(`qty-input-${itemId}`);
        if (input) {
            input.value = Math.max(0, qty);
        }
        updateCartUI();
    }

    function addToCart(itemId, name, priceMinor, image) {
        setQuantity(itemId, getQuantity(itemId) + 1);
        updateCartUI();
    }

    function formatPrice(minor) {
        const major = minor / 100;
        return `₦${major.toLocaleString('en-NG', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}`;
    }

    function updateCartUI() {
        const container = document.getElementById('cart-items-list');
        container.innerHTML = '';

        let subtotalMinor = 0;
        let totalCount = 0;

        const inputs = document.querySelectorAll('#hidden-qty-container input[data-qty]');
        inputs.forEach(input => {
            const qty = parseInt(input.value) || 0;
            if (qty > 0) {
                totalCount += qty;
                const itemId = input.name.replace('qty[', '').replace(']', '');
                const row = document.querySelector(`.menu-item-row[onclick*="${itemId}"], .menu-item-row input[name="qty[${itemId}]"]`)?.closest('.menu-item-row');
                
                let name = 'Menu Item';
                let priceMinor = 0;
                let image = '{{ asset("images/stock/food-mains.jpg") }}';

                if (row) {
                    const titleEl = row.querySelector('h4');
                    const imgEl = row.querySelector('img');
                    const priceEl = row.querySelector('span.text-brand-600');
                    if (titleEl) name = titleEl.textContent;
                    if (imgEl) image = imgEl.src;
                    if (priceEl) {
                        const priceText = priceEl.textContent.replace(/[^0-9]/g, '');
                        priceMinor = (parseInt(priceText) || 0) * 100;
                    }
                }

                const lineTotalMinor = priceMinor * qty;
                subtotalMinor += lineTotalMinor;

                const div = document.createElement('div');
                div.className = 'p-3 rounded-2xl border border-ink-100 bg-white flex items-center gap-3 shadow-2xs';
                div.innerHTML = `
                    <img src="${image}" class="w-14 h-14 rounded-xl object-cover shrink-0">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-xs font-bold text-ink-900 truncate">${name}</h4>
                        <p class="text-xs font-bold text-brand-600 mt-0.5">${formatPrice(lineTotalMinor)}</p>
                    </div>
                    <div class="flex items-center gap-2 bg-ink-50 px-2 py-1 rounded-lg border border-ink-200">
                        <button type="button" onclick="setQuantity(${itemId}, ${qty - 1})" class="w-5 h-5 text-xs font-bold text-ink-700 flex items-center justify-center">-</button>
                        <span class="text-xs font-bold text-ink-900 w-4 text-center">${qty}</span>
                        <button type="button" onclick="setQuantity(${itemId}, ${qty + 1})" class="w-5 h-5 text-xs font-bold text-ink-700 flex items-center justify-center">+</button>
                    </div>
                    <button type="button" onclick="setQuantity(${itemId}, 0)" class="text-red-500 hover:text-red-700 p-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                `;
                container.appendChild(div);
            }
        });

        if (totalCount === 0) {
            container.innerHTML = `<p class="text-xs text-ink-400 text-center py-8">Your cart is empty. Browse the menu to add items.</p>`;
        }

        document.getElementById('cart-badge-count').textContent = totalCount;
        document.getElementById('cart-subtotal-text').textContent = formatPrice(subtotalMinor);
        document.getElementById('cart-total-text').textContent = formatPrice(subtotalMinor);
        document.getElementById('submit-order-btn').disabled = totalCount === 0;
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateCartUI();
        if (document.body.dataset.hasErrors === '1') {
            showScreen('cart');
        } else {
            const initialScreen = new URLSearchParams(window.location.search).get('screen');
            if (initialScreen && document.getElementById(`screen-${initialScreen}`)) showScreen(initialScreen);
        }
    });

    window.setInterval(() => {
        if (activeScreenName === 'confirmation') refreshSessionOrders();
    }, 12000);
</script>
</body>
</html>