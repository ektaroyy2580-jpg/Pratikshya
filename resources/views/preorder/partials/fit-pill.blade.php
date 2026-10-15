@php
    $fitMap = [
        'ok' => ['bi-check-circle-fill', 'Fits your wait'],
        'tight' => ['bi-exclamation-circle-fill', 'Tight fit'],
        'conflict' => ['bi-x-octagon-fill', 'Might clash with your turn'],
        'no_ticket' => ['bi-clock', 'Ready in ~'.($hint['ready_in_minutes'] ?? '?').' min'],
    ];
    [$fIcon, $fText] = $fitMap[$hint['state'] ?? 'no_ticket'] ?? $fitMap['no_ticket'];
@endphp
<span class="fit {{ $hint['state'] ?? 'no_ticket' }}" title="{{ $hint['explanation'] ?? '' }}"><i class="bi {{ $fIcon }}"></i>{{ $fText }}</span>
