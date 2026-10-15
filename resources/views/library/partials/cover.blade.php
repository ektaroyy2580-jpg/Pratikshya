{{--
    Book cover. With a locally stored, credited photo/illustration it shows the image (blur-up, lazy) with the title on a
    soft shade; the generated SVG cover is always rendered underneath as the fallback (no image, missing file, load error).
    Vars: $book, $size ('sm'|'lg'|null), optional $eager (bool)
--}}
@php
    $cvSize = $size ?? '';
    $cvPhoto = $book->hasImage();
@endphp
<div class="lib-cover {{ $cvSize }} {{ $cvPhoto ? 'has-photo' : '' }}" @if($book->language === 'ne') lang="ne" @endif>
    {!! \App\Services\Library\CoverArt::svg($book->cover, $book->slug) !!}
    @if($cvPhoto)
        @include('library.partials.photo', [
            'src' => $book->imageUrl($cvSize !== 'lg'),
            'srcset' => $cvSize === 'lg' ? null : $book->imageUrl(true).' 500w, '.$book->imageUrl().' 960w',
            'sizes' => $cvSize === 'sm' ? '80px' : '(max-width: 575px) 45vw, 200px',
            'alt' => $cvSize === 'lg' ? (string) $book->image_alt : '', // decorative in cards (the card link has its own label)
            'ph' => $book->safePlaceholder(),
            'class' => 'lib-cover-photo',
            'eager' => $eager ?? false,
        ])
    @endif
    <div class="lib-cover-text" aria-hidden="true">
        <span class="t">{{ $book->title }}</span>
        <span class="a">{{ $book->author }}</span>
    </div>
    <span class="lib-type" aria-hidden="true">{{ $book->typeLabel() }}</span>
    @if($book->isAudiobook() && $cvSize !== 'sm')
        <span class="lib-audio-badge" aria-hidden="true" title="Audiobook — Listen mode"><i class="bi bi-headphones"></i></span>
    @endif
</div>
