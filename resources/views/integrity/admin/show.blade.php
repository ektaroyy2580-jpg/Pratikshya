@extends('layouts.app')
@section('title', 'Alert #'.$alert->id.' · Dalal AI — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/integrity.css') }}?v={{ @filemtime(public_path('css/modules/integrity.css')) }}">
@endpush

@section('content')
@php
    $sevIcons = ['low' => 'bi-info-circle', 'medium' => 'bi-exclamation-triangle', 'high' => 'bi-exclamation-octagon'];
    $facts = collect($alert->evidence['facts'] ?? []);
    $factLabels = ['count' => 'Occurrences', 'window_minutes' => 'Window (min)', 'threshold' => 'Rule threshold', 'idempotent_retries' => 'Safe browser retries',
        'services' => 'Services involved', 'account_age_hours' => 'Account age limit (h)', 'completed_exchanges' => 'Completed exchanges', 'absent' => 'Marked absent',
        'cancelled' => 'Cancelled', 'baseline_mean' => 'Usual per window', 'baseline_std' => 'Usual spread', 'z_score' => 'Z-score',
        'baseline_buckets' => 'Baseline windows', 'active_tokens_same_service' => 'Active tokens (same service)'];
    $u = $alert->subjectUser;
    $maskEmail = function (?string $e) {
        if (! $e || ! str_contains($e, '@')) return '—';
        [$l, $d] = explode('@', $e, 2);
        return mb_substr($l, 0, 2).str_repeat('•', max(1, min(6, mb_strlen($l) - 2))).'@'.$d;
    };
    $timeline = $signals->map(fn ($s) => ['at' => $s->created_at, 'kind' => 'sig', 'icon' => 'bi-activity', 'what' => $s->label(),
            'detail' => collect([$s->ticket?->service ? $s->ticket->service->token($s->ticket->number) : null, $s->actor && $s->actor_id !== $s->user_id ? 'by '.$s->actor->name.' ('.$s->actor_role.')' : null, $s->ua_family])->filter()->implode(' · ')])
        ->concat($queueEvents->map(fn ($e) => ['at' => $e->created_at, 'kind' => 'evt', 'icon' => 'bi-ticket-perforated', 'what' => 'Queue event: '.str_replace('_', ' ', $e->type),
            'detail' => collect([$e->from_status && $e->to_status && $e->from_status !== $e->to_status ? $e->from_status.' → '.$e->to_status : null, $e->actor ? 'by '.$e->actor->name : null])->filter()->implode(' · ')]))
        ->sortByDesc('at')->values();
    $isTemplate = $alert->explanation_provider === \App\Services\Integrity\ExplanationService::TEMPLATE_PROVIDER;
    $decisions = [
        'dismiss' => ['bi-check2-circle', 'Dismiss', 'False positive · no issue'],
        'reviewing' => ['bi-hourglass-split', 'Reviewing', 'Mark as being looked at'],
        'confirm' => ['bi-patch-check', 'Confirm', 'Issue confirmed after review'],
        'escalate' => ['bi-arrow-up-right-circle', 'Escalate', 'Needs a supervisor'],
    ];
@endphp
<header class="pk-page-head ig-head">
    <div class="container">
        <a href="{{ route('integrity.admin.index') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Integrity review</a>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-2">
            <div class="d-flex align-items-center gap-3 fade-up ig-min-0">
                <span class="ig-ring lg {{ $alert->severity }}" style="--v: {{ $alert->risk_score }}" role="img" aria-label="Risk score {{ $alert->risk_score }} of 100"><span>{{ $alert->risk_score }}</span></span>
                <div class="ig-min-0">
                    <span class="pk-eyebrow mb-1"><i class="bi bi-shield-shaded"></i> Alert #{{ $alert->id }} · {{ str_replace('_', ' ', $alert->rule) }}</span>
                    <h1 class="mb-2">{{ $alert->title() }}</h1>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="ig-sev {{ $alert->severity }}"><i class="bi {{ $sevIcons[$alert->severity] ?? 'bi-dot' }}"></i>{{ ucfirst($alert->severity) }} severity</span>
                        <span class="ig-st {{ $alert->status }}">{{ ucfirst($alert->status) }}</span>
                        @if(! empty($alert->evidence['demo']))<span class="chip ig-muted-chip">Demo data</span>@endif
                    </div>
                </div>
            </div>
            <div class="ig-note fade-up text-lg-end" style="animation-delay:.05s">
                First seen {{ $alert->first_seen_at?->format('d M, H:i') ?? '—' }}<br>Last seen {{ $alert->last_seen_at?->diffForHumans() ?? '—' }}
            </div>
        </div>
    </div>
</header>

