{{-- Pushes the library stylesheet into the layout head exactly once, wherever a library partial is included. --}}
@once
    @push('head')
        <link rel="stylesheet" href="{{ asset('css/modules/library.css') }}?v={{ @filemtime(public_path('css/modules/library.css')) }}">
        <noscript><style>.lib-ph img { opacity: 1 !important; }</style></noscript>
    @endpush
@endonce
