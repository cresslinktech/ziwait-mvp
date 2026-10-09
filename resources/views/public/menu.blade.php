<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $branch->name }} – Menu</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-stone-50 pb-28 text-stone-900 antialiased">
<header class="bg-white px-4 py-5 shadow-sm">
    <h1 class="text-xl font-bold">{{ $business->name }}</h1>
    <p class="text-sm text-stone-600">{{ $branch->name }}@if($branch->address) · {{ $branch->address }}@endif</p>
</header>

<main class="mx-auto max-w-2xl px-4">
@if (! $branch->is_active)
    <p class="mt-6 rounded bg-amber-50 p-3 text-sm text-amber-900">This branch is not taking orders right now.</p>
@endif

<form method="POST" action="{{ route('menu.order', $link->token) }}" id="order-form" novalidate>
    @csrf
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded bg-red-50 p-3 text-sm text-red-800">
            <ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @forelse ($groups as $category => $offers)
        <section class="mt-6" aria-labelledby="cat-{{ $loop->index }}">
            <h2 id="cat-{{ $loop->index }}" class="mb-2 text-lg font-semibold">{{ $category }}</h2>
            <ul class="divide-y rounded bg-white shadow-sm">
            @foreach ($offers as $offer)
                @php $item = $offer->menuItem; $id = $item->id; @endphp
                <li class="flex gap-3 p-3 {{ $offer->is_available ? '' : 'opacity-50' }}">
                    @if ($item->imageUrl())<img src="{{ $item->imageUrl() }}" alt="" class="h-20 w-20 rounded object-cover" loading="lazy">@endif
                    <div class="flex-1">
                        <p class="font-medium">{{ $item->name }}</p>
                        @if ($item->description)<p class="text-sm text-stone-600">{{ $item->description }}</p>@endif
                        <p class="mt-1 text-sm font-semibold">{{ App\Support\MoneyFormat::display($offer->price_minor, $currency) }}</p>
                    </div>
                    <div class="flex items-center gap-2" data-stepper data-price="{{ $offer->price_minor }}">
                        @if ($offer->is_available)
                            <button type="button" data-dec class="h-9 w-9 rounded border" aria-label="Remove one {{ $item->name }}">−</button>
                            <input type="number" name="qty[{{ $id }}]" value="{{ old("qty.$id", 0) }}" min="0" max="{{ App\Domain\Orders\Actions\PlaceOrder::MAX_QUANTITY }}" class="w-12 rounded border text-center" aria-label="Quantity of {{ $item->name }}">
                            <button type="button" data-inc class="h-9 w-9 rounded border" aria-label="Add one {{ $item->name }}">+</button>
                        @else
                            <span class="text-xs text-stone-500">Sold out</span>
                        @endif
                    </div>
                </li>
            @endforeach
            </ul>
        </section>
    @empty
        <p class="mt-8 text-center text-stone-500">This menu is empty for now. Please check back soon.</p>
    @endforelse

    @if ($groups->isNotEmpty())
    <section class="mt-8 rounded bg-white p-4 shadow-sm" aria-labelledby="details">
        <h2 id="details" class="mb-3 text-lg font-semibold">Your details</h2>
        @foreach (['name' => ['Name', 'text', 'name'], 'phone' => ['Phone', 'tel', 'tel'], 'email' => ['Email (optional)', 'email', 'email']] as $f => [$label, $type, $ac])
            <div class="mb-3">
                <label for="f-{{ $f }}" class="block text-sm font-medium">{{ $label }}</label>
                <input id="f-{{ $f }}" name="{{ $f }}" type="{{ $type }}" autocomplete="{{ $ac }}" value="{{ old($f) }}" class="mt-1 w-full rounded border border-stone-300 px-3 py-2" @unless($f === 'email') required @endunless>
            </div>
        @endforeach
        <div class="mb-3">
            <label for="f-notes" class="block text-sm font-medium">Notes for the restaurant (optional)</label>
            <textarea id="f-notes" name="notes" rows="3" maxlength="500" class="mt-1 w-full rounded border border-stone-300 px-3 py-2">{{ old('notes') }}</textarea>
        </div>
        <p class="text-xs text-stone-500">Payment is arranged directly with the restaurant. ZIWAIT does not take payment for food orders.</p>
    </section>
    @endif
</form>
</main>

@if ($groups->isNotEmpty())
<div class="fixed inset-x-0 bottom-0 border-t bg-white p-3">
    <div class="mx-auto flex max-w-2xl items-center justify-between">
        <p><span id="count">0</span> items · <strong id="total"></strong></p>
        <button form="order-form" class="rounded bg-stone-900 px-5 py-2 font-medium text-white">Place order</button>
    </div>
</div>
<script>
    // Display only: the server recalculates the real total from its own prices.
    (function () {
        var currency = @json($currency), fraction = {{ \Brick\Money\Currency::of($currency)->getDefaultFractionDigits() }};
        var steppers = document.querySelectorAll('[data-stepper]');
        function render() {
            var minor = 0, count = 0;
            steppers.forEach(function (s) {
                var input = s.querySelector('input'); if (!input) return;
                var qty = Math.max(0, Math.min(parseInt(input.value || '0', 10) || 0, input.max));
                count += qty; minor += qty * parseInt(s.dataset.price, 10);
            });
            document.getElementById('count').textContent = count;
            document.getElementById('total').textContent = new Intl.NumberFormat('en', {style: 'currency', currency: currency}).format(minor / Math.pow(10, fraction));
        }
        steppers.forEach(function (s) {
            var input = s.querySelector('input'); if (!input) return;
            s.querySelector('[data-inc]').addEventListener('click', function () { input.value = Math.min(+input.max, (+input.value || 0) + 1); render(); });
            s.querySelector('[data-dec]').addEventListener('click', function () { input.value = Math.max(0, (+input.value || 0) - 1); render(); });
            input.addEventListener('input', render);
        });
        render();
    })();
</script>
@endif
</body>
</html>
