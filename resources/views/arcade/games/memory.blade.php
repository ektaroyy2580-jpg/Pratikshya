@extends('arcade.layouts.shell')

@push('head')
<style>
    .mm-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: clamp(8px, 2vw, 14px); max-width: 460px; margin: 0 auto; perspective: 900px; }
    .mm-card { position: relative; aspect-ratio: 1; border: 0; padding: 0; background: transparent; cursor: pointer; border-radius: 16px; -webkit-tap-highlight-color: transparent; }
    .mm-card:focus-visible { outline: 3px solid rgba(47, 91, 234, .5); outline-offset: 3px; }
    .mm-inner { position: absolute; inset: 0; transform-style: preserve-3d; transition: transform .45s var(--pk-ease); border-radius: inherit; }
    .mm-card.open .mm-inner, .mm-card.done .mm-inner { transform: rotateY(180deg); }
    .mm-face { position: absolute; inset: 0; border-radius: inherit; display: grid; place-items: center; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
    .mm-back { background: var(--pk-grad); box-shadow: 0 10px 20px -12px rgba(47, 91, 234, .8); }
    .mm-back::after { content: ''; width: 34%; aspect-ratio: 1; border-radius: 10px; border: 2px solid rgba(255, 255, 255, .45); transform: rotate(45deg); }
    .mm-front { background: #fff; border: 1px solid var(--pk-line); transform: rotateY(180deg); font-size: clamp(1.5rem, 6vw, 2.2rem); color: var(--pk-blue); box-shadow: var(--pk-shadow-sm); }
    .mm-card.done .mm-front { background: var(--pk-success-tint); border-color: #bfe6cd; color: var(--pk-success); animation: arc-bump .45s var(--pk-ease); }
    .mm-card.miss .mm-front { border-color: #ffc7d3; background: var(--pk-danger-tint); color: var(--pk-danger); }
    .mm-card:not(.open):not(.done):hover .mm-inner { transform: translateY(-2px); }
</style>
@endpush

@section('arcade-intro', 'Sixteen cards, eight pairs. Every move is replayed on the server to verify your score.')

@section('arcade-hud')
    <span class="hud-item" title="Moves"><i class="bi bi-hand-index"></i><span id="mmMoves">0</span></span>
    <span class="hud-item" title="Pairs found"><i class="bi bi-check2-square"></i><span id="mmPairs">0</span>/8</span>
@endsection

@section('arcade-board')
    <div class="mm-grid" id="mmGrid" role="grid" aria-label="Memory cards"></div>
    <p class="text-center small pk-muted mt-3 mb-0">Tip: fewer moves means a higher score and more WaitPoints.</p>
@endsection

@section('arcade-how')
    <li><span class="n">1</span><span>Tap a card to flip it, then tap a second card.</span></li>
    <li><span class="n">2</span><span>If the icons match they stay open; otherwise both flip back.</span></li>
    <li><span class="n">3</span><span>Match all 8 pairs. 12 moves or fewer earns the top reward.</span></li>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/arcade/memory.js') }}?v={{ filemtime(public_path('js/modules/arcade/memory.js')) }}"></script>
@endpush
