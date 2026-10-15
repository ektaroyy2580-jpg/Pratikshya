{{-- Pratikshya mark: an hourglass whose sand runs down, then the glass flips (loops seamlessly).
     Params: $size (px, default 36), $static (bool, no animation). --}}
@php $size = $size ?? 36; $uid = 'pk'.substr(md5(uniqid('', true)), 0, 6); @endphp
<svg class="pk-logo {{ !empty($static) ? 'is-static' : '' }}" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 40 40" role="img" aria-label="Pratikshya logo" xmlns="http://www.w3.org/2000/svg">
    <defs>
        <linearGradient id="{{ $uid }}-bg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#2f5bea"/><stop offset="1" stop-color="#7046e8"/>
        </linearGradient>
        <linearGradient id="{{ $uid }}-sand" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#ffe7a3"/><stop offset="1" stop-color="#f7b733"/>
        </linearGradient>
        <clipPath id="{{ $uid }}-top"><path d="M13.6 10h12.8c0 4.6-3.4 7-5.3 9.2h-2.2C17 17 13.6 14.6 13.6 10z"/></clipPath>
        <clipPath id="{{ $uid }}-bot"><path d="M18.9 20.8h2.2c1.9 2.2 5.3 4.6 5.3 9.2H13.6c0-4.6 3.4-7 5.3-9.2z"/></clipPath>
    </defs>
    <rect width="40" height="40" rx="11" fill="url(#{{ $uid }}-bg)"/>
    <rect x="1" y="1" width="38" height="38" rx="10" fill="none" stroke="#fff" stroke-opacity=".18"/>
    <g class="pk-logo-glass">
        {{-- sand --}}
        <g clip-path="url(#{{ $uid }}-top)"><rect class="pk-sand-top" x="12" y="10" width="16" height="9.6" fill="url(#{{ $uid }}-sand)"/></g>
        <g clip-path="url(#{{ $uid }}-bot)"><rect class="pk-sand-bot" x="12" y="20.6" width="16" height="9.6" fill="url(#{{ $uid }}-sand)"/></g>
        <line class="pk-sand-stream" x1="20" y1="19.4" x2="20" y2="29.6" stroke="#f7b733" stroke-width="1.1" stroke-linecap="round" stroke-dasharray="1.4 1.6"/>
        {{-- glass + frame --}}
        <path d="M13.6 10h12.8c0 4.6-3.4 7-5.3 9.2c1.9 2.2 5.3 4.6 5.3 9.2v.8H13.6v-.8c0-4.6 3.4-7 5.3-9.2C17 17 13.6 14.6 13.6 10z" fill="none" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/>
        <rect x="11.5" y="7.6" width="17" height="2.4" rx="1.2" fill="#fff"/>
        <rect x="11.5" y="30" width="17" height="2.4" rx="1.2" fill="#fff"/>
    </g>
</svg>
