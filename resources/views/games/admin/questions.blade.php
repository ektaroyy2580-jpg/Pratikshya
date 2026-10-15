@extends('layouts.app')
@section('title', 'Question bank — Admin — प्रतीक्षा')

@push('head')
<link rel="stylesheet" href="{{ asset('css/modules/games.css') }}?v={{ filemtime(public_path('css/modules/games.css')) }}">
@endpush

@section('content')
<header class="pk-page-head gm-head">
    <div class="container">
        <nav class="gm-crumbs mb-3 fade-up" aria-label="Breadcrumb">
            <a href="{{ route('admin') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left me-1"></i>Admin</a>
            <i class="bi bi-chevron-right sep" aria-hidden="true"></i>
            <a href="{{ route('games.hub') }}" class="pk-breadcrumb">Game hub</a>
        </nav>
        <span class="pk-eyebrow fade-up"><i class="bi bi-ui-checks"></i> Games admin</span>
        <h1 class="mt-2 mb-2 fade-up">Question bank</h1>
        <p class="lead mb-0 fade-up">Approve, disable or generate quiz questions. Only approved questions are used in battles.</p>
    </div>
</header>

<div class="container py-4 py-md-5">
    <div class="row g-3 mb-4">
        @foreach([
            ['Approved', $counts['approved'] ?? 0, 'bi-check2-circle', 'green'],
            ['Pending review', $counts['pending'] ?? 0, 'bi-hourglass-split', 'amber'],
            ['Disabled', $counts['disabled'] ?? 0, 'bi-slash-circle', 'rose'],
            ['Battles completed', $stats['completed'], 'bi-flag', ''],
        ] as [$label, $value, $icon, $tone])
            <div class="col-6 col-md-3">
                <div class="pk-card p-3 d-flex align-items-center gap-3 h-100 reveal" style="--d:{{ $loop->index * 0.05 }}s">
                    <span class="pk-icon {{ $tone }}"><i class="bi {{ $icon }}"></i></span>
                    <div><div class="h4 mb-0">{{ number_format($value) }}</div><div class="gm-sub small">{{ $label }}</div></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-lg-4 order-lg-2">
            <section class="pk-card p-3 p-md-4" aria-labelledby="genTitle">
                <h2 class="gm-section-title mb-1" id="genTitle"><i class="bi bi-magic me-1 text-primary"></i> Generate with AI</h2>
                <p class="small mb-3">
                    <span class="chip {{ $aiEnabled ? '' : '' }}" style="{{ $aiEnabled ? 'background:var(--pk-success-tint);color:var(--pk-success)' : 'background:#eef0f6;color:var(--pk-muted)' }}">
                        <i class="bi {{ $aiEnabled ? 'bi-cpu' : 'bi-plug' }}"></i> AI provider: {{ $providerLabel }}
                    </span>
                </p>
                @unless($aiEnabled)
                    <div class="gm-alert warn small mb-3"><i class="bi bi-info-circle"></i><div>AI is switched off, so battles use the curated local bank. To enable, set <code>AI_PROVIDER</code> in <code>.env</code> (see <code>config/ai.php</code>).</div></div>
                @endunless
                <form method="POST" action="{{ route('games.admin.questions.generate') }}">
                    @csrf
                    <label class="form-label" for="gCat">Topic</label>
                    <select class="form-select mb-3" id="gCat" name="category">
                        @foreach(\App\Models\GameQuestion::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label" for="gDiff">Difficulty</label>
                            <select class="form-select" id="gDiff" name="difficulty">
                                @foreach(\App\Models\GameQuestion::DIFFICULTIES as $d)<option value="{{ $d }}">{{ ucfirst($d) }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label" for="gCount">How many</label>
                            <input class="form-control" type="number" id="gCount" name="count" min="1" max="5" value="3">
                        </div>
                    </div>
                    <button class="btn btn-pk w-100" type="submit" @disabled(! $aiEnabled)><i class="bi bi-stars me-1"></i>Generate for review</button>
                    <p class="gm-sub small mt-2 mb-0">Output is checked against a strict schema (≤200 chars, 4 options, one answer, explanation). Valid items arrive as <b>pending</b>; nothing personal is sent to the AI.</p>
                </form>
            </section>
        </div>

        <div class="col-lg-8 order-lg-1">
            <form method="GET" class="d-flex flex-wrap gap-2 mb-3" aria-label="Filter questions">
                <select name="status" class="form-select form-select-sm w-auto" style="min-width:150px;padding-right:2.2rem" aria-label="Status">
                    <option value="">All statuses</option>
                    @foreach(\App\Models\GameQuestion::STATUSES as $s)<option value="{{ $s }}" @selected($filters['status'] === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
                <select name="category" class="form-select form-select-sm w-auto" style="min-width:150px;padding-right:2.2rem" aria-label="Topic">
                    <option value="">All topics</option>
                    @foreach(\App\Models\GameQuestion::CATEGORIES as $k => $l)<option value="{{ $k }}" @selected($filters['category'] === $k)>{{ $l }}</option>@endforeach
                </select>
                <select name="source" class="form-select form-select-sm w-auto" style="min-width:150px;padding-right:2.2rem" aria-label="Source">
                    <option value="">All sources</option>
                    <option value="bank" @selected($filters['source'] === 'bank')>Local bank</option>
                    <option value="ai" @selected($filters['source'] === 'ai')>AI</option>
                </select>
                <button class="btn btn-ghost btn-sm">Filter</button>
            </form>

            <section class="pk-card p-2 p-md-3">
                @if($questions->isEmpty())
                    <div class="gm-empty m-2"><i class="bi bi-inbox"></i>No questions match these filters.</div>
                @else
                    <div class="table-responsive">
                        <table class="table gm-qtable mb-0">
                            <thead><tr><th>Question</th><th>Topic</th><th>Status</th><th class="text-end">Action</th></tr></thead>
                            <tbody>
                                @foreach($questions as $q)
                                    <tr>
                                        <td>
                                            <div class="q">{{ $q->question }}</div>
                                            <div class="opts mt-1">
                                                @foreach($q->options as $i => $o)<span class="{{ $i === $q->answer_index ? 'right' : '' }}">{{ ['A','B','C','D'][$i] }}. {{ $o }}</span>@if(! $loop->last) · @endif @endforeach
                                            </div>
                                            <div class="opts mt-1"><i class="bi bi-lightbulb me-1"></i>{{ $q->explanation }}</div>
                                        </td>
                                        <td class="text-nowrap"><span class="chip">{{ $q->categoryLabel() }}</span><div class="gm-sub small mt-1 text-capitalize">{{ $q->difficulty }} · {{ $q->source === 'ai' ? 'AI' : 'Bank' }} · used {{ $q->times_used }}×</div></td>
                                        <td><span class="pk-status {{ ['approved' => 'called', 'pending' => 'waiting', 'disabled' => 'done'][$q->status] ?? 'done' }}">{{ ucfirst($q->status) }}</span></td>
                                        <td class="text-end text-nowrap">
                                            @if($q->status !== 'approved')
                                                <form method="POST" action="{{ route('games.admin.questions.status', $q) }}" class="d-inline">@csrf<input type="hidden" name="status" value="approved"><button class="btn btn-pk btn-sm">Approve</button></form>
                                            @endif
                                            @if($q->status !== 'disabled')
                                                <form method="POST" action="{{ route('games.admin.questions.status', $q) }}" class="d-inline">@csrf<input type="hidden" name="status" value="disabled"><button class="btn btn-ghost btn-sm">Disable</button></form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 px-2">{{ $questions->links('pagination::bootstrap-5') }}</div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection
