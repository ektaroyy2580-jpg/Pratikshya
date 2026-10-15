{{-- In-chapter illustration (seeded, file verified on disk by BookChapter::illustrationsByBlock). Var: $ill --}}
@php
    $figCredit = $ill['credit'] ?? null;
    $figSrc = is_array($figCredit) && is_string($figCredit['source_url'] ?? null) && str_starts_with($figCredit['source_url'], 'https://commons.wikimedia.org/') ? $figCredit['source_url'] : null;
@endphp
<figure class="rd-fig">
    @include('library.partials.photo', [
        'src' => asset($ill['path']),
        'alt' => (string) ($ill['alt'] ?? ''),
        'ph' => \App\Models\Book::cleanPlaceholder($ill['placeholder'] ?? null),
        'w' => $ill['width'] ?? null,
        'h' => $ill['height'] ?? null,
        'class' => 'rd-fig-img',
    ])
    <figcaption>
        @if(! empty($ill['caption']))<span class="rd-cap">{{ $ill['caption'] }}</span>@endif
        @if($figCredit)
            <span class="rd-credit">
                @if($figSrc)<a href="{{ $figSrc }}" target="_blank" rel="noopener">{{ \App\Models\Book::creditLine($figCredit) }}</a>@else{{ \App\Models\Book::creditLine($figCredit) }}@endif
            </span>
        @endif
    </figcaption>
</figure>
