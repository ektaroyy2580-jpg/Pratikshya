@extends('arcade.layouts.shell')

@push('head')
<style>
    .uno-table { --R: #e5484d; --G: #1f9d5c; --B: #2f5bea; --Y: #eaa911; --K: #1d2133; position: relative; }
    /* Cards */
    .uno-card { --w: clamp(56px, 14vw, 78px); width: var(--w); aspect-ratio: 2 / 3; border-radius: calc(var(--w) * .14); position: relative; flex-shrink: 0; color: #fff; background: var(--cc, var(--K)); border: 3px solid #fff; box-shadow: 0 6px 14px -8px rgba(15, 21, 53, .55), 0 0 0 1px rgba(15, 21, 53, .08); display: grid; place-items: center; overflow: hidden; user-select: none; font-weight: 800; }
    .uno-card.c-R { --cc: var(--R); } .uno-card.c-G { --cc: var(--G); } .uno-card.c-B { --cc: var(--B); } .uno-card.c-Y { --cc: var(--Y); } .uno-card.c-W { --cc: var(--K); }
    .uno-card .oval { width: 78%; height: 62%; border-radius: 50%; background: #fff; transform: rotate(-24deg); display: grid; place-items: center; }
    .uno-card .sym { transform: rotate(24deg); color: var(--cc); font-size: calc(var(--w) * .42); line-height: 1; letter-spacing: -.04em; }
    .uno-card.c-W .oval { background: conic-gradient(var(--R) 0 25%, var(--B) 0 50%, var(--Y) 0 75%, var(--G) 0); }
    .uno-card.c-W .sym { color: #fff; text-shadow: 0 1px 3px rgba(0, 0, 0, .45); font-size: calc(var(--w) * .34); }
    .uno-card .corner { position: absolute; font-size: calc(var(--w) * .2); line-height: 1; }
    .uno-card .corner.tl { top: 6%; left: 9%; } .uno-card .corner.br { bottom: 6%; right: 9%; transform: rotate(180deg); }
    .uno-back { background: var(--K); }
    .uno-back .oval { background: var(--R); }
    .uno-back .sym { color: #fff; font-size: calc(var(--w) * .2); font-style: italic; transform: rotate(0); }

    /* Opponents */
    .uno-opps { display: grid; grid-template-columns: repeat(var(--n, 3), minmax(0, 1fr)); gap: .6rem; }
    .uno-seat { border: 1px solid var(--pk-line); border-radius: 16px; padding: .7rem .6rem; background: #fff; text-align: center; transition: border-color .25s, box-shadow .25s, transform .25s var(--pk-ease); position: relative; min-width: 0; }
    .uno-seat.turn { border-color: var(--pk-blue); box-shadow: 0 0 0 3px rgba(47, 91, 234, .14); transform: translateY(-2px); }
    .uno-seat .av { width: 36px; height: 36px; border-radius: 50%; display: inline-grid; place-items: center; font-weight: 800; color: #fff; background: var(--pk-grad); font-size: .9rem; }
    .uno-seat .nm { font-weight: 800; font-size: .88rem; margin-top: .3rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .uno-seat .ct { font-size: .75rem; color: var(--pk-muted); font-weight: 700; }
    .uno-fan { display: flex; justify-content: center; margin-top: .45rem; height: 34px; }
    .uno-fan .uno-card { --w: 22px; border-width: 1.5px; margin-left: -12px; box-shadow: 0 2px 4px -2px rgba(15, 21, 53, .5); }
    .uno-fan .uno-card:first-child { margin-left: 0; }
    .uno-bubble { position: absolute; top: -10px; right: -6px; background: var(--R); color: #fff; font-size: .7rem; font-weight: 800; padding: .2rem .5rem; border-radius: 999px; animation: arc-pop .4s var(--pk-ease) both; }
    .uno-seat.flash { animation: arc-bump .45s var(--pk-ease); }

    /* Centre */
    .uno-center { display: flex; align-items: center; justify-content: center; gap: clamp(1rem, 5vw, 2.5rem); padding: 1.4rem 0 1rem; }
    .uno-pile { border: 0; background: transparent; padding: 0; position: relative; display: flex; flex-direction: column; align-items: center; gap: .35rem; cursor: pointer; }
    .uno-pile .uno-card { --w: clamp(66px, 17vw, 92px); }
    .uno-pile .stack { position: relative; }
    .uno-pile .stack::before, .uno-pile .stack::after { content: ''; position: absolute; inset: 0; border-radius: 14px; background: var(--K); border: 3px solid #fff; box-shadow: 0 0 0 1px rgba(15, 21, 53, .08); }
    .uno-pile .stack::before { transform: translate(4px, 4px); } .uno-pile .stack::after { transform: translate(2px, 2px); }
    .uno-pile .stack .uno-card { position: relative; z-index: 1; }
    .uno-pile:disabled { cursor: default; opacity: .75; }
    .uno-pile:not(:disabled):hover .stack { transform: translateY(-3px); }
    .uno-pile .stack { transition: transform .2s var(--pk-ease); }
    .uno-pile .lbl { font-size: .75rem; font-weight: 800; color: var(--pk-muted); }
    .uno-discard .uno-card { --w: clamp(72px, 19vw, 100px); }
    .uno-discard .uno-card.land { animation: uno-land .35s var(--pk-ease); }
    @keyframes uno-land { from { transform: translateY(-26px) rotate(-8deg) scale(1.08); opacity: .4; } to { transform: none; opacity: 1; } }
    .uno-state { display: flex; flex-direction: column; align-items: center; gap: .5rem; font-size: .75rem; font-weight: 800; color: var(--pk-muted); }
    .uno-dot { width: 34px; height: 34px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px var(--pk-line), 0 4px 10px -4px rgba(15, 21, 53, .4); background: var(--K); transition: background .3s; }
    .uno-dir { font-size: 1.3rem; color: var(--pk-ink-2); transition: transform .4s var(--pk-ease); }
    .uno-dir.rev { transform: scaleX(-1); }

    .uno-log { text-align: center; min-height: 2.4rem; display: flex; align-items: center; justify-content: center; }
    .uno-log span { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .9rem; border-radius: 999px; background: var(--pk-bg); font-weight: 700; font-size: .88rem; color: var(--pk-ink-2); animation: pk-rise .3s var(--pk-ease) both; }
    .uno-log span.mine { background: var(--pk-tint); color: var(--pk-blue); }

    /* My hand */
    .uno-me { border-top: 1px solid var(--pk-line); margin-top: .8rem; padding-top: .9rem; }
    .uno-me-bar { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; margin-bottom: .6rem; }
    .uno-me-bar .t { font-weight: 800; margin-right: auto; }
    .uno-hand { display: flex; gap: 0; overflow-x: auto; padding: 18px 6px 10px; scroll-snap-type: x proximity; -webkit-overflow-scrolling: touch; justify-content: safe center; }
    .uno-hand .h { border: 0; background: transparent; padding: 0; margin-left: calc(clamp(56px, 14vw, 78px) * -0.28); transition: transform .2s var(--pk-ease), filter .2s; scroll-snap-align: center; border-radius: 12px; }
    .uno-hand .h:first-child { margin-left: 0; }
    @media (min-width: 768px) { .uno-hand:not(.many) .h { margin-left: 8px; } .uno-hand:not(.many) .h:first-child { margin-left: 0; } }
    .uno-hand .h.ok { transform: translateY(-12px); }
    .uno-hand .h.ok:hover, .uno-hand .h.ok:focus-visible { transform: translateY(-20px); }
    .uno-hand .h.no { filter: saturate(.55) brightness(.92); cursor: not-allowed; }
    .uno-hand .h.new .uno-card { animation: uno-land .35s var(--pk-ease); }
    .uno-hand.idle .h { transform: none; filter: none; cursor: default; }
    .btn-uno { background: var(--R); color: #fff; border: 0; font-weight: 800; letter-spacing: .02em; }
    .btn-uno:hover { background: #cf3a3f; color: #fff; }
    .btn-uno.armed { box-shadow: 0 0 0 4px rgba(229, 72, 77, .25); background: #b92f34; }
    .btn-uno:disabled { background: #f2c4c6; color: #fff; }

    /* Colour picker */
    .uno-pick { position: absolute; inset: 0; z-index: 25; display: grid; place-items: center; background: rgba(246, 247, 252, .9); backdrop-filter: blur(4px); animation: pk-rise .25s var(--pk-ease) both; }
    .uno-pick .box { background: #fff; border-radius: 20px; box-shadow: var(--pk-shadow-lg); padding: 1.3rem; text-align: center; width: min(92%, 320px); }
    .uno-pick .grid { display: grid; grid-template-columns: 1fr 1fr; gap: .6rem; margin-top: .9rem; }
    .uno-pick .grid button { height: 64px; border: 0; border-radius: 14px; color: #fff; font-weight: 800; transition: transform .15s; }
    .uno-pick .grid button:hover { transform: translateY(-2px); }
</style>
@endpush

@section('arcade-intro', 'Classic UNO against computer players. The server deals, shuffles and enforces every rule, so wins are real.')

@section('arcade-hud')
    <span class="hud-item" title="Cards in your hand"><i class="bi bi-collection"></i><span id="unoCount">7</span></span>
@endsection

@section('arcade-board')
    <div class="uno-table" id="unoTable">
        <div class="uno-opps" id="unoOpps"></div>
        <div class="uno-center">
            <button type="button" class="uno-pile" id="unoDraw" aria-label="Draw a card">
                <span class="stack"><span class="uno-card uno-back"><span class="oval"><span class="sym">UNO</span></span></span></span>
                <span class="lbl" id="unoDeck">Draw</span>
            </button>
            <div class="uno-discard" id="unoDiscard" aria-label="Discard pile"></div>
            <div class="uno-state">
                <span class="uno-dot" id="unoColor" aria-label="Current colour"></span>
                <span id="unoColorName">Colour</span>
                <i class="bi bi-arrow-clockwise uno-dir" id="unoDir" aria-label="Play direction"></i>
            </div>
        </div>
        <div class="uno-log" id="unoLog" aria-live="polite"></div>
        <div class="uno-me">
            <div class="uno-me-bar">
                <span class="t" id="unoTurn">Your hand</span>
                <button type="button" class="btn btn-sm btn-uno" id="unoCall" disabled aria-pressed="false">UNO!</button>
                <button type="button" class="btn btn-sm btn-ghost" id="unoPass" hidden><i class="bi bi-skip-forward me-1"></i>Keep &amp; pass</button>
                <button type="button" class="btn btn-sm btn-ghost" id="unoQuit" title="Leave this game"><i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Leave</span></button>
            </div>
            <div class="uno-hand" id="unoHand" role="list" aria-label="Your cards"></div>
        </div>
    </div>
@endsection

@section('arcade-how')
    <li><span class="n">1</span><span>Play a card that matches the colour, number or symbol on the pile. Raised cards are playable.</span></li>
    <li><span class="n">2</span><span>No match? Tap the deck to draw. You can play the drawn card or keep it and pass.</span></li>
    <li><span class="n">3</span><span>Skip, Reverse, Draw Two, Wild and Wild Draw Four work as in the official rules (Wild Draw Four only when you hold no card of the current colour).</span></li>
    <li><span class="n">4</span><span>Down to two cards? Tap <b>UNO!</b> before playing, or draw 2 as a penalty. First to empty their hand wins.</span></li>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/arcade/uno.js') }}?v={{ filemtime(public_path('js/modules/arcade/uno.js')) }}"></script>
@endpush
