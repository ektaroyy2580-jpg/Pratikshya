@extends('layouts.app')
@section('title', 'QR posters — Office operations — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php $isAdmin = auth()->user()->isAdmin(); @endphp
@include('ops.partials.console-head', ['active' => 'qr', 'title' => 'QR posters', 'subtitle' => 'Print a poster for each queue. Scanning opens a public info page — joining still needs a login.'])

<div class="container py-4 py-lg-5">
    <div class="alert alert-warning d-flex gap-2 align-items-start mb-4 fade-up" role="note" style="background:var(--pk-tint);border-color:#d5ddff;color:#1f3fae">
        <i class="bi bi-shield-check fs-5"></i>
        <div class="small">Each QR code holds only a public link like <span class="code-pill">/q/abc123…</span> — no account data or secrets. @if($isAdmin)If a poster is misused, <b>rotate</b> its code: the old one stops working immediately.@else Admins can rotate a code if a poster is misused.@endif</div>
    </div>

    @forelse($locations as $l)
        <section class="pk-card p-4 mb-4 reveal" aria-labelledby="loc-{{ $l->id }}">
            <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between mb-3">
                <div class="d-flex gap-3 align-items-center min-w-0">
                    <div class="qr-thumb" data-qr="{{ route('ops.qr.location', $l->qr_code) }}" data-label="QR for {{ $l->name }}"><span class="skeleton d-block w-100 h-100"></span></div>
                    <div class="min-w-0">
                        <h2 id="loc-{{ $l->id }}" class="h5 mb-1">{{ $l->name }}</h2>
                        <div class="small pk-muted mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $l->area }} · location poster lists every queue</div>
                        <span class="code-pill">/q/l/{{ $l->qr_code }}</span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('ops.qr.location-poster', $l) }}" class="btn btn-ghost btn-sm"><i class="bi bi-printer me-1"></i>Location poster</a>
                    @if($isAdmin)
                        <form method="POST" action="{{ route('ops.qr.location-rotate', $l) }}" class="js-confirm" data-confirm="Issue a new QR code for {{ $l->name }}? Printed location posters will stop working.">@csrf<button class="btn btn-ghost btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Rotate</button></form>
                    @endif
                </div>
            </div>

            <div class="row g-3">
                @forelse($l->services as $s)
                    <div class="col-md-6 col-xl-4">
                        <div class="h-100 d-flex gap-3 p-3 rounded-4 border" style="border-color:var(--pk-line)!important">
                            <div class="qr-thumb" data-qr="{{ route('ops.qr.landing', $s->qr_code) }}" data-label="QR for {{ $s->name }}"><span class="skeleton d-block w-100 h-100"></span></div>
                            <div class="min-w-0 d-flex flex-column">
                                <div class="fw-bold text-truncate" title="{{ $s->name }}">{{ $s->name }}</div>
                                <div class="small pk-muted mb-1">Prefix {{ $s->prefix }} · Counter {{ $s->counter }}@unless($s->is_active) · <span class="text-danger">inactive</span>@endunless</div>
                                <span class="code-pill align-self-start mb-2">/q/{{ $s->qr_code }}</span>
                                <div class="d-flex flex-wrap gap-1 mt-auto">
                                    <a href="{{ route('ops.qr.poster', $s) }}" class="btn btn-pk btn-sm"><i class="bi bi-printer"></i> Poster</a>
                                    <a href="{{ route('ops.qr.landing', $s->qr_code) }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener" aria-label="Open public page for {{ $s->name }}"><i class="bi bi-box-arrow-up-right"></i></a>
                                    @if($isAdmin)
                                        <form method="POST" action="{{ route('ops.qr.rotate', $s) }}" class="js-confirm" data-confirm="Issue a new QR code for {{ $s->name }}? The printed poster will stop working.">@csrf<button class="btn btn-ghost btn-sm" aria-label="Rotate QR code for {{ $s->name }}"><i class="bi bi-arrow-clockwise"></i></button></form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 small pk-muted">No services at this location yet.</div>
                @endforelse
            </div>
        </section>
    @empty
        <div class="pk-card ops-empty"><span class="pk-icon mb-3"><i class="bi bi-qr-code"></i></span><h2 class="h5">No locations assigned</h2><p class="pk-muted mb-0">Ask an admin to assign you to a location.</p></div>
    @endforelse
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/qrcode/qrcode.js') }}"></script>
<script src="{{ asset('js/modules/ops-qr.js') }}?v={{ filemtime(public_path('js/modules/ops-qr.js')) }}"></script>
@endpush
