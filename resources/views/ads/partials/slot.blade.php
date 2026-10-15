{{--
    Sponsored slot. Usage:
        @include('ads.partials.slot', ['placement' => 'explore', 'context' => ['location_id' => $location->id, 'category' => 'cafe']])
    Optional: 'compact' => true, 'class' => 'mb-4'.
    Context keys: location_id, category, ticket (if the ticket is called/serving the slot renders NOTHING), suppress (bool).
    Do NOT include this partial on views that show critical queue alerts. It renders nothing (no wrapper) when no ad
    qualifies, ads are disabled, the viewer opted out, or the ads tables are not migrated yet.
--}}
@php
    $adPick = null;
    try {
        $adServer = app(\App\Services\Ads\AdServer::class);
        $adPick = $adServer->pick($placement, $context ?? []);
    } catch (\Throwable $e) {
        report($e);
        $adPick = null; // Ads must never break the host page.
    }
@endphp
@if($adPick)
    @once
        @push('scripts')<script src="{{ asset('js/modules/ads.js') }}?v={{ @filemtime(public_path('js/modules/ads.js')) }}" defer></script>@endpush
    @endonce
    @php
        $adCtx = $context ?? [];
        $adReasons = array_filter([
            $adPick->target_location_id ? 'the location you are viewing' : null,
            $adPick->target_category && ($adCtx['category'] ?? null) ? 'this type of place' : null,
        ]);
    @endphp
    <div class="{{ $class ?? '' }}">
        @include('ads.partials.card', [
            'campaign' => $adPick,
            'placement' => $placement,
            'token' => $adServer->slotToken($adPick, $placement),
            'href' => \Illuminate\Support\Facades\URL::signedRoute('ads.click', ['campaign' => $adPick->id, 'placement' => $placement]),
            'compact' => $compact ?? false,
            'why' => 'Shown because of '.($adReasons ? implode(' and ', $adReasons) : 'the section of the app you are in').'. It is not based on your activity or personal data.',
        ])
    </div>
@endif