<div class="container py-4 py-lg-5">
    @if($errors->any() && ! $errors->has('queue'))
        <div class="alert alert-danger d-flex gap-2 align-items-start fade-up" role="alert"><i class="bi bi-exclamation-circle-fill mt-1"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="row g-4">
        <div class="col-lg-8">
            {{-- Why --}}
            <section class="pk-card ig-explain p-4 mb-4 reveal" aria-labelledby="whyHead">
                <div class="ig-sec-head mb-2">
                    <h2 id="whyHead"><i class="bi bi-chat-square-text pk-muted"></i> Why this was flagged</h2>
                    <span class="ig-provider {{ $isTemplate ? 'tpl' : '' }}"><i class="bi {{ $isTemplate ? 'bi-file-text' : 'bi-stars' }}"></i>{{ $alert->explanation_provider ?? 'template' }}</span>
                </div>
                <p class="mb-3" style="font-size:1.02rem; line-height:1.6">{{ $alert->explanation }}</p>
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="ig-note"><i class="bi bi-lock me-1"></i>AI wording receives only the rule name, counts and time spans. No names, emails, IPs or tokens.</span>
                    @if($aiEnabled)
                        <form method="POST" action="{{ route('integrity.admin.explain', $alert) }}" data-ig-once>
                            @csrf
                            <button type="submit" class="btn btn-ghost btn-sm" data-busy="Asking AI…"><i class="bi bi-stars me-1"></i>{{ $isTemplate ? 'Reword with AI' : 'Regenerate' }}</button>
                        </form>
                    @else
                        <span class="chip ig-muted-chip"><i class="bi bi-plug"></i> AI off · deterministic text</span>
                    @endif
                </div>
            </section>

            {{-- Evidence --}}
            <section class="pk-card p-4 mb-4 reveal" style="--d:.04s" aria-labelledby="evHead">
                <div class="ig-sec-head"><h2 id="evHead"><i class="bi bi-clipboard-data pk-muted"></i> Evidence</h2><span class="ig-note">{{ $alert->signal_count }} {{ \Illuminate\Support\Str::plural('signal', $alert->signal_count) }}</span></div>
                <div class="ig-facts mb-3">
                    @foreach($facts as $k => $v)
                        <div class="ig-fact"><div class="k">{{ $factLabels[$k] ?? str_replace('_', ' ', $k) }}</div><div class="v">{{ is_scalar($v) ? $v : json_encode($v) }}</div></div>
                    @endforeach
                </div>
                @php $extra = collect($alert->evidence ?? [])->except(['facts', 'signal_ids', 'demo']); @endphp
                @if($extra->isNotEmpty())
                    <dl class="row small mb-0">
                        @foreach($extra as $k => $v)
                            <dt class="col-sm-4 pk-muted fw-semibold">{{ ucfirst(str_replace('_', ' ', $k)) }}</dt>
                            <dd class="col-sm-8 mb-2" style="word-break:break-word">
                                @if(is_array($v))
                                    @foreach($v as $kk => $vv)<span class="chip ig-muted-chip me-1 mb-1">{{ is_int($kk) ? '' : str_replace('_', ' ', $kk).': ' }}{{ is_scalar($vv) ? $vv : json_encode($vv) }}</span>@endforeach
                                @else
                                    {{ $v }}
                                @endif
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </section>

            {{-- Timeline --}}
            <section class="pk-card p-4 mb-4 reveal" style="--d:.08s" aria-labelledby="tlHead">
                <div class="ig-sec-head"><h2 id="tlHead"><i class="bi bi-list-ol pk-muted"></i> Event timeline</h2><span class="ig-note">newest first</span></div>
                @if($timeline->isEmpty())
                    <div class="ig-note">The underlying signals were pruned after {{ config('integrity.retention_days') }} days. Counts above are kept.</div>
                @else
                    <ol class="ig-timeline">
                        @foreach($timeline as $e)
                            <li>
                                <span class="dot {{ $e['kind'] }}"><i class="bi {{ $e['icon'] }}"></i></span>
                                <div class="ig-min-0">
                                    <div class="what">{{ $e['what'] }}</div>
                                    <div class="when">{{ $e['at']?->format('d M Y, H:i:s') }}@if($e['detail']) · {{ $e['detail'] }}@endif</div>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            {{-- Recommended --}}
            <section class="pk-card p-4 reveal" style="--d:.1s" aria-labelledby="recHead">
                <div class="ig-sec-head"><h2 id="recHead"><i class="bi bi-signpost-2 pk-muted"></i> Recommended review steps</h2></div>
                <ul class="ig-actions-list">
                    @foreach($alert->recommended_actions ?? [] as $step)
                        <li><i class="bi bi-check2-square"></i><span>{{ $step }}</span></li>
                    @endforeach
                </ul>
            </section>
        </div>

        <aside class="col-lg-4">
            <div class="ig-sticky">
                {{-- Review --}}
                <section class="pk-card p-4 mb-4 reveal" aria-labelledby="rvHead">
                    <h2 id="rvHead" class="h6 fw-bold mb-3"><i class="bi bi-person-check me-1"></i> Your review</h2>
                    @if($alert->reviewed_at)
                        <div class="ig-principles mb-3">
                            <div class="fw-bold mb-1">{{ ucfirst(str_replace('_', ' ', $alert->review_outcome)) }}</div>
                            <div>“{{ $alert->review_note }}”</div>
                            <div class="ig-note mt-1">{{ $alert->reviewer?->name ?? 'Admin' }} · {{ $alert->reviewed_at->diffForHumans() }}</div>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('integrity.admin.review', $alert) }}" data-ig-once>
                        @csrf
                        <fieldset>
                            <legend class="form-label mb-2">Decision</legend>
                            <div class="ig-decisions mb-3">
                                @foreach($decisions as $key => [$icon, $label, $hint])
                                    <div class="ig-decision {{ $key }}">
                                        <input type="radio" name="decision" id="dec-{{ $key }}" value="{{ $key }}" @checked(old('decision', $alert->status === 'open' ? 'dismiss' : null) === $key) required>
                                        <label for="dec-{{ $key }}"><i class="bi {{ $icon }}"></i><span><b>{{ $label }}</b><small>{{ $hint }}</small></span></label>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                        <label for="rvNote" class="form-label">Review note <span class="text-danger">*</span></label>
                        <textarea id="rvNote" name="note" rows="3" class="form-control @error('note') is-invalid @enderror" minlength="5" maxlength="1000" required
                                  placeholder="What you checked and why you decided this.">{{ old('note') }}</textarea>
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-pk w-100 mt-3" data-busy="Saving…"><i class="bi bi-save2 me-1"></i>Record decision</button>
                        <div class="ig-note mt-2 text-center">Saved to the audit log with your name.</div>
                    </form>
                </section>

                {{-- Subject --}}
                <section class="pk-card p-4 mb-4 reveal" style="--d:.04s" aria-labelledby="sjHead">
                    <h2 id="sjHead" class="h6 fw-bold mb-3"><i class="bi bi-person-vcard me-1"></i> Subject</h2>
                    <dl class="row small mb-0">
                        @if($u)
                            <dt class="col-5 pk-muted fw-semibold">Account</dt><dd class="col-7">#{{ $u->id }} · {{ $u->name }}</dd>
                            <dt class="col-5 pk-muted fw-semibold">Email</dt><dd class="col-7 ig-mono">{{ $maskEmail($u->email) }}</dd>
                            <dt class="col-5 pk-muted fw-semibold">Role</dt><dd class="col-7">{{ ucfirst($u->role) }}</dd>
                            <dt class="col-5 pk-muted fw-semibold">Account age</dt><dd class="col-7">{{ $u->created_at?->diffForHumans(null, true) ?? '—' }}</dd>
                        @else
                            <dt class="col-5 pk-muted fw-semibold">Account</dt><dd class="col-7">No single account (pattern across accounts)</dd>
                        @endif
                        <dt class="col-5 pk-muted fw-semibold">Service</dt><dd class="col-7">{{ $alert->subjectService?->name ?? '—' }}@if($alert->subjectService?->location)<br><span class="ig-note">{{ $alert->subjectService->location->name }}</span>@endif</dd>
                        @if($alert->subjectTicket)
                            <dt class="col-5 pk-muted fw-semibold">Token</dt><dd class="col-7">{{ $alert->subjectTicket->token() }} · {{ $alert->subjectTicket->status }}</dd>
                        @endif
                        @if($alert->reporter)
                            <dt class="col-5 pk-muted fw-semibold">Reported by</dt><dd class="col-7">{{ $alert->reporter->name }}</dd>
                        @endif
                    </dl>
                </section>

                @if($related->isNotEmpty())
                    <section class="pk-card p-4 mb-4 reveal" style="--d:.06s" aria-labelledby="rlHead">
                        <h2 id="rlHead" class="h6 fw-bold mb-2">Other alerts for this account</h2>
                        @foreach($related as $r)
                            <a href="{{ route('integrity.admin.show', $r) }}" class="d-flex justify-content-between align-items-center gap-2 py-2 border-top text-decoration-none" style="color:inherit">
                                <span class="small fw-semibold text-truncate">{{ $r->title() }}</span><span class="ig-st {{ $r->status }}">{{ ucfirst($r->status) }}</span>
                            </a>
                        @endforeach
                    </section>
                @endif

                <section class="pk-card p-4 mb-4 reveal" style="--d:.08s" aria-labelledby="hsHead">
                    <h2 id="hsHead" class="h6 fw-bold mb-2">Review history</h2>
                    @forelse($reviewLog as $log)
                        <div class="ig-hist">
                            <i class="bi bi-journal-check pk-muted"></i>
                            <div class="ig-min-0">
                                <div class="fw-semibold small">{{ str_replace(['integrity_alert_', 'integrity_', '_'], ['', '', ' '], $log->action) }}</div>
                                <div class="ig-note">{{ $log->actor?->name ?? 'System' }} · {{ $log->created_at?->diffForHumans() }}@if(! empty($log->meta['note'])) · “{{ \Illuminate\Support\Str::limit($log->meta['note'], 90) }}”@endif</div>
                            </div>
                        </div>
                    @empty
                        <div class="ig-note">Not reviewed yet.</div>
                    @endforelse
                </section>

                <div class="ig-principles reveal">
                    <i class="bi bi-shield-check me-1"></i>A risk score is a prompt to look, not a finding. Shared networks, devices, names or locations are never conclusive. Talk to the person at the counter before any change to their token.
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/integrity.js') }}?v={{ @filemtime(public_path('js/modules/integrity.js')) }}"></script>
@endpush
