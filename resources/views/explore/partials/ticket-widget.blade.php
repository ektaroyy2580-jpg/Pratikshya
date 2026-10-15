{{--
    Explore widget for the ticket page. Needs: $ticket (App\Models\Ticket). Safe when missing.
    Shows the top 3 neutral recommendations that fit the remaining wait, with dismiss buttons.
    Include with: @includeIf('explore.partials.ticket-widget', ['ticket' => $ticket])
--}}
@php
    $exUser = auth()->user();
    $exOk = isset($ticket) && $ticket instanceof \App\Models\Ticket && $exUser && $ticket->user_id === $exUser->id
        && \Illuminate\Support\Facades\Route::has('explore.index');
    if ($exOk) {
        try {
            $exSvc = app(\App\Services\Explore\RecommendationService::class);
            $exLoc = $ticket->service?->location;
            $exRem = $exSvc->remainingFor($ticket->isActive() ? $ticket : null);
            $exRec = $exLoc ? $exSvc->recommend($exUser, $exLoc, $exRem['minutes'], null, 3) : null;
        } catch (\Throwable $e) {
            report($e);
            $exRec = null;
        }
        $exOk = $exRec !== null;
    }
@endphp
@if($exOk)
    @once
        <link rel="stylesheet" href="{{ asset('css/modules/explore.css') }}?v={{ filemtime(public_path('css/modules/explore.css')) }}">
    @endonce
    <div class="pk-card p-4" id="exploreWidget">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div>
                <span class="pk-eyebrow"><i class="bi bi-compass"></i>While you wait</span>
                <h2 class="h6 mb-0 mt-1">
                    @if($exRem['minutes'] !== null && $exRem['status'] === 'waiting')
                        You have approximately {{ $exRem['minutes'] }} minutes
                    @else
                        Nearby places
                    @endif
                </h2>
            </div>
            <a href="{{ route('explore.index', ['location' => $exLoc->id]) }}" class="btn btn-ghost btn-sm">Open map</a>
        </div>
        @forelse($exRec['items'] as $it)
            <div class="exw-item" style="--c: {{ $it['color'] }}">
                @if(! empty($it['image']))
                    <span class="ico photo"><i class="bi {{ $it['icon'] }}"></i><img src="{{ $it['image'] }}" alt="" loading="lazy" decoding="async" onload="this.classList.add('is-in')" onerror="this.remove()"></span>
                @else
                    <span class="ico"><i class="bi {{ $it['icon'] }}"></i></span>
                @endif
                <div class="flex-grow-1 min-w-0">
                    <a href="{{ $it['url'] }}" class="fw-bold text-decoration-none d-block text-truncate">{{ $it['name'] }}</a>
                    <div class="small pk-muted">{{ $it['explanation'] }}</div>
                    @if(! empty($it['is_cafe']))
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            <a href="{{ $it['url'] }}" class="ex-open">Open café</a>
                            @if($exPre = \App\Services\Explore\ExploreLinks::preorder(\App\Models\NearbyPlace::find($it['id'])))<a href="{{ $exPre }}" class="ex-open"><i class="bi bi-bag-check"></i>Pre-order</a>@endif
                        </div>
                    @endif
                </div>
                <form method="POST" action="{{ route('explore.dismiss', $it['id']) }}">@csrf
                    <button class="btn btn-link btn-sm p-1 pk-muted" title="Not interested" aria-label="Dismiss {{ $it['name'] }}"><i class="bi bi-x-lg"></i></button>
                </form>
            </div>
        @empty
            <div class="small pk-muted py-2">{{ $exRec['message'] }}</div>
        @endforelse
        <div class="small pk-muted mt-2"><i class="bi bi-info-circle me-1"></i>Not sponsored. Times are approximate and keep {{ $exRec['buffer'] }} min spare. <a href="{{ route('explore.preferences') }}">Preferences</a></div>
    </div>
@endif
