{{--
    "Continue reading where you left off" — the signed-in user's most recent unfinished item.
    Progress is keyed by user + book (never by ticket), so this shows the same place in any queue.
    Optional vars: $progress (App\Models\ReadingProgress; resolved automatically if omitted),
                   $compact (bool), $showEmpty (bool, default true).
    Safe for guests and when nothing is in progress.
--}}
@auth
@include('library.partials._styles')
@php
    $lcProgress = $progress ?? app(\App\Services\Library\ReadingProgressService::class)->continueFor(auth()->user());
    $lcCompact = $compact ?? false;
@endphp
@if($lcProgress && $lcProgress->book)
    <div class="pk-card lib-continue {{ $lcCompact ? 'compact' : '' }}" data-lib-continue>
        <a href="{{ $lcProgress->resumeUrl() }}" class="d-block" tabindex="-1" aria-hidden="true">
            @include('library.partials.cover', ['book' => $lcProgress->book, 'size' => 'sm'])
        </a>
        <div class="flex-grow-1 min-w-0">
            @if($lcProgress->isListening())
                <div class="cc-eyebrow"><i class="bi bi-headphones"></i> Continue listening where you left off</div>
            @else
                <div class="cc-eyebrow"><i class="bi bi-bookmark-star-fill"></i> Continue reading where you left off</div>
            @endif
            <div class="cc-title text-truncate" @if($lcProgress->book->language === 'ne') lang="ne" @endif>{{ $lcProgress->book->title }}</div>
            <div class="cc-place text-truncate">{{ $lcProgress->placeLabel() }}</div>
            <div class="d-flex align-items-center gap-2 mt-2">
                <div class="progress flex-grow-1" role="progressbar" aria-label="Overall progress" aria-valuenow="{{ (int) $lcProgress->overall_percent }}" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: {{ (int) $lcProgress->overall_percent }}%"></div>
                </div>
                <span class="small fw-bold pk-muted">{{ (int) $lcProgress->overall_percent }}%</span>
            </div>
            <div class="small pk-muted mt-1">{{ $lcProgress->isListening() ? 'Last listened' : 'Last read' }} {{ $lcProgress->last_read_at?->diffForHumans() }}</div>
        </div>
        <div class="cc-actions">
            <a href="{{ $lcProgress->resumeUrl() }}" class="btn btn-pk {{ $lcCompact ? 'btn-sm' : '' }}"><i class="bi {{ $lcProgress->isListening() ? 'bi-headphones' : 'bi-book-half' }} me-1"></i>{{ $lcProgress->isListening() ? 'Resume listening' : 'Resume' }}</a>
        </div>
    </div>
@elseif($showEmpty ?? true)
    <div class="pk-card lib-continue {{ $lcCompact ? 'compact' : '' }}">
        <span class="pk-icon violet"><i class="bi bi-book"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="cc-title">Nothing in progress yet</div>
            <div class="cc-place">Start a short story — your exact place is saved and follows you to any queue.</div>
        </div>
        <div class="cc-actions"><a href="{{ route('library.home') }}" class="btn btn-ghost {{ $lcCompact ? 'btn-sm' : '' }}">Browse the library</a></div>
    </div>
@endif
@endauth
