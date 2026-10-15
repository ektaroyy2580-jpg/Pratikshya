@extends('arcade.layouts.shell')

@push('head')
<style>
    .wd-hint { display: flex; gap: .8rem; align-items: center; background: var(--pk-bg); border-radius: 16px; padding: .9rem 1rem; margin: 1.1rem 0 1.4rem; font-weight: 700; color: var(--pk-ink-2); }
    .wd-row { display: flex; flex-wrap: wrap; justify-content: center; gap: clamp(5px, 1.4vw, 9px); }
    .wd-slot, .wd-tile { width: clamp(30px, 8.6vw, 52px); height: clamp(44px, 12vw, 58px); border-radius: 12px; display: grid; place-items: center; font-size: clamp(1.1rem, 4vw, 1.5rem); font-weight: 800; user-select: none; -webkit-tap-highlight-color: transparent; padding: 0; }
    .wd-slot { border: 2px dashed #cfd5ea; background: #fff; color: var(--pk-ink); cursor: pointer; transition: border-color .2s, background .2s; }
    .wd-slot.filled { border-style: solid; border-color: var(--pk-blue); background: var(--pk-tint); animation: arc-bump .25s var(--pk-ease); }
    .wd-row.solved .wd-slot { border-color: var(--pk-success); background: var(--pk-success-tint); color: var(--pk-success); }
    .wd-row.missed .wd-slot { border-color: var(--pk-danger); background: var(--pk-danger-tint); color: var(--pk-danger); }
    .wd-tile { border: 1px solid var(--pk-line); background: #fff; color: var(--pk-ink); box-shadow: 0 3px 0 #e1e5f2; cursor: pointer; transition: transform .12s var(--pk-ease), opacity .2s, box-shadow .12s; }
    .wd-tile:hover:not(:disabled) { transform: translateY(-2px); border-color: #b9c6f5; }
    .wd-tile:active:not(:disabled) { transform: translateY(2px); box-shadow: 0 1px 0 #e1e5f2; }
    .wd-tile:disabled { opacity: .25; cursor: default; box-shadow: none; }
    .wd-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: .5rem; margin-top: 1.4rem; }
    .wd-feedback { margin-top: 1.2rem; border-radius: 16px; padding: 1rem 1.1rem; animation: pk-rise .35s var(--pk-ease) both; }
    .wd-feedback.ok { background: var(--pk-success-tint); border: 1px solid #c9eed6; }
    .wd-feedback.bad { background: var(--pk-danger-tint); border: 1px solid #ffd0dc; }
    .wd-feedback .t { font-weight: 800; margin-bottom: .25rem; }
    .wd-feedback.ok .t { color: #116a35; } .wd-feedback.bad .t { color: #a3153a; }
</style>
@endpush

@section('arcade-intro', 'Six Nepal-themed words, scrambled. Tap the letters in the right order before the timer runs out.')

@section('arcade-hud')
    <span class="hud-item" title="Words solved"><i class="bi bi-check2-circle"></i><span id="wdRight">0</span>/6</span>
@endsection

@section('arcade-board')
    <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
        <div class="arc-dots" id="wdDots" aria-hidden="true"></div>
        <span class="small fw-bold pk-muted text-uppercase text-nowrap" id="wdCount" style="letter-spacing:.05em">Word 1 of 6</span>
    </div>
    <div class="arc-countdown" id="wdBar" aria-hidden="true"><span id="wdBarFill"></span></div>
    <div class="wd-hint"><span class="pk-icon amber" style="width:38px;height:38px;font-size:1rem"><i class="bi bi-lightbulb"></i></span><span id="wdHint"></span></div>
    <div class="wd-row mb-3" id="wdSlots" aria-label="Your answer" aria-live="polite"></div>
    <div class="wd-row" id="wdTiles" aria-label="Scrambled letters"></div>
    <div class="wd-actions" id="wdActions">
        <button type="button" class="btn btn-ghost btn-sm" id="wdShuffle"><i class="bi bi-shuffle me-1"></i>Shuffle</button>
        <button type="button" class="btn btn-ghost btn-sm" id="wdClear"><i class="bi bi-backspace me-1"></i>Clear</button>
        <button type="button" class="btn btn-ghost btn-sm" id="wdSkip"><i class="bi bi-skip-forward me-1"></i>Skip</button>
        <button type="button" class="btn btn-pk btn-sm px-4" id="wdSubmit" disabled><i class="bi bi-check2 me-1"></i>Check</button>
    </div>
    <div id="wdFeedback" aria-live="polite"></div>
@endsection

@section('arcade-how')
    <li><span class="n">1</span><span>Read the hint, then tap letters to build the word (or type on a keyboard).</span></li>
    <li><span class="n">2</span><span>Tap a filled slot to send a letter back. Shuffle if you are stuck.</span></li>
    <li><span class="n">3</span><span>45 seconds per word. Longer words score more.</span></li>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/arcade/word.js') }}?v={{ filemtime(public_path('js/modules/arcade/word.js')) }}"></script>
@endpush
