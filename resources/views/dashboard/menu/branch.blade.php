@extends('layouts.app')
@section('content')
<h1 class="mb-1 text-2xl font-bold">{{ $branch->name }}: menu</h1>
<p class="mb-6 text-sm text-ink-500">Leave a price blank to not offer an item at this branch. Prices are in {{ $currency }}.</p>
<form method="POST" action="{{ route('branch.menu.update', $branch) }}">@csrf @method('PUT')
    @error('items.*.price')<p role="alert" class="mb-3 text-sm text-red-700">{{ $message }}</p>@enderror
    <div class="card overflow-x-auto"><table class="w-full text-left text-sm">
        <thead><tr><th class="p-2">Item</th><th class="p-2">Price</th><th class="p-2">Available</th><th class="p-2">Visible</th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            @php $o = $offers->get($item->id); @endphp
            <tr class="border-t">
                <td class="p-2">{{ $item->name }}<span class="block text-xs text-ink-500">{{ $item->category->name }}</span></td>
                <td class="p-2"><label class="sr-only" for="p{{ $item->id }}">Price for {{ $item->name }}</label>
                    <input id="p{{ $item->id }}" name="items[{{ $item->id }}][price]" inputmode="decimal" @disabled(! $canPrice)
                        value="{{ $o ? App\Support\MoneyFormat::plain($o->price_minor, $currency) : '' }}" class="w-28 rounded border px-2 py-1"></td>
                <td class="p-2"><input type="hidden" name="items[{{ $item->id }}][available]" value="0"><input type="checkbox" name="items[{{ $item->id }}][available]" value="1" @checked($o?->is_available ?? true) aria-label="Available: {{ $item->name }}"></td>
                <td class="p-2"><input type="hidden" name="items[{{ $item->id }}][visible]" value="0"><input type="checkbox" name="items[{{ $item->id }}][visible]" value="1" @checked($o?->is_visible ?? true) @disabled(! $canPrice) aria-label="Visible: {{ $item->name }}"></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <button class="mt-4 btn btn-primary">Save branch menu</button>
</form>
@endsection
