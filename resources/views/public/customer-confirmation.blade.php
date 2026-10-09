<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex">
    <title>Order Status · {{ $order->branch->name }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950/5 md:bg-ink-100 font-sans text-ink-900 antialiased flex flex-col items-center">

<main class="w-full max-w-[430px] min-h-screen bg-white shadow-2xl relative pb-12 flex flex-col justify-between">
    <!-- Header bar -->
    <header class="sticky top-0 z-40 bg-white/95 backdrop-blur-md border-b border-ink-100 px-4 py-3 flex items-center justify-between">
        <a href="{{ $menuUrl }}" class="flex items-center gap-2">
            <div class="h-7 w-7 rounded-lg bg-brand-500 flex items-center justify-center font-black text-sm text-white shadow-xs">Z</div>
            <span class="text-sm font-black tracking-tight text-ink-900">ZIWAIT</span>
        </a>
        <a href="{{ $menuUrl }}" class="text-xs font-bold text-brand-600 hover:underline">Back to Menu</a>
    </header>

    <div class="p-6 text-center space-y-6 flex-1">
        <!-- Floating Checkmark Circle with sparkles -->
        <div class="relative w-20 h-20 mx-auto">
            <div class="w-20 h-20 rounded-full bg-emerald-100 border-2 border-emerald-300 flex items-center justify-center text-emerald-600 shadow-md">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
            </div>
            <span class="absolute -top-1 right-1 w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
        </div>

        <div>
            <h1 class="text-2xl font-black text-ink-900">{{ $order->status->value === 'submitted' ? 'Order Submitted!' : 'Order Status' }}</h1>
            <p class="text-xs text-ink-500 max-w-xs mx-auto mt-1.5 leading-relaxed">
                Thank you for your order. {{ $order->branch->name }} has received it and will start preparing it soon.
            </p>
        </div>

        <!-- Reference Box -->
        <div class="p-4 rounded-2xl border border-ink-100 bg-ink-50/60 text-left space-y-1">
            <div class="flex justify-between items-center text-[10px] font-bold text-ink-400 uppercase tracking-wider">
                <span>Order Reference</span>
                <span>Table {{ $order->table_number }}</span>
            </div>
            <div class="flex items-center justify-between">
                <span class="font-mono text-lg font-black text-ink-900">{{ $order->reference }}</span>
                <button type="button" onclick="navigator.clipboard.writeText('{{ $order->reference }}'); alert('Reference copied!');" class="text-xs text-brand-600 font-bold hover:underline">Copy</button>
            </div>
        </div>

        <!-- Status Timeline -->
        @php
            $statusSteps = ['submitted' => 'Submitted', 'acknowledged' => 'Acknowledged', 'preparing' => 'Preparing', 'ready' => 'Ready', 'completed' => 'Completed'];
            $currentStep = array_search($order->status->value, array_keys($statusSteps), true);
            $isStopped = in_array($order->status->value, ['rejected', 'cancelled'], true);
        @endphp
        <div class="p-4 rounded-2xl border border-ink-100 bg-white text-left space-y-3 shadow-2xs">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-xs font-bold text-ink-900 uppercase tracking-wider">Order Status</h4>
                <span class="px-2.5 py-0.5 rounded-full bg-brand-50 text-brand-700 font-bold text-[10px] border border-brand-200">{{ $order->status->label() }}</span>
            </div>

            @if ($isStopped)
                <div class="p-3 rounded-xl bg-red-50 text-red-700 text-xs font-semibold">
                    This order was {{ $order->status->value }}. Please contact the branch directly for support.
                </div>
            @else
                <div class="space-y-3 text-xs">
                    @foreach ($statusSteps as $statusKey => $label)
                        @php
                            $stepIndex = $loop->index;
                            $complete = $currentStep !== false && $stepIndex <= $currentStep;
                            $isCurrent = $order->status->value === $statusKey;
                        @endphp
                        <div class="flex items-center gap-3 {{ $complete ? '' : 'opacity-50' }}">
                            <span class="w-5 h-5 rounded-full {{ $complete ? 'bg-brand-500 text-white' : 'bg-ink-200 text-ink-600' }} flex items-center justify-center font-bold text-[10px]">
                                {{ $complete ? '✓' : '○' }}
                            </span>
                            <div class="flex-1 flex justify-between">
                                <span class="font-bold {{ $isCurrent ? 'text-brand-700' : 'text-ink-800' }}">{{ $label }}</span>
                                @if ($isCurrent)
                                    <span class="text-brand-600 text-[10px] font-bold">Current</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Detailed Ordered Items Summary List -->
        <div class="p-4 rounded-2xl border border-ink-100 bg-ink-50/50 text-left space-y-3 text-xs">
            <div class="flex items-center justify-between border-b border-ink-200/60 pb-2">
                <h4 class="font-bold text-ink-900 uppercase tracking-wider text-[11px]">Items Ordered</h4>
                <span class="text-ink-500 text-[11px] font-semibold">{{ $order->items->sum('quantity') }} items</span>
            </div>
            
            <div class="space-y-2.5">
                @foreach ($order->items as $item)
                    <div class="flex items-center justify-between gap-3 p-2 rounded-xl bg-white border border-ink-100/80">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-6 h-6 rounded-lg bg-brand-50 text-brand-700 font-extrabold text-xs flex items-center justify-center shrink-0">
                                {{ $item->quantity }}x
                            </span>
                            <span class="font-bold text-ink-900 truncate">{{ $item->name }}</span>
                        </div>
                        <span class="font-bold text-brand-600 shrink-0">{{ App\Support\MoneyFormat::display($item->line_total_minor, $order->currency) }}</span>
                    </div>
                @endforeach
            </div>

            @if ($order->notes)
                <div class="pt-2 border-t border-ink-200/60 text-[11px] text-ink-600">
                    <span class="font-bold text-ink-800">Special Instructions:</span> {{ $order->notes }}
                </div>
            @endif

            <div class="border-t border-ink-200/60 pt-2 flex justify-between font-black text-sm text-ink-900">
                <span>Total Amount</span>
                <span class="text-brand-600">{{ App\Support\MoneyFormat::display($order->total_minor, $order->currency) }}</span>
            </div>
        </div>

        <p class="text-[11px] text-ink-400 leading-relaxed max-w-xs mx-auto">
            ⓘ You can check your order status anytime using your reference number.
        </p>

        <a href="{{ $menuUrl }}" class="block w-full py-3.5 bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm rounded-xl shadow-md transition">
            Back to Menu
        </a>
    </div>
</main>

</body>
</html>