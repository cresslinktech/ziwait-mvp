@extends('layouts.onboarding')
@section('content')
@php
    $field = 'mt-1.5 w-full rounded-xl border border-ink-100 bg-ink-50/80 px-4 py-2.5 text-sm transition focus:border-brand-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-100';
    $err = fn ($k) => $errors->has($k) ? '<p class="mt-1 text-sm text-red-700">'.e($errors->first($k)).'</p>' : '';
@endphp

<section aria-labelledby="step-h">
    {{-- Step 1: Business Details --}}
    @if ($step === 1)
        <p class="mb-6 text-sm text-ink-500">Tell us about your restaurant or hotel.</p>
        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <form method="POST" action="{{ route('onboarding.store', 1) }}" enctype="multipart/form-data" class="space-y-5">@csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="block text-sm font-medium text-ink-900">Business name <span class="text-red-600">*</span></label>
                        <input id="name" name="name" required maxlength="120" value="{{ old('name', $business->name) }}" placeholder="The Green Bistro" class="{{ $field }}">
                        {!! $err('name') !!}
                    </div>
                    <div>
                        <label for="business_type" class="block text-sm font-medium text-ink-900">Business type <span class="text-red-600">*</span></label>
                        <select id="business_type" name="business_type" required class="{{ $field }}">
                            @foreach (App\Domain\Tenancy\Models\Business::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('business_type', $business->business_type ?? 'restaurant') === $v)>{{ $l }}</option>@endforeach
                        </select>
                        {!! $err('business_type') !!}
                    </div>
                </div>
                <div>
                    <label for="description" class="block text-sm font-medium text-ink-900">Description</label>
                    <textarea id="description" name="description" rows="4" maxlength="500" class="{{ $field }}" placeholder="A modern restaurant offering fresh, delicious meals and great vibes.">{{ old('description', $business->description) }}</textarea>
                    <p class="mt-1 text-right text-xs text-ink-500"><span id="desc-count">{{ strlen(old('description', $business->description ?? '')) }}</span>/500</p>
                    {!! $err('description') !!}
                </div>
                <div>
                    <label for="logo" class="block text-sm font-medium text-ink-900">Business logo</label>
                    <div class="mt-1.5 flex items-center gap-4">
                        <span class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-dashed border-ink-200 bg-ink-50 text-ink-400" id="logo-preview-wrap">
                            @if ($business->logoUrl())<img src="{{ $business->logoUrl() }}" alt="" class="h-full w-full object-cover rounded-xl">@else<x-icon name="plus" class="h-5 w-5" />@endif
                        </span>
                        <div class="flex-1">
                            <label for="logo" class="btn btn-outline cursor-pointer !py-2 text-xs">Change Logo</label>
                            <input id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                            <p class="mt-1 text-xs text-ink-500">Recommended size 600×600px</p>
                        </div>
                    </div>
                    {!! $err('logo') !!}
                </div>
                <input id="cover-photo-input" name="cover_photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only">
                {!! $err('cover_photo') !!}
                <div class="flex justify-end pt-2">
                    <button class="btn btn-primary !px-8">Next Step <x-icon name="arrow" class="h-4 w-4" /></button>
                </div>
            </form>
            <aside class="overflow-hidden rounded-2xl border border-ink-100 bg-ink-50" aria-label="Preview">
                <div class="relative">
                    <img id="cover-preview" src="{{ $business->coverUrl() ?? asset('images/stock/onboarding-banner.jpg') }}" alt="Restaurant cover preview" class="h-40 w-full object-cover">
                    <div class="absolute inset-0 bg-gradient-to-t from-ink-900/60 to-transparent"></div>
                    <label for="cover-photo-input" class="absolute right-3 top-3 inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-white/20 bg-black/60 px-2.5 py-1.5 text-[11px] font-semibold text-white backdrop-blur transition hover:bg-black/80">
                        <x-icon name="plus" class="h-3.5 w-3.5" /> Change Cover
                    </label>
                </div>
                <div class="relative p-5">
                    <span id="business-logo-preview" class="absolute -top-7 left-5 flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border-[3px] border-white bg-brand-600 text-lg font-extrabold text-white shadow-lg">
                        @if ($business->logoUrl())<img src="{{ $business->logoUrl() }}" alt="" class="h-full w-full object-cover">@else{{ strtoupper(substr($business->name, 0, 1)) }}@endif</span>
                    <div class="mt-6">
                        <h3 id="business-preview-name" class="text-lg font-extrabold">{{ $business->name }}</h3>
                        <p id="business-preview-type" class="text-xs font-medium text-ink-500">{{ App\Domain\Tenancy\Models\Business::TYPES[$business->business_type ?? 'restaurant'] ?? 'Restaurant' }}</p>
                    </div>
                    <p id="business-preview-description" class="mt-2.5 text-sm text-ink-600">{{ $business->description ?: 'A modern restaurant offering fresh, delicious meals and great vibes.' }}</p>
                </div>
            </aside>
        </div>

    {{-- Step 2: Branch Information --}}
    @elseif ($step === 2)
        <p class="mb-6 text-sm text-ink-500">Add your restaurant branches. You can always add more later.</p>
        <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
            <form method="POST" action="{{ route('onboarding.branches.add') }}" enctype="multipart/form-data" class="space-y-5">@csrf
                <div class="flex items-start gap-4">
                    <div class="group relative h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-ink-200">
                        <img id="branch-photo-preview" src="{{ asset('images/stock/branch-1.jpg') }}" alt="Branch photo preview" class="h-full w-full object-cover">
                        <input id="branch-photo-input" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="peer sr-only">
                        <label for="branch-photo-input" class="absolute inset-0 flex cursor-pointer items-center justify-center bg-black/35 text-center text-[10px] font-bold text-white opacity-0 transition group-hover:opacity-100 peer-focus-visible:opacity-100">Change Photo</label>
                    </div>
                    <div class="min-w-0 flex-1">
                        <label for="bname" class="block text-sm font-medium text-ink-900">Branch name <span class="text-red-600">*</span></label>
                        <input id="bname" name="name" required maxlength="120" value="{{ old('name') }}" placeholder="Victoria Island Branch" class="{{ $field }}">
                        {!! $err('name') !!}
                        <p class="mt-1 text-xs text-ink-500">Add a branch photo to help identify this location.</p>
                        {!! $err('photo') !!}
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="address" class="block text-sm font-medium text-ink-900">Address <span class="text-red-600">*</span></label>
                        <input id="address" name="address" required maxlength="255" value="{{ old('address') }}" placeholder="123 Adeola Odeku Street, Victoria Island, Lagos" class="{{ $field }}">
                        {!! $err('address') !!}
                    </div>
                    <div>
                        <label for="timezone" class="block text-sm font-medium text-ink-900">Timezone <span class="text-red-600">*</span></label>
                        <select id="timezone" name="timezone" class="{{ $field }}">@foreach (['Africa/Lagos', 'Africa/Accra', 'Africa/Nairobi', 'Africa/Johannesburg', 'Europe/London', 'UTC'] as $tz)<option @selected(old('timezone', 'Africa/Lagos') === $tz)>{{ $tz }}</option>@endforeach</select>
                        {!! $err('timezone') !!}
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-ink-900">Phone number <span class="text-red-600">*</span></label>
                        <input id="phone" name="phone" required type="tel" value="{{ old('phone') }}" placeholder="+234 801 234 5678" class="{{ $field }}">
                        {!! $err('phone') !!}
                    </div>
                    <div class="sm:col-span-2">
                        <span class="block text-sm font-medium text-ink-900">Operating hours <span class="text-red-600">*</span></span>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <select name="hours_status" aria-label="Open or closed" class="rounded-xl border border-ink-100 bg-ink-50/80 px-4 py-2.5 text-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100"><option value="open">Open</option><option value="closed" @selected(old('hours_status') === 'closed')>Closed</option></select>
                            <input type="time" name="open_time" value="{{ old('open_time', '08:00') }}" aria-label="Opening time" class="rounded-xl border border-ink-100 bg-ink-50/80 px-4 py-2.5 text-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                            <span class="text-sm font-medium text-ink-500">to</span>
                            <input type="time" name="close_time" value="{{ old('close_time', '23:00') }}" aria-label="Closing time" class="rounded-xl border border-ink-100 bg-ink-50/80 px-4 py-2.5 text-sm transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-100">
                        </div>
                        {!! $err('open_time') !!}{!! $err('close_time') !!}
                    </div>
                </div>
                <button class="btn btn-outline">
                    <x-icon name="plus" class="h-4 w-4" /> Add Another Branch
                </button>
            </form>

            {{-- Your Branches sidebar --}}
            <aside class="rounded-2xl border border-ink-100 bg-ink-50 p-5" aria-label="Your branches">
                <h3 class="mb-4 text-base font-bold text-ink-900">Your Branches</h3>
                <ul class="space-y-3">
                    @forelse ($branches as $b)
                        <li class="flex items-start gap-3 rounded-xl border border-ink-100 bg-white p-3.5 shadow-sm transition hover:shadow-md">
                            <img src="{{ $b->photoUrl() ?? asset('images/stock/branch-'.(($b->id % 2) + 1).'.jpg') }}" alt="" class="h-12 w-12 rounded-lg object-cover">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-ink-900">{{ $b->name }}</p>
                                <p class="truncate text-xs text-ink-500">{{ $b->address }}</p>
                            </div>
                            <div class="flex flex-col items-end gap-1.5">
                                <x-status :value="$b->is_active ? 'active' : 'pending'" />
                                <form method="POST" action="{{ route('onboarding.branches.remove', $b) }}" onsubmit="return confirm('Remove {{ e($b->name) }}?')">@csrf @method('DELETE')
                                    <button class="text-[11px] text-red-600 underline" aria-label="Remove {{ $b->name }}">Remove</button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="rounded-xl border border-dashed border-ink-200 bg-white/50 p-6 text-center text-sm text-ink-500">
                            <x-icon name="pin" class="mx-auto mb-2 h-8 w-8 text-ink-300" />
                            No branches yet. Add your first one.
                        </li>
                    @endforelse
                </ul>
            </aside>
        </div>
        <form method="POST" action="{{ route('onboarding.store', 2) }}" class="mt-6 flex items-center justify-between border-t border-ink-100 pt-5">@csrf
            <a href="{{ route('onboarding.show', 1) }}" class="btn btn-outline">← Back</a>
            <button class="btn btn-primary !px-8" @disabled($branches->isEmpty())>Next Step <x-icon name="arrow" class="h-4 w-4" /></button>
        </form>

    {{-- Step 3: Menu Setup --}}
    @elseif ($step === 3)
        <p class="mb-6 text-sm text-ink-500">Pick some starter categories. You will add items and prices from your dashboard.</p>
        <form method="POST" action="{{ route('onboarding.store', 3) }}">@csrf
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                @foreach ($suggested as $name)
                    @php $img = ['Appetizers' => 'appetizers', 'Main Courses' => 'mains', 'Drinks' => 'drinks', 'Desserts' => 'desserts', 'Sides' => 'appetizers'][$name]; $has = in_array($name, $existing); @endphp
                    <label class="group relative block cursor-pointer overflow-hidden rounded-2xl border-2 border-ink-100 transition hover:border-brand-300 has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-200 has-[:checked]:shadow-md">
                        <img src="{{ asset('images/stock/food-'.$img.'.jpg') }}" alt="" class="h-28 w-full object-cover transition group-hover:scale-105">
                        <span class="flex items-center gap-2.5 p-3.5 text-sm font-semibold">
                            <input type="checkbox" name="categories[]" value="{{ $name }}" @checked($has || in_array($name, old('categories', ['Appetizers', 'Main Courses', 'Drinks', 'Desserts']))) @disabled($has) class="h-4 w-4 rounded accent-brand-600">
                            {{ $name }}
                            @if($has)<span class="text-[10px] font-normal text-ink-500">(added)</span>@endif
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="mt-5 max-w-sm">
                <label for="custom_category" class="block text-sm font-medium text-ink-900">Another category (optional)</label>
                <input id="custom_category" name="custom_category" maxlength="100" value="{{ old('custom_category') }}" placeholder="e.g. Specials, Combos" class="{{ $field }}">
                {!! $err('custom_category') !!}
            </div>
            <section class="mt-8 overflow-hidden rounded-2xl border border-ink-100 bg-white" aria-labelledby="menu-preview-heading">
                <header class="flex flex-wrap items-center justify-between gap-3 border-b border-ink-100 bg-ink-50/70 px-5 py-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-brand-700">Customer view</p>
                        <h3 id="menu-preview-heading" class="mt-1 text-lg font-extrabold text-ink-900">{{ $business->name }}</h3>
                        <p class="text-xs text-ink-500">{{ $previewBranch?->name }} · Restaurant menu</p>
                    </div>
                    @if ($menuPreviewUrl)
                        <a href="{{ $menuPreviewUrl }}" target="_blank" rel="noopener" class="btn btn-outline !py-2 text-xs">Open customer menu <x-icon name="arrow" class="h-3.5 w-3.5" /></a>
                    @else
                        <span class="rounded-full border border-ink-200 bg-white px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-ink-500">Live preview</span>
                    @endif
                </header>
                <div class="px-5 py-4">
                    <div class="mb-4 flex gap-2 overflow-x-auto pb-1" aria-label="Menu categories">
                        @foreach ($suggested as $name)
                            <span class="shrink-0 rounded-full bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-800">{{ $name }}</span>
                        @endforeach
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($menuPreview as $category => $items)
                            @foreach ($items as $item)
                                <article data-menu-preview-category="{{ $category }}" class="overflow-hidden rounded-xl border border-ink-100 bg-white">
                                    <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" class="h-32 w-full object-cover" loading="lazy">
                                    <div class="p-3.5">
                                        <p class="text-[10px] font-semibold uppercase tracking-wider text-brand-700">{{ $category }}</p>
                                        <h4 class="mt-1 text-sm font-bold text-ink-900">{{ $item['name'] }}</h4>
                                        <p class="mt-1 min-h-10 text-xs leading-relaxed text-ink-500">{{ $item['description'] }}</p>
                                        <p class="mt-2 text-sm font-extrabold text-ink-900">{{ $item['price_display'] }}</p>
                                    </div>
                                </article>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </section>
            <div class="mt-6 flex items-center justify-between border-t border-ink-100 pt-5">
                <a href="{{ route('onboarding.show', 2) }}" class="btn btn-outline">← Back</a>
                <button class="btn btn-primary !px-8">Next Step <x-icon name="arrow" class="h-4 w-4" /></button>
            </div>
        </form>

    {{-- Step 4: Subscription Plan --}}
    @elseif ($step === 4)
        <p class="mb-6 text-sm text-ink-500">Choose a plan that fits your business needs.</p>
        @if ($menuPreviewUrl)
            <a href="{{ $menuPreviewUrl }}" target="_blank" rel="noopener" class="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-brand-700 underline decoration-brand-300 underline-offset-4">Preview your customer menu <x-icon name="arrow" class="h-4 w-4" /></a>
        @endif
        <form method="POST" action="{{ route('onboarding.store', 4) }}">@csrf
            {!! $err('plan_id') !!}
            <div class="mt-2 grid gap-5 md:grid-cols-3">
                @foreach ($plans as $plan)
                    @php
                        $price = $plan->prices->first();
                        $max = $plan->maxBranches();
                        $fits = $max === null || $branches->count() <= $max;
                        $popular = $plan->code === config('marketing.popular_plan');
                        $selected = (int) old('plan_id', $subscription?->plan_id) === $plan->id;
                    @endphp
                    <label @class([
                        'relative block rounded-2xl border-2 p-6 transition',
                        'cursor-pointer hover:border-brand-400 has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-200 has-[:checked]:shadow-lg' => $fits,
                        'opacity-50 cursor-not-allowed' => !$fits,
                        'bg-brand-50/30 border-brand-200' => $popular && $fits,
                        'border-ink-100' => !$popular,
                    ])>
                        @if ($popular)<span class="absolute -top-3 right-4 rounded-full bg-brand-600 px-3 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">Most Popular</span>@endif
                        <span class="flex items-center gap-2.5">
                            <input type="radio" name="plan_id" value="{{ $plan->id }}" required @checked($selected) @disabled(!$fits) class="h-4 w-4 accent-brand-600">
                            <strong class="text-lg">{{ $plan->name }}</strong>
                        </span>
                        <span class="mt-3 block text-3xl font-extrabold text-ink-900">{{ App\Support\MoneyFormat::display($price->amount_minor, $price->currency) }}<span class="text-sm font-normal text-ink-500">/month</span></span>
                        <ul class="mt-4 space-y-2 text-sm text-ink-700">
                            @foreach (config("marketing.plan_features.{$plan->code}", []) as $f)
                                <li class="flex gap-2"><x-icon name="check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" />{{ $f }}</li>
                            @endforeach
                        </ul>
                        @unless ($fits)
                            <span class="mt-3 block rounded-lg bg-red-50 p-2 text-xs text-red-700">Allows {{ $max }} branch{{ $max === 1 ? '' : 'es' }}; you added {{ $branches->count() }}.</span>
                        @endunless
                        @if ($popular && $fits)
                            <div class="mt-4">
                                <span class="btn btn-primary w-full !py-2.5 text-sm">Select Plan</span>
                            </div>
                        @else
                            <div class="mt-4">
                                <span class="btn btn-outline w-full !py-2.5 text-sm">Select Plan</span>
                            </div>
                        @endif
                    </label>
                @endforeach
            </div>
            <div class="mt-6 flex items-center justify-between border-t border-ink-100 pt-5">
                <a href="{{ route('onboarding.show', 3) }}" class="btn btn-outline">← Back</a>
                <button class="btn btn-primary !px-8">Next Step <x-icon name="arrow" class="h-4 w-4" /></button>
            </div>
        </form>

    {{-- Step 5: Complete --}}
    @else
        <p class="mb-6 text-sm text-ink-500">Review your details and finish setup.</p>
        @php $sub = \App\Domain\Billing\Models\Subscription::where('business_id', $business->id)->with('plan')->latest('id')->first(); @endphp
        <div class="grid gap-6 md:grid-cols-2">
            {{-- Review summary --}}
            <div class="space-y-4 rounded-2xl border border-ink-100 bg-ink-50 p-5">
                <h3 class="text-base font-bold text-ink-900">Setup Summary</h3>
                <dl class="space-y-3 text-sm">
                    <div class="flex items-start gap-3 rounded-xl bg-white p-3.5">
                        <x-icon name="business" class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                        <div>
                            <dt class="text-xs font-semibold uppercase text-ink-500">Business</dt>
                            <dd class="font-semibold text-ink-900">{{ $business->name }}</dd>
                            <dd class="text-xs text-ink-500">{{ App\Domain\Tenancy\Models\Business::TYPES[$business->business_type] ?? '' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl bg-white p-3.5">
                        <x-icon name="pin" class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                        <div>
                            <dt class="text-xs font-semibold uppercase text-ink-500">Branches</dt>
                            <dd class="font-semibold text-ink-900">{{ $branches->count() }} branches</dd>
                            <dd class="text-xs text-ink-500">{{ $branches->pluck('name')->join(', ') }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-xl bg-white p-3.5">
                        <x-icon name="layers" class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                        <div>
                            <dt class="text-xs font-semibold uppercase text-ink-500">Subscription plan</dt>
                            <dd class="font-semibold text-ink-900">{{ $sub?->plan->name ?? 'None selected' }}</dd>
                            <dd class="text-xs text-ink-500">{{ $sub?->status->value === 'trialing' ? 'Free trial until '.$sub->current_period_end->format('j M Y') : '' }}</dd>
                        </div>
                    </div>
                </dl>
                <div class="rounded-xl bg-white p-3.5">
                    <div class="flex items-center gap-2 text-sm">
                        <x-icon name="check" class="h-4 w-4 text-brand-600" /><span class="font-medium text-ink-700">Business details completed</span>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2 text-sm">
                        <x-icon name="check" class="h-4 w-4 text-brand-600" /><span class="font-medium text-ink-700">Branches added</span>
                    </div>
                    <div class="mt-1.5 flex items-center gap-2 text-sm">
                        <x-icon name="check" class="h-4 w-4 text-brand-600" /><span class="font-medium text-ink-700">Subscription plan selected</span>
                    </div>
                </div>
            </div>

            {{-- Celebration card --}}
            <div class="flex flex-col items-center justify-center rounded-2xl border border-brand-100 bg-gradient-to-br from-brand-50/80 to-white p-8 text-center">
                <div class="relative mb-5">
                    <span class="flex h-20 w-20 items-center justify-center rounded-full bg-brand-600 text-white shadow-[0_0_30px_rgba(20,166,116,0.3)]">
                        <x-icon name="check" class="h-10 w-10" />
                    </span>
                    <span class="absolute -right-1 -top-1 flex h-6 w-6 items-center justify-center rounded-full bg-amber-400 text-xs">🎉</span>
                </div>
                <h3 class="text-2xl font-extrabold text-ink-900">You're all set!</h3>
                <p class="mt-2 max-w-xs text-sm text-ink-500">Your restaurant is ready to use. Start managing your menu items and prices, print your QR code and start receiving orders.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('onboarding.store', 5) }}" class="mt-6 flex items-center justify-between border-t border-ink-100 pt-5">@csrf
            <a href="{{ route('onboarding.show', 4) }}" class="btn btn-outline">← Back</a>
            <button class="btn btn-primary !px-8 !py-3 !text-base">
                Complete Setup <x-icon name="check" class="h-5 w-5" />
            </button>
        </form>
    @endif
</section>

<script>
    const desc = document.getElementById('description');
    const descCount = document.getElementById('desc-count');
    const businessPreviewName = document.getElementById('business-preview-name');
    const businessPreviewType = document.getElementById('business-preview-type');
    const businessPreviewDescription = document.getElementById('business-preview-description');
    const businessNameInput = document.getElementById('name');
    const businessTypeInput = document.getElementById('business_type');

    if (desc && descCount) {
        desc.addEventListener('input', function () {
            descCount.textContent = this.value.length;
        });
    }

    if (businessNameInput && businessPreviewName) {
        businessNameInput.addEventListener('input', () => {
            businessPreviewName.textContent = businessNameInput.value || 'Your business name';
        });
    }

    if (businessTypeInput && businessPreviewType) {
        businessTypeInput.addEventListener('change', () => {
            businessPreviewType.textContent = businessTypeInput.selectedOptions[0].textContent;
        });
    }

    if (desc && businessPreviewDescription) {
        desc.addEventListener('input', () => {
            businessPreviewDescription.textContent = desc.value || 'A modern restaurant offering fresh, delicious meals and great vibes.';
        });
    }

    const logoInput = document.getElementById('logo');
    const logoWrap = document.getElementById('logo-preview-wrap');
    const businessLogoPreview = document.getElementById('business-logo-preview');
    if (logoInput && logoWrap) {
        logoInput.addEventListener('change', function () {
            if (this.files[0]) {
                const imageUrl = URL.createObjectURL(this.files[0]);
                const createPreview = (className) => {
                    const image = document.createElement('img');
                    image.src = imageUrl;
                    image.alt = '';
                    image.className = className;
                    return image;
                };

                logoWrap.replaceChildren(createPreview('h-full w-full rounded-xl object-cover'));
                businessLogoPreview?.replaceChildren(createPreview('h-full w-full object-cover'));
            }
        });
    }

    const bindImagePreview = (inputId, previewId) => {
        const input = document.getElementById(inputId);
        const preview = document.getElementById(previewId);
        if (input && preview) {
            input.addEventListener('change', () => {
                if (input.files[0]) preview.src = URL.createObjectURL(input.files[0]);
            });
        }
    };

    bindImagePreview('cover-photo-input', 'cover-preview');
    bindImagePreview('branch-photo-input', 'branch-photo-preview');

    const categoryInputs = Array.from(document.querySelectorAll('input[name="categories[]"]'));
    const previewCards = Array.from(document.querySelectorAll('[data-menu-preview-category]'));
    const updateMenuPreview = () => {
        const selected = new Set(categoryInputs.filter((input) => input.checked).map((input) => input.value));
        previewCards.forEach((card) => {
            card.hidden = !selected.has(card.dataset.menuPreviewCategory);
        });
    };

    categoryInputs.forEach((input) => input.addEventListener('change', updateMenuPreview));
    if (categoryInputs.length) updateMenuPreview();
</script>
@endsection
