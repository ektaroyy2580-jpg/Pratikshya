{{-- One comic page. Vars: $page (array), $i (index), $total, $chapter, $art (ComicArt), $rtl (bool), $active (bool) --}}
@php $style = ($page['style'] ?? 'color') === 'manga' ? 'manga' : 'color'; @endphp
<section class="cm-page {{ $style }} {{ $rtl ? 'rtl' : '' }}" data-page="{{ $i }}" aria-label="Page {{ $i + 1 }} of {{ $total }}" @unless($active) hidden @endunless>
    <div class="cm-grid">
        @foreach(($page['panels'] ?? []) as $j => $panel)
            @php $span = ((int) ($panel['span'] ?? 1)) === 2; @endphp
            <figure class="cm-panel {{ $span ? 'span-2' : '' }}">
                {!! $art->panel($panel, $style, 'c'.$chapter->id.'p'.$i.'n'.$j) !!}
                @if(! empty($panel['caption']))
                    <div class="cm-cap">{{ $panel['caption'] }}</div>
                @endif
                @foreach(($panel['bubbles'] ?? []) as $bb)
                    @php
                        $kind = in_array($bb['kind'] ?? 'speech', ['speech', 'thought', 'shout', 'sfx'], true) ? $bb['kind'] : 'speech';
                        $tail = in_array($bb['tail'] ?? 'bl', ['bl', 'br', 'tl', 'tr', 'none'], true) ? $bb['tail'] : 'bl';
                    @endphp
                    <div class="cm-b {{ $kind }} t-{{ $tail }}" style="left: {{ (float) ($bb['x'] ?? 5) }}%; top: {{ (float) ($bb['y'] ?? 5) }}%; width: {{ (float) ($bb['w'] ?? 40) }}%">{{ $bb['text'] ?? '' }}</div>
                @endforeach
                @if(! empty($panel['alt']))
                    <figcaption class="visually-hidden">{{ $panel['alt'] }}</figcaption>
                @endif
            </figure>
        @endforeach
    </div>
</section>
