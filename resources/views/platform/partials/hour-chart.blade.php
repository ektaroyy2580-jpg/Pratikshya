{{-- Hour-of-day bar chart (inline SVG, single series). Needs $hours (array of 24 ints). Optional $chartId. --}}
@php
    $chartId = $chartId ?? 'hc'.substr(md5(json_encode($hours)), 0, 6);
    $max = max(1, max($hours));
    $total = array_sum($hours);
    $peakHour = $total ? array_search(max($hours), $hours, true) : null;
    $W = 720; $H = 200; $padL = 30; $padB = 24; $padT = 12;
    $plotW = $W - $padL - 6; $plotH = $H - $padB - $padT;
    $slot = $plotW / 24; $barW = max(4, $slot - 6);
    $ticks = array_unique([0, (int) ceil($max / 2), $max]);
    $fmt = fn ($h) => \Carbon\Carbon::createFromTime($h)->format('ga');
@endphp
<figure class="pf-chart mb-0" id="{{ $chartId }}">
    @if($total === 0)
        <div class="pf-empty py-4"><span class="pk-icon mb-2"><i class="bi bi-bar-chart"></i></span><p class="pk-muted small mb-0">No tokens issued in the last 30 days yet.</p></div>
    @else
        <svg viewBox="0 0 {{ $W }} {{ $H }}" width="100%" role="img" aria-labelledby="{{ $chartId }}-t" style="height:auto;max-height:240px;overflow:visible">
            <title id="{{ $chartId }}-t">Tokens issued by hour of day, last 30 days. Busiest hour: {{ $fmt($peakHour) }}.</title>
            @foreach($ticks as $t)
                @php $y = $padT + $plotH - ($t / $max) * $plotH; @endphp
                <line x1="{{ $padL }}" x2="{{ $W }}" y1="{{ $y }}" y2="{{ $y }}" stroke="var(--pk-line)" stroke-width="1" @if($t) stroke-dasharray="3 4" @endif />
                <text x="{{ $padL - 6 }}" y="{{ $y + 4 }}" text-anchor="end" font-size="10" fill="var(--pk-muted)">{{ $t }}</text>
            @endforeach
            @foreach($hours as $h => $c)
                @php
                    $bh = $c ? max(3, ($c / $max) * $plotH) : 0;
                    $x = $padL + $h * $slot + ($slot - $barW) / 2;
                    $y = $padT + $plotH - $bh;
                @endphp
                <g class="pf-bar" tabindex="0" data-tip="{{ $fmt($h) }}–{{ $fmt(($h + 1) % 24) }} · {{ $c }} {{ \Illuminate\Support\Str::plural('token', $c) }}">
                    <rect x="{{ $padL + $h * $slot }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent" />
                    @if($c)
                        <path d="M{{ $x }},{{ $padT + $plotH }} V{{ $y + min(4, $bh) }} Q{{ $x }},{{ $y }} {{ $x + min(4, $barW / 2) }},{{ $y }} H{{ $x + $barW - min(4, $barW / 2) }} Q{{ $x + $barW }},{{ $y }} {{ $x + $barW }},{{ $y + min(4, $bh) }} V{{ $padT + $plotH }} Z"
                              fill="{{ $h === $peakHour ? 'var(--pk-violet)' : 'var(--pk-blue)' }}" opacity="{{ $h === $peakHour ? 1 : .78 }}" />
                    @endif
                    <title>{{ $fmt($h) }}: {{ $c }} tokens</title>
                </g>
                @if($h % 3 === 0)
                    <text x="{{ $padL + $h * $slot + $slot / 2 }}" y="{{ $H - 6 }}" text-anchor="middle" font-size="10" fill="var(--pk-muted)">{{ $fmt($h) }}</text>
                @endif
            @endforeach
        </svg>
        <div class="pf-chart-tip" role="status" aria-live="polite"></div>
        <figcaption class="small pk-muted mt-2 d-flex flex-wrap gap-3 align-items-center">
            <span><i class="bi bi-square-fill me-1" style="color:var(--pk-violet)"></i>Busiest hour: <strong class="text-body">{{ $fmt($peakHour) }}</strong> ({{ $hours[$peakHour] }} tokens)</span>
            <span>{{ $total }} tokens in 30 days</span>
            <details class="ms-auto"><summary class="fw-semibold">Table view</summary>
                <table class="table table-sm small mt-2 mb-0" style="max-width:260px"><thead><tr><th>Hour</th><th class="text-end">Tokens</th></tr></thead><tbody>
                    @foreach($hours as $h => $c) @if($c)<tr><td>{{ $fmt($h) }}</td><td class="text-end">{{ $c }}</td></tr>@endif @endforeach
                </tbody></table>
            </details>
        </figcaption>
    @endif
</figure>
@once
    @push('head')
    <style>
        .pf-chart { position: relative; }
        .pf-bar { cursor: default; outline: none; }
        .pf-bar path { transition: opacity .2s; }
        .pf-bar:hover path, .pf-bar:focus path { opacity: 1; }
        .pf-bar:focus rect { stroke: var(--pk-blue); stroke-width: 1.5; }
        .pf-chart-tip { position: absolute; pointer-events: none; background: var(--pk-ink); color: #fff; font-size: .75rem; font-weight: 700; padding: .3rem .55rem; border-radius: 8px; opacity: 0; transform: translate(-50%, -120%); transition: opacity .15s; white-space: nowrap; }
        .pf-chart-tip.show { opacity: 1; }
    </style>
    @endpush
    @push('scripts')
    <script>
    document.querySelectorAll('.pf-chart').forEach(fig => {
        const tip = fig.querySelector('.pf-chart-tip'); if (!tip) return;
        const show = (g) => { const r = g.getBoundingClientRect(), f = fig.getBoundingClientRect(); tip.textContent = g.dataset.tip; tip.style.left = (r.left - f.left + r.width / 2) + 'px'; tip.style.top = (r.top - f.top + 18) + 'px'; tip.classList.add('show'); };
        fig.querySelectorAll('.pf-bar').forEach(g => { g.addEventListener('mouseenter', () => show(g)); g.addEventListener('focus', () => show(g)); g.addEventListener('mouseleave', () => tip.classList.remove('show')); g.addEventListener('blur', () => tip.classList.remove('show')); });
    });
    </script>
    @endpush
@endonce
