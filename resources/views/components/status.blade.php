@props(['value'])
@php
    $v = $value instanceof \BackedEnum ? $value->value : (string) $value;
    $tone = match ($v) {
        'active', 'successful', 'handled', 'approved', 'ok' => 'bg-emerald-50 text-emerald-700',
        'pending', 'trialing', 'past_due', 'review', 'open', 'new' => 'bg-amber-50 text-amber-800',
        'failed', 'expired', 'cancelled', 'suspended', 'rejected', 'disabled' => 'bg-red-50 text-red-700',
        default => 'bg-ink-100 text-ink-700',
    };
@endphp
<span class="badge {{ $tone }}">{{ ucfirst(str_replace('_', ' ', $v)) }}</span>
