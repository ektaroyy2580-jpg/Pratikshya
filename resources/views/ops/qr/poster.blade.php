@extends('layouts.app')
@section('title', 'QR poster — '.($service?->name ?? $location->name).' — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
<style>
    .poster-wrap { padding: 2rem 0 3rem; }
    .poster-tools { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; justify-content: space-between; margin-bottom: 1.25rem; }
    .paper { width: 100%; max-width: 620px; aspect-ratio: 210 / 297; margin: 0 auto; background: #fff; border-radius: 6px; box-shadow: var(--pk-shadow-lg); border: 1px solid var(--pk-line);
        display: flex; flex-direction: column; overflow: hidden; container-type: inline-size; }
    .paper .band { background: var(--pk-grad); color: #fff; padding: 5cqw 7cqw 4.2cqw; }
    .paper .brand { display: flex; align-items: center; gap: 2cqw; font-weight: 800; font-size: 3.6cqw; letter-spacing: -.02em; }
    .paper .brand .bm { width: 7cqw; height: 7cqw; border-radius: 2cqw; background: rgba(255, 255, 255, .18); display: grid; place-items: center; font-size: 3.6cqw; }
    .paper .loc { margin-top: 3.6cqw; font-size: 3cqw; font-weight: 700; opacity: .85; text-transform: uppercase; letter-spacing: .08em; }
    .paper h1 { font-size: 7.4cqw; line-height: 1.05; margin: 1.2cqw 0 0; letter-spacing: -.035em; overflow-wrap: anywhere; }
    .paper .body { flex: 1; min-height: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 3.5cqw 7cqw 2.5cqw; text-align: center; }
    .paper .cta { font-size: 4.4cqw; font-weight: 800; letter-spacing: -.02em; color: var(--pk-ink); }
    .paper .sub { font-size: 2.7cqw; color: var(--pk-muted); font-weight: 600; margin-top: .8cqw; }
    .paper .qr { width: 38cqw; aspect-ratio: 1; margin: 3cqw auto 2cqw; padding: 2cqw; border-radius: 3cqw; border: .5cqw solid var(--pk-ink); background: #fff; }
    .paper .qr svg { width: 100%; height: 100%; display: block; }
    .paper .url { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 2.5cqw; color: var(--pk-ink-2); overflow-wrap: anywhere; }
    .paper .steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 2cqw; padding: 0 7cqw 3cqw; }
    .paper .step { background: var(--pk-bg); border-radius: 2.4cqw; padding: 2.2cqw 2cqw; text-align: center; }
    .paper .step .n { width: 6cqw; height: 6cqw; border-radius: 50%; background: var(--pk-ink); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 2.8cqw; margin: 0 auto 1.4cqw; }
    .paper .step .t { font-weight: 800; font-size: 2.6cqw; color: var(--pk-ink); }
    .paper .step .d { font-size: 2.1cqw; color: var(--pk-muted); margin-top: .4cqw; }
    .paper .foot { border-top: 1px solid var(--pk-line); padding: 2.6cqw 7cqw; display: flex; justify-content: space-between; gap: 2cqw; font-size: 2.2cqw; color: var(--pk-muted); font-weight: 600; }
    @media print {
        @page { size: A4 portrait; margin: 0; }
        html, body { background: #fff !important; padding: 0 !important; }
        .pk-nav, .footer-ww, .bottom-nav, .poster-tools, .toast-ww { display: none !important; }
        .poster-wrap { padding: 0 !important; }
        .poster-wrap > .container { max-width: none !important; padding: 0 !important; }
        .paper { width: 210mm; height: 297mm; max-width: none; aspect-ratio: auto; border: 0; border-radius: 0; box-shadow: none; }
        .paper .band { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .paper .step, .paper .step .n { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
@endpush

@section('content')
<div class="poster-wrap">
    <div class="container">
        <div class="poster-tools">
            <a href="{{ route('ops.qr.index') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>All QR posters</a>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-ghost btn-sm js-copy" data-copy="{{ $url }}"><i class="bi bi-link-45deg"></i> Copy link</button>
                <a href="{{ $url }}" class="btn btn-ghost btn-sm" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> Open page</a>
                <button type="button" class="btn btn-pk btn-sm js-print"><i class="bi bi-printer me-1"></i>Print A4</button>
            </div>
        </div>

        <article class="paper" aria-label="Printable poster">
            <div class="band">
                <div class="brand"><span class="bm"><i class="bi bi-hourglass-split"></i></span> प्रतीक्षा · Pratikshya</div>
                @if($kind === 'service')
                    <div class="loc">{{ $location->name }}</div>
                    <h1>{{ $service->name }}</h1>
                @else
                    <div class="loc">{{ $location->area }}</div>
                    <h1>{{ $location->name }}</h1>
                @endif
            </div>
            <div class="body">
                <div class="cta">{{ $kind === 'service' ? 'Scan to see the live queue' : 'Scan to choose your service' }}</div>
                <div class="sub">{{ $kind === 'service' ? 'Waiting time · documents needed · join from your phone' : 'Live queues and waiting times for every counter' }}</div>
                <div class="qr" data-qr="{{ $url }}" data-margin="0" data-ecc="Q" data-label="QR code linking to {{ $url }}"></div>
                <div class="url">{{ $url }}</div>
            </div>
            <div class="steps">
                <div class="step"><div class="n">1</div><div class="t">Scan</div><div class="d">with your phone camera</div></div>
                <div class="step"><div class="n">2</div><div class="t">Check</div><div class="d">wait time &amp; documents</div></div>
                <div class="step"><div class="n">3</div><div class="t">Join</div><div class="d">log in &amp; get your token</div></div>
            </div>
            <div class="foot">
                <span>@if($kind === 'service')Counter {{ $service->counter }} · Token prefix {{ $service->prefix }}@else{{ $location->name }}@endif</span>
                <span>No app install needed</span>
            </div>
        </article>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/qrcode/qrcode.js') }}"></script>
<script src="{{ asset('js/modules/ops-qr.js') }}?v={{ filemtime(public_path('js/modules/ops-qr.js')) }}"></script>
@endpush
