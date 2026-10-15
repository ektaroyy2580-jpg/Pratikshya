{{--
    Blur-up, lazy-loaded local image. A tiny base64 placeholder is shown blurred until the real file arrives,
    then the photo fades in (instantly with prefers-reduced-motion). If the file fails to load, the image removes
    itself and whatever sits underneath (e.g. the generated SVG cover) shows through.
    Vars: $src, $alt, optional $srcset, $sizes, $ph (sanitised data: URI), $w, $h, $class, $eager (bool)
--}}
<span class="lib-ph {{ $class ?? '' }}" @if(! empty($ph)) style="--ph: url('{{ $ph }}')" @endif>
    <img src="{{ $src }}" alt="{{ $alt ?? '' }}"
         @if(! empty($srcset)) srcset="{{ $srcset }}" sizes="{{ $sizes ?? '100vw' }}" @endif
         @if(! empty($w) && ! empty($h)) width="{{ (int) $w }}" height="{{ (int) $h }}" @endif
         loading="{{ ! empty($eager) ? 'eager' : 'lazy' }}" decoding="async" @if(! empty($eager)) fetchpriority="high" @endif
         onload="this.parentNode.classList.add('is-in')" onerror="this.parentNode.classList.add('is-err');this.remove()">
</span>
