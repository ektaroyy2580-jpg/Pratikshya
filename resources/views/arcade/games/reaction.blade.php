@extends('arcade.layouts.shell')

@push('head')
<style>
    .rx-pad { width: 100%; min-height: clamp(240px, 42vh, 340px); border: 0; border-radius: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: .6rem; color: #fff; font-weight: 800; text-align: center; padding: 1.5rem; cursor: pointer; user-select: none; -webkit-user-select: none; touch-action: manipulation; -webkit-tap-highlight-color: transparent; transition: background-color .12s linear, transform .12s; }
    .rx-pad:active { transform: scale(.995); }
    .rx-pad i { font-size: 2.6rem; }
    .rx-pad .big { font-size: clamp(1.6rem, 5vw, 2.4rem); letter-spacing: -.02em; line-height: 1.1; }
    .rx-pad .sub { font-weight: 600; opacity: .85; font-size: .95rem; }
    .rx-pad[data-state="idle"], .rx-pad[data-state="shown"] { background: var(--pk-grad); }
    .rx-pad[data-state="wait"] { background: #1d2550; }
    .rx-pad[data-state="go"] { background: var(--pk-success); }
    .rx-pad[data-state="early"] { background: var(--pk-danger); }
    .rx-rounds { display: grid; grid-template-columns: repeat(5, 1fr); gap: .5rem; margin-top: 1rem; }
    .rx-round { border-radius: 12px; background: var(--pk-bg); border: 1px solid var(--pk-line); padding: .55rem .3rem; text-align: center; }
    .rx-round .l { font-size: .68rem; font-weight: 800; color: var(--pk-muted); text-transform: uppercase; letter-spacing: .05em; }
    .rx-round .v { font-weight: 800; font-variant-numeric: tabular-nums; font-size: .98rem; }
    .rx-round.cur { border-color: var(--pk-blue); background: var(--pk-tint); }
    .rx-round.done .v { color: var(--pk-blue); animation: arc-bump .35s var(--pk-ease); }
</style>
@endpush

@section('arcade-intro', 'Five rounds. Wait for the panel to turn green, then tap as fast as you can. Tapping early restarts the round.')

@section('arcade-hud')
    <span class="hud-item" title="Round"><i class="bi bi-flag"></i><span id="rxRound">1</span>/5</span>
@endsection

@section('arcade-board')
    <button type="button" class="rx-pad" id="rxPad" data-state="idle" aria-live="assertive">
        <i class="bi bi-hand-index-thumb"></i><span class="big">Tap to begin round 1</span><span class="sub">or press Space</span>
    </button>
    <div class="rx-rounds" id="rxRounds"></div>
@endsection

@section('arcade-how')
    <li><span class="n">1</span><span>Tap the panel to arm a round. It turns dark: wait.</span></li>
    <li><span class="n">2</span><span>The moment it turns <b>green</b>, tap (or press Space).</span></li>
    <li><span class="n">3</span><span>Five rounds; your average decides the score. Times under 100 ms are treated as guesses and rejected.</span></li>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/arcade/reaction.js') }}?v={{ filemtime(public_path('js/modules/arcade/reaction.js')) }}"></script>
@endpush
