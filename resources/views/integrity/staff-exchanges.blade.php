@extends('layouts.app')
@section('title', 'Queue integrity · Staff — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/integrity.css') }}?v={{ @filemtime(public_path('css/modules/integrity.css')) }}">
@endpush

@section('content')
<header class="pk-page-head ig-head">
    <div class="container">
        <a href="{{ route('staff') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Staff console</a>
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mt-2">
            <div class="fade-up">
                <span class="pk-eyebrow mb-2"><i class="bi bi-shield-check"></i> Staff · Queue integrity</span>
                <h1 class="mb-1">Exchanges &amp; reports</h1>
                <div class="pk-muted">Approve voluntary swaps at your locations and flag duplicate-token concerns for admin review.</div>
            </div>
            <span class="chip fade-up"><i class="bi bi-geo-alt"></i> Your assigned locations only</span>
        </div>
    </div>
</header>

<div class="container py-4 py-lg-5">
    @if($errors->any() && ! $errors->has('queue'))
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="pk-card p-3 p-md-4 mb-4 reveal" aria-labelledby="apHead">
                <div class="ig-sec-head">
                    <h2 id="apHead"><span class="pk-icon violet" style="width:34px;height:34px;font-size:.95rem"><i class="bi bi-person-check"></i></span> Waiting for your approval</h2>
                    <span class="chip">{{ $pending->count() }}</span>
                </div>
                @forelse($pending as $x)
                    <div class="mb-3">@include('integrity.partials._approval', ['x' => $x])</div>
                @empty
                    <div class="ig-empty py-3">
                        <span class="pk-icon green"><i class="bi bi-check2-all"></i></span>
                        <div class="fw-bold">All clear</div>
                        <div class="ig-note">Exchanges appear here once both people agree, for services that require staff approval.</div>
                    </div>
                @endforelse
                <div class="ig-principles mt-2"><i class="bi bi-info-circle me-1"></i>Approve only if both visitors are present or reachable and the swap does not skip anyone else. Swaps never involve payment or points.</div>
            </section>

            <section class="pk-card p-3 p-md-4 reveal" style="--d:.06s" aria-labelledby="recHead">
                <div class="ig-sec-head"><h2 id="recHead"><i class="bi bi-clock-history pk-muted"></i> Recent decisions</h2></div>
                @forelse($recent as $h)
                    <div class="ig-hist">
                        <span class="ig-st {{ $h->status === 'completed' ? 'dismissed' : ($h->status === 'rejected' ? 'escalated' : 'confirmed') }}">{{ $h->statusLabel() }}</span>
                        <div class="ig-min-0 flex-grow-1">
                            <div class="fw-semibold">{{ $h->service->token($h->requester_number_before) }} <i class="bi bi-arrow-left-right small pk-muted"></i> {{ $h->service->token($h->target_number_before) }} <span class="ig-note">· {{ $h->service->name }}</span></div>
                            <div class="ig-note">{{ $h->updated_at->diffForHumans() }}@if($h->decider) · by {{ $h->decider->name }}@endif @if($h->decision_note)· “{{ $h->decision_note }}”@endif</div>
                        </div>
                    </div>
                @empty
                    <div class="ig-note">No decisions yet.</div>
                @endforelse
            </section>
        </div>

        <aside class="col-lg-5">
            <div class="ig-sticky reveal" style="--d:.1s">
                @if($services->count() > 1)
                    <form method="GET" class="mb-3">
                        <label for="igSvcPick" class="form-label small mb-1">Service</label>
                        <select id="igSvcPick" name="service" class="form-select" onchange="this.form.submit()">
                            @foreach($services as $s)
                                <option value="{{ $s->id }}" @selected($service && $s->id === $service->id)>{{ $s->name }} · {{ $s->location->name }}</option>
                            @endforeach
                        </select>
                        <noscript><button class="btn btn-ghost btn-sm mt-2">Show</button></noscript>
                    </form>
                @endif
                @if($service)
                    @include('integrity.partials.staff-card', ['service' => $service])
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/integrity.js') }}?v={{ @filemtime(public_path('js/modules/integrity.js')) }}"></script>
@endpush
