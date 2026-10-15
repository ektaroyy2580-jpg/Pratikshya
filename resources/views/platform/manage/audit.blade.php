@extends('platform.layouts.console')
@section('title', 'Audit log — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-journal-text"></i> Platform</span>
        <h1>Audit log</h1>
        <p>Read-only record of security-relevant and administrative actions.</p>
    </div>
</div>

<section class="pk-card pf-panel reveal" aria-label="Audit entries">
    <div class="pf-panel-head">
        <form method="GET" class="pf-filter w-100" role="search">
            <label class="visually-hidden" for="aAction">Action</label>
            <select id="aAction" name="action" class="form-select" style="max-width:220px">
                <option value="">All actions</option>
                @foreach($actions as $a)<option value="{{ $a }}" @selected(($filters['action'] ?? null) === $a)>{{ $a }}</option>@endforeach
            </select>
            <label class="visually-hidden" for="aActor">Actor</label>
            <input id="aActor" class="form-control" style="max-width:180px" name="actor" value="{{ $filters['actor'] ?? '' }}" placeholder="Actor name/email">
            <label class="visually-hidden" for="aSub">Subject type</label>
            <input id="aSub" class="form-control" style="max-width:150px" name="subject" value="{{ $filters['subject'] ?? '' }}" placeholder="Subject (e.g. User)">
            <label class="visually-hidden" for="aFrom">From</label>
            <input id="aFrom" class="form-control" style="max-width:160px" type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            <label class="visually-hidden" for="aTo">To</label>
            <input id="aTo" class="form-control" style="max-width:160px" type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            <button class="btn btn-ghost" data-no-busy><i class="bi bi-funnel me-1"></i>Filter</button>
            @if(array_filter($filters))<a href="{{ route('platform.manage.audit') }}" class="small fw-semibold">Clear</a>@endif
        </form>
    </div>
    @if($logs->isEmpty())
        <div class="pf-empty"><span class="pk-icon mb-2"><i class="bi bi-journal"></i></span><h3 class="h6 mb-1">No entries</h3><p class="pk-muted small mb-0">Nothing matches these filters.</p></div>
    @else
        <div class="table-responsive">
            <table class="table pf-tbl pf-stack">
                <thead><tr><th>When</th><th>Action</th><th>Actor</th><th>Subject</th><th>Details</th></tr></thead>
                <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td class="small text-nowrap"><span title="{{ $log->created_at }}">{{ $log->created_at?->format('d M, H:i') }}</span><div class="sub">{{ $log->created_at?->diffForHumans() }}</div></td>
                        <td data-label="Action"><span class="pf-act">{{ $log->action }}</span></td>
                        <td data-label="Actor" class="small">{{ $log->actor?->name ?? 'System' }}</td>
                        <td data-label="Subject" class="small">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td data-label="Details" class="pf-mono pk-muted" style="max-width:300px;word-break:break-word">{{ $log->meta ? \Illuminate\Support\Str::limit(json_encode($log->meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 160) : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())<div class="pf-pager">{{ $logs->links('pagination::bootstrap-5') }}</div>@endif
    @endif
</section>
@endsection
