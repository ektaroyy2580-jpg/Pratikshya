{{--
    Live office announcements for a queue. Include with ['service' => $service] or ['ticket' => $ticket].
    Server-renders the current public notices, then (for logged-in users) polls
    GET /api/announcements?service=<id> every 10s. Shows ONLY published, in-window, public-safe notices.
    Optional: 'notices' => precomputed collection (avoids a second query).
--}}
@php
    $opsSvc = $service ?? ($ticket->service ?? null);
    $opsNotices = $opsSvc ? ($notices ?? app(\App\Services\Ops\AnnouncementService::class)->publicFor($opsSvc)) : collect();
    $opsSev = ['info' => 'bi-info-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'critical' => 'bi-exclamation-octagon-fill'];
@endphp
@if($opsSvc)
    @once
        @push('head')
            <link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
        @endpush
        @auth
            @push('scripts')
                <script src="{{ asset('js/modules/ops-banner.js') }}?v={{ filemtime(public_path('js/modules/ops-banner.js')) }}"></script>
            @endpush
        @endauth
    @endonce
    <div class="ops-banner mb-4" role="status" aria-live="polite" aria-label="Office announcements"
         @auth data-ops-feed="{{ route('ops.api.announcements', ['service' => $opsSvc->id]) }}" @endauth>@foreach($opsNotices as $n)<div class="notice-preview sev-{{ $n->severity }}" data-id="{{ $n->id }}"><span class="ic"><i class="bi {{ $opsSev[$n->severity] ?? 'bi-info-circle-fill' }}"></i></span><div class="min-w-0"><div class="tt">{{ $n->title }}</div><div class="mm">{{ $n->public_message }}</div></div></div>@endforeach</div>
@endif
