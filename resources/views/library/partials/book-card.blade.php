{{-- Vars: $book, optional $delay --}}
<a href="{{ route('library.book', $book) }}" class="lib-card reveal" style="--d: {{ $delay ?? 0 }}s" aria-label="{{ $book->title }} by {{ $book->author }}, {{ $book->typeLabel() }}, {{ $book->readingTimeLabel() }}{{ $book->isAudiobook() ? ', audiobook available' : '' }}">
    @include('library.partials.cover', ['book' => $book])
    <div class="c-title" @if($book->language === 'ne') lang="ne" @endif>{{ $book->title }}</div>
    <div class="c-author">{{ $book->author }}</div>
    <div class="c-meta">
        <span><i class="bi bi-clock"></i> {{ $book->reading_minutes }} min</span>
        @if($book->isAudiobook())<span class="lib-audio-chip"><i class="bi bi-headphones"></i> Audiobook</span>@endif
        @if($book->language === 'ne')<span><i class="bi bi-translate"></i> नेपाली</span>@endif
        @if($book->isRtl())<span><i class="bi bi-arrow-left"></i> RTL</span>@endif
    </div>
</a>
