@push('head')
<link href="{{ asset('css/modules/bills.css') }}?v={{ filemtime(public_path('css/modules/bills.css')) }}" rel="stylesheet">
@endpush
