@extends('layouts.app')
@section('title', $incident->reference().' — Office operations — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/ops.css') }}?v={{ filemtime(public_path('css/modules/ops.css')) }}" rel="stylesheet">
@endpush

@section('content')
@php
    $typeLabel = ['created' => 'Reported', 'status' => 'Status changed', 'comment' => 'Comment', 'assignment' => 'Assignment', 'published' => 'Public notice'];
    $sevIcon = ['info' => 'bi-info-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', 'critical' => 'bi-exclamation-octagon-fill'];
    $allowed = \App\Models\OperationalIncident::TRANSITIONS[$incident->status] ?? [];
@endphp
@include('ops.partials.console-head', ['active' => 'incidents', 'title' => $incident->categoryLabel().($incident->counter ? ' · Counter '.$incident->counter->number : ''), 'subtitle' => $incident->reference().' · '.$incident->location->name])

<div class="container py-4 py-lg-5">
    <a href="{{ route('ops.incidents.index') }}" class="pk-breadcrumb d-inline-block mb-3"><i class="bi bi-arrow-left me-1"></i>All incidents</a>

    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-7">
            <section class="pk-card p-4 reveal" aria-labelledby="detHead">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                    <h2 id="detHead" class="h6 fw-bold mb-0 d-flex align-items-center gap-2"><span class="pk-icon {{ in_array($incident->priority, ['critical', 'high']) ? 'rose' : '' }}" style="width:36px;height:36px;font-size:1rem"><i class="bi {{ $incident->categoryIcon() }}"></i></span> Details</h2>
                    <div class="d-flex flex-wrap gap-2">@include('ops.partials.badges', ['incident' => $incident])</div>
                </div>
                <div class="kv-grid mb-4">
                    <div><div class="k">Location</div><div class="v">{{ $incident->location->name }}</div></div>
                    <div><div class="k">Service</div><div class="v">{{ $incident->service?->name ?? '—' }}</div></div>
                    <div><div class="k">Counter / equipment</div><div class="v">{{ $incident->counter ? 'Counter '.$incident->counter->number : '—' }}{{ $incident->equipment ? ' · '.$incident->equipment : '' }}</div></div>
                    <div><div class="k">Reported</div><div class="v"><time class="js-local" datetime="{{ $incident->reported_at?->toIso8601String() }}">{{ $incident->reported_at?->format('d M, H:i') }} UTC</time> <span class="pk-muted fw-semibold small">by {{ $incident->reporter?->name ?? 'Unknown' }}</span></div></div>
                    <div><div class="k">Technician</div><div class="v">{{ $incident->assigned_technician ?: 'Unassigned' }}</div></div>
                    <div><div class="k">Resolved</div><div class="v">@if($incident->resolved_at)<time class="js-local" datetime="{{ $incident->resolved_at->toIso8601String() }}">{{ $incident->resolved_at->format('d M, H:i') }} UTC</time>@else — @endif</div></div>
                </div>
                <div class="internal-box mb-3">
                    <div class="lbl"><i class="bi bi-lock-fill"></i> Internal description</div>
                    <div style="white-space:pre-line;overflow-wrap:anywhere">{{ $incident->description }}</div>
                </div>
                @if($incident->resolution_notes)
                    <div class="internal-box mb-3">
                        <div class="lbl"><i class="bi bi-lock-fill"></i> Resolution notes</div>
                        <div style="white-space:pre-line;overflow-wrap:anywhere">{{ $incident->resolution_notes }}</div>
                    </div>
                @endif
                @if($incident->photo_path)
                    <div class="mt-3">
                        <div class="small fw-bold pk-muted mb-2"><i class="bi bi-image me-1"></i>Photo (private)</div>
                        <a href="{{ route('ops.incidents.photo', $incident) }}" target="_blank" rel="noopener"><img src="{{ route('ops.incidents.photo', $incident) }}" alt="Photo attached to {{ $incident->reference() }}" class="inc-photo" loading="lazy"></a>
                    </div>
                @endif
            </section>

            <section class="pk-card p-4 mt-4 reveal" aria-labelledby="tlHead">
                <h2 id="tlHead" class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-clock-history pk-muted"></i> Activity</h2>
                <ol class="timeline">
                    @foreach($incident->updates as $u)
                        <li class="t-{{ $u->type }}">
                            <span class="dot" aria-hidden="true"></span>
                            <div class="head">
                                {{ $typeLabel[$u->type] ?? ucfirst($u->type) }}
                                @if($u->type === 'status') <span class="pk-muted fw-semibold">{{ str_replace('_', ' ', $u->from_status) }} → </span>{{ str_replace('_', ' ', $u->to_status) }}@endif
                            </div>
                            <div class="when">{{ $u->author?->name ?? 'System' }} · <time datetime="{{ $u->created_at?->toIso8601String() }}">{{ $u->created_at?->diffForHumans() }}</time></div>
                            @if($u->body)<div class="body">{{ $u->body }}</div>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <div class="col-lg-5">
            <section class="pk-card p-4 reveal" style="--d:.05s" aria-labelledby="updHead">
                <h2 id="updHead" class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-arrow-repeat pk-muted"></i> Update</h2>
                <form method="POST" action="{{ route('ops.incidents.update', $incident) }}" id="updForm">
                    @csrf
                    <fieldset class="mb-3">
                        <legend class="form-label">Status</legend>
                        <div class="status-steps">
                            @foreach(\App\Models\OperationalIncident::STATUSES as $s)
                                <input type="radio" class="btn-check" name="status" id="st-{{ $s }}" value="{{ $s }}" @checked($incident->status === $s) @disabled($s !== $incident->status && ! in_array($s, $allowed, true))>
                                <label class="btn" for="st-{{ $s }}">{{ ucfirst(str_replace('_', ' ', $s)) }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div class="mb-3">
                        <label class="form-label" for="tech">Technician</label>
                        <input id="tech" name="assigned_technician" class="form-control" maxlength="120" value="{{ old('assigned_technician', $incident->assigned_technician) }}" placeholder="Name or team">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="res">Resolution notes <span class="pk-muted fw-normal">(internal)</span></label>
                        <textarea id="res" name="resolution_notes" rows="2" class="form-control" maxlength="2000">{{ old('resolution_notes', $incident->resolution_notes) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="cmt">Add a comment <span class="pk-muted fw-normal">(internal)</span></label>
                        <textarea id="cmt" name="comment" rows="2" class="form-control" maxlength="1000" placeholder="e.g. Technician on the way, ETA 30 min"></textarea>
                    </div>
                    <div class="d-grid"><button class="btn btn-pk"><i class="bi bi-check2 me-1"></i>Save update</button></div>
                </form>
            </section>

            <section class="pk-card p-4 mt-4 reveal" style="--d:.1s" aria-labelledby="pubHead">
                <h2 id="pubHead" class="h6 fw-bold mb-1 d-flex align-items-center gap-2"><i class="bi bi-megaphone pk-muted"></i> Public notice</h2>
                @if(! $incident->isConfidential() && ! $incident->service_id && ! $incident->counter_id && ! $canLocationWide)
                    <div class="internal-box mt-3 d-flex gap-2">
                        <i class="bi bi-info-circle fs-5"></i>
                        <div class="small">This incident isn't linked to a service or counter, so a notice would cover the whole location — that needs an admin or the location-notice permission. You can still post a service notice in <a href="{{ route('ops.notices.index', ['location' => $incident->location_id]) }}">Public notices</a>.</div>
                    </div>
                @elseif($incident->isConfidential())
                    <div class="internal-box mt-3 d-flex gap-2">
                        <i class="bi bi-shield-lock fs-5"></i>
                        <div class="small"><b>This incident is internal.</b> It can never be published. If customers need to know something, write a separate generic notice in <a href="{{ route('ops.notices.index', ['location' => $incident->location_id]) }}">Public notices</a>.</div>
                    </div>
                @else
                    <p class="small pk-muted mb-3">Customers in the affected queue see this live and get a notification. Write it for the public — internal notes are never copied.</p>
                    <form method="POST" action="{{ route('ops.incidents.publish', $incident) }}" class="js-notice-form">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label" for="nTitle">Title</label>
                            <input id="nTitle" name="title" class="form-control js-n-title" maxlength="80" required value="{{ old('title', $suggestion['title']) }}">
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-end"><label class="form-label" for="nMsg">Message</label><span class="char-count js-n-count" aria-live="polite"></span></div>
                            <textarea id="nMsg" name="public_message" rows="3" class="form-control js-n-msg" maxlength="280" required>{{ old('public_message', $suggestion['message']) }}</textarea>
                            <div class="form-text">No names, phone numbers or ID numbers.</div>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label" for="nSev">Severity</label>
                                <select id="nSev" name="severity" class="form-select js-n-sev">
                                    @foreach(\App\Models\ServiceAnnouncement::SEVERITIES as $s)<option value="{{ $s }}" @selected(old('severity', $suggestion['severity']) === $s)>{{ ucfirst($s) }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="nEnd">Ends</label>
                                @php $endIso = old('ends_at', now()->addHours(4)->toIso8601String()); @endphp
                                <input id="nEnd" type="datetime-local" class="form-control js-local-input" data-target="nEndIso" data-iso="{{ $endIso }}">
                                <input type="hidden" name="ends_at" id="nEndIso" value="{{ $endIso }}">
                            </div>
                        </div>
                        <fieldset class="mb-3 d-grid gap-2">
                            <legend class="form-label">Who sees it</legend>
                            <label class="scope-opt"><input type="radio" name="scope" value="affected" checked>
                                <span class="box"><i class="bi bi-bullseye"></i><span><span class="t d-block">{{ $incident->service ? $incident->service->name : ($incident->counter ? 'Counter '.$incident->counter->number.' queue' : 'Affected service') }}</span><span class="d">Only the affected queue{{ $incident->service || $incident->counter ? '' : ' (no service set — this becomes location-wide)' }}</span></span></span></label>
                            <label class="scope-opt"><input type="radio" name="scope" value="location" @disabled(! $canLocationWide)>
                                <span class="box"><i class="bi bi-building"></i><span><span class="t d-block">Whole location</span><span class="d">{{ $canLocationWide ? 'Every queue at '.$incident->location->name : 'Needs admin or the location-notice permission' }}</span></span></span></label>
                        </fieldset>
                        <div class="small fw-bold pk-muted mb-2">Preview</div>
                        <div class="notice-preview sev-warning mb-3 js-n-preview" aria-hidden="true">
                            <span class="ic"><i class="bi bi-exclamation-triangle-fill"></i></span>
                            <div class="min-w-0"><div class="tt js-p-title"></div><div class="mm js-p-msg"></div></div>
                        </div>
                        <div class="d-grid"><button class="btn btn-pk"><i class="bi bi-broadcast me-1"></i>Publish notice</button></div>
                    </form>
                @endif

                @if($incident->announcements->isNotEmpty())
                    <div class="mt-4">
                        <div class="small fw-bold pk-muted mb-2">Linked notices</div>
                        @foreach($incident->announcements as $a)
                            <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-top small">
                                <span class="text-truncate"><i class="bi {{ $sevIcon[$a->severity] ?? 'bi-info-circle' }} me-1"></i>{{ $a->title }}</span>
                                <span class="chip flex-shrink-0">{{ ucfirst($a->displayStatus()) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/ops-console.js') }}?v={{ filemtime(public_path('js/modules/ops-console.js')) }}"></script>
@endpush
