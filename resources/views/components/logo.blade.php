@props(['dark' => false])
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-xl font-extrabold tracking-tight '.($dark ? 'text-white' : 'text-ink-900')]) }}>
    <svg viewBox="0 0 32 32" class="h-8 w-8" aria-hidden="true"><rect width="32" height="32" rx="8" fill="#14a674"/><path d="M8 9h16l-11 14h11" fill="none" stroke="#fff" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    ZIWAIT
</span>
