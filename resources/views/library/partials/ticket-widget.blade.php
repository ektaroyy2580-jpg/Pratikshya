{{--
    E-Library widget for the ticket page / dashboard.
    Optional: $ticket (App\Models\Ticket) — used only to suggest reads that fit the estimated wait.
    Reading never changes queue position: this widget only reads library data.
--}}
@auth
@php
    $lwMinutes = null;
    try {
        if (isset($ticket) && $ticket && method_exists($ticket, 'isActive') && $ticket->isActive()) {
            $lwMinutes = (int) ($ticket->ahead() * max(1, (int) $ticket->service->avg_minutes));
        }
    } catch (\Throwable $e) {
        $lwMinutes = null;
    }
    $lwFit = max(3, min(15, $lwMinutes ?? 6));
    $lwPicks = \App\Models\Book::query()->active()->where('reading_minutes', '<=', $lwFit)
        ->orderByDesc('is_featured')->inRandomOrder()->limit(3)->get();
@endphp
@include('library.partials._styles')
<section class="pk-card p-3 p-md-4" aria-labelledby="libWidgetTitle">
    <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
        <h2 id="libWidgetTitle" class="lib-section-title"><i class="bi bi-book-half"></i> Read while you wait</h2>
        <a href="{{ route('library.home') }}" class="lib-see-all">E-Library <i class="bi bi-arrow-right"></i></a>
    </div>
    @include('library.partials.continue-card', ['compact' => true])
    @if($lwPicks->isNotEmpty())
        <div class="small fw-bold pk-muted mt-3 mb-1">{{ $lwMinutes ? 'Fits your wait (about '.$lwMinutes.' min)' : 'Quick reads' }}</div>
        <div>
            @foreach($lwPicks as $pick)
                <div class="lib-row">
                    @include('library.partials.cover', ['book' => $pick, 'size' => 'sm'])
                    <div class="min-w-0 flex-grow-1">
                        <a class="r-t d-block text-truncate" href="{{ route('library.book', $pick) }}" @if($pick->language === 'ne') lang="ne" @endif>{{ $pick->title }}</a>
                        <div class="r-m">{{ $pick->typeLabel() }} · {{ $pick->readingTimeLabel() }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    <p class="small pk-muted mb-0 mt-2"><i class="bi bi-shield-check me-1"></i>Reading never changes your place in line. Your page is saved to your account, so it carries over to any queue or device.</p>
</section>
@endauth
