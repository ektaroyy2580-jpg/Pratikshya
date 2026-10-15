{{--
    Staff console card. Usage: @include('integrity.partials.staff-card', ['service' => $service])
    Shows exchange approvals for this service and the "Report duplicate-token suspicion" form.
    Renders nothing unless the signed-in user operates the service's location.
--}}
@php
    $igSvc = $service ?? null;
    $igUser = auth()->user();
    $igShow = $igSvc && $igUser && in_array($igUser->role, ['staff', 'admin'], true) && $igUser->canOperateLocation((int) $igSvc->location_id);
    if ($igShow) {
        $igApprovals = \App\Models\QueueExchangeRequest::with('service', 'requesterTicket', 'targetTicket')
            ->where('service_id', $igSvc->id)->where('status', 'awaiting_staff')->where('expires_at', '>', now())->orderBy('expires_at')->get();
        $igActive = $igSvc->tickets()->whereIn('status', \App\Models\Ticket::ACTIVE)->orderBy('number')->get(['id', 'number', 'status', 'service_id']);
        $igReasons = \App\Http\Requests\Integrity\StaffReportRequest::REASONS;
    }
@endphp
@if($igShow)
    @once
        @push('head')
            <link rel="stylesheet" href="{{ asset('css/modules/integrity.css') }}?v={{ @filemtime(public_path('css/modules/integrity.css')) }}">
        @endpush
        @push('scripts')
            <script src="{{ asset('js/modules/integrity.js') }}?v={{ @filemtime(public_path('js/modules/integrity.js')) }}"></script>
        @endpush
    @endonce
    <section class="pk-card ig-widget" aria-labelledby="igStaffHead{{ $igSvc->id }}">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <h3 id="igStaffHead{{ $igSvc->id }}" class="d-flex align-items-center gap-2">
                <span class="pk-icon violet" style="width:32px;height:32px;font-size:.9rem"><i class="bi bi-shield-check"></i></span> Queue integrity
            </h3>
            <a href="{{ route('integrity.staff.exchanges') }}" class="small fw-semibold text-decoration-none">Exchanges <i class="bi bi-chevron-right"></i></a>
        </div>

        <div class="mb-3">
            <div class="small fw-bold mb-2">Exchange approvals
                @if($igSvc->requires_exchange_approval)<span class="chip ms-1 py-0 px-2">required here</span>@endif
                @unless($igSvc->allow_exchange)<span class="chip ig-muted-chip ms-1 py-0 px-2">exchanges off</span>@endunless
            </div>
            @forelse($igApprovals as $x)
                <div class="mb-2">@include('integrity.partials._approval', ['x' => $x])</div>
            @empty
                <div class="ig-note"><i class="bi bi-check2-all me-1"></i>Nothing waiting for approval.</div>
            @endforelse
        </div>

        <details class="border-top pt-3">
            <summary class="small fw-bold" style="cursor:pointer">Report duplicate-token suspicion</summary>
            @if($igActive->isEmpty())
                <div class="ig-note mt-2">No active tokens in this queue right now.</div>
            @else
                <form method="POST" action="{{ route('integrity.staff.report', $igActive->first()) }}" class="mt-3" id="igReport{{ $igSvc->id }}" data-ig-once>
                    @csrf
                    <div class="row g-2">
                        <div class="col-sm-5">
                            <label class="form-label small mb-1" for="igRepTicket{{ $igSvc->id }}">Token</label>
                            <select id="igRepTicket{{ $igSvc->id }}" class="form-select form-select-sm"
                                    onchange="this.form.action = this.selectedOptions[0].dataset.action">
                                @foreach($igActive as $t)
                                    <option value="{{ $t->id }}" data-action="{{ route('integrity.staff.report', $t) }}">{{ $igSvc->token($t->number) }} · {{ $t->status }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-7">
                            <label class="form-label small mb-1" for="igRepReason{{ $igSvc->id }}">What did you notice?</label>
                            <select id="igRepReason{{ $igSvc->id }}" name="reason" class="form-select form-select-sm" required>
                                @foreach($igReasons as $k => $label)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small mb-1" for="igRepNote{{ $igSvc->id }}">Facts observed</label>
                            <textarea id="igRepNote{{ $igSvc->id }}" name="note" rows="2" class="form-control form-control-sm @error('note') is-invalid @enderror" minlength="10" maxlength="500" required
                                      placeholder="e.g. Same visitor presented D-24 and D-27 at the desk.">{{ old('note') }}</textarea>
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-2">
                        <span class="ig-note"><i class="bi bi-lock me-1"></i>Goes to admin review only. The token is not changed.</span>
                        <button type="submit" class="btn btn-ghost btn-sm" data-busy="Sending…"><i class="bi bi-flag me-1"></i>Send report</button>
                    </div>
                </form>
            @endif
        </details>
    </section>
@endif
