@props(['name', 'class' => 'h-5 w-5'])
<svg {{ $attributes->merge(['class' => $class]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
    @case('home') <path d="M3 11l9-8 9 8M5 10v10h14V10M10 20v-6h4v6"/> @break
    @case('business') <path d="M4 21V8l8-5 8 5v13M9 21v-6h6v6M8 11h.01M12 11h.01M16 11h.01"/> @break
    @case('pin') <path d="M12 21s7-6.2 7-11a7 7 0 10-14 0c0 4.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/> @break
    @case('users') <circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14c2.5 0 4 2 4 5"/> @break
    @case('card') <rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/> @break
    @case('receipt') <path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/> @break
    @case('megaphone') <path d="M3 10v4h3l8 4V6L6 10H3zM17 9a4 4 0 010 6"/> @break
    @case('bell') <path d="M6 16v-5a6 6 0 1112 0v5l2 2H4zM10 20a2 2 0 004 0"/> @break
    @case('chart') <path d="M4 20V4M4 20h16M8 16v-4M12 16V8M16 16v-6"/> @break
    @case('list') <path d="M5 4h14v16H5zM8 9h8M8 13h8M8 17h5"/> @break
    @case('cog') <circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/> @break
    @case('search') <circle cx="11" cy="11" r="6"/><path d="M20 20l-4-4"/> @break
    @case('check') <path d="M5 12l4 4 10-10"/> @break
    @case('qr') <rect x="4" y="4" width="6" height="6"/><rect x="14" y="4" width="6" height="6"/><rect x="4" y="14" width="6" height="6"/><path d="M14 14h3v3M20 14v6M14 20h3"/> @break
    @case('shield') <path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/> @break
    @case('mail') <path d="M3 5h18v14H3zM3 6l9 7 9-7"/> @break
    @case('plus') <path d="M12 5v14M5 12h14"/> @break
    @case('chevron') <path d="M6 9l6 6 6-6"/> @break
    @case('arrow') <path d="M5 12h14M13 6l6 6-6 6"/> @break
    @case('layers') <path d="M12 3l9 5-9 5-9-5zM3 13l9 5 9-5"/> @break
    @case('menu') <path d="M4 7h16M4 12h16M4 17h16"/> @break
    @case('cart') <path d="M3 4h2l2.2 10.4a2 2 0 002 1.6h8.9a2 2 0 001.9-1.4L22 8H6"/><circle cx="10" cy="19" r="1"/><circle cx="18" cy="19" r="1"/> @break
    @case('utensils') <path d="M7 3v6M4 3v4a3 3 0 006 0V3M7 10v11M17 3v18M17 3c-3 2-3 7 0 9"/> @break
    @case('cup') <path d="M7 3h10l-1 18H8L7 3zM8 8h8M12 8V3l3-2"/> @break
    @case('cake') <path d="M4 12h16v8H4zM4 12a4 4 0 017 0 4 4 0 017 0M8 16h.01M12 16h.01M16 16h.01"/> @break
    @case('plate') <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/> @break
@endswitch
</svg>
