@extends('arcade.layouts.shell')

@push('head')
<style>
    .sd-wrap { display: grid; gap: 1.2rem; justify-items: center; }
    @media (min-width: 992px) { .sd-wrap { grid-template-columns: minmax(0, 468px) 200px; align-items: start; justify-content: center; } }
    .sd-grid { display: grid; grid-template-columns: repeat(9, 1fr); width: 100%; max-width: 468px; aspect-ratio: 1; border: 2px solid var(--pk-ink); border-radius: 14px; overflow: hidden; background: var(--pk-ink); gap: 1px; }
    .sd-cell { border: 0; padding: 0; background: #fff; display: grid; place-items: center; font-size: clamp(1rem, 4.6vw, 1.5rem); font-weight: 700; color: var(--pk-blue); cursor: pointer; font-variant-numeric: tabular-nums; transition: background .12s; -webkit-tap-highlight-color: transparent; min-width: 0; }
    .sd-cell.given { color: var(--pk-ink); font-weight: 800; background: #f7f8fc; }
    .sd-cell.c3, .sd-cell.c6 { box-shadow: inset 2px 0 0 var(--pk-ink); }
    .sd-cell.r3, .sd-cell.r6 { box-shadow: inset 0 2px 0 var(--pk-ink); }
    .sd-cell.c3.r3, .sd-cell.c3.r6, .sd-cell.c6.r3, .sd-cell.c6.r6 { box-shadow: inset 2px 0 0 var(--pk-ink), inset 0 2px 0 var(--pk-ink); }
    .sd-cell.peer { background: #eef1ff; }
    .sd-cell.same { background: #dfe6ff; }
    .sd-cell.sel { background: #c9d5ff; }
    .sd-cell.bad { color: var(--pk-danger); background: var(--pk-danger-tint); }
    .sd-cell.pop { animation: arc-bump .25s var(--pk-ease); }
    .sd-cell:focus-visible { outline: 3px solid rgba(47, 91, 234, .6); outline-offset: -3px; }
    .sd-pad { display: grid; grid-template-columns: repeat(5, 1fr); gap: .45rem; width: min(100%, 468px); }
    @media (min-width: 992px) { .sd-pad { grid-template-columns: repeat(3, 1fr); width: 200px; } }
    .sd-key { border: 1px solid var(--pk-line); background: #fff; border-radius: 12px; height: 52px; font-size: 1.25rem; font-weight: 800; color: var(--pk-ink); box-shadow: 0 2px 0 #e1e5f2; transition: transform .1s, background .15s; position: relative; }
    .sd-key:active { transform: translateY(2px); box-shadow: none; }
    .sd-key:hover { background: var(--pk-tint); }
    .sd-key .left { position: absolute; right: 6px; bottom: 3px; font-size: .62rem; font-weight: 700; color: var(--pk-muted); }
    .sd-key.full { opacity: .35; }
    .sd-key.erase { font-size: 1.1rem; color: var(--pk-danger); }
    .sd-side { width: min(100%, 468px); }
    @media (min-width: 992px) { .sd-side { width: 200px; } }
</style>
@endpush

@section('arcade-intro', 'A brand-new 9×9 puzzle with exactly one solution. Fill every row, column and 3×3 box with 1 to 9.')

@section('arcade-hud')
    <span class="hud-item" title="Cells left"><i class="bi bi-grid-3x3"></i><span id="sdLeft">0</span> left</span>
@endsection

@section('arcade-board')
    <div class="sd-wrap">
        <div class="sd-grid" id="sdGrid" role="grid" aria-label="Sudoku board"></div>
        <div class="sd-side d-grid gap-3">
            <div class="sd-pad" id="sdPad" aria-label="Number pad"></div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-ghost btn-sm" id="sdCheck"><i class="bi bi-search me-1"></i>Check conflicts</button>
                <button type="button" class="btn btn-pk btn-sm" id="sdSubmit" disabled><i class="bi bi-send me-1"></i>Submit solution</button>
            </div>
            <p class="small pk-muted mb-0 text-center">Conflicts show in red. The grid submits automatically when complete.</p>
        </div>
    </div>
@endsection

@section('arcade-how')
    <li><span class="n">1</span><span>Select an empty cell, then tap a number (or type 1–9; arrows move).</span></li>
    <li><span class="n">2</span><span>Every row, column and 3×3 box must contain 1–9 once.</span></li>
    <li><span class="n">3</span><span>Finish the grid: the server checks it against your puzzle. Medium pays more.</span></li>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/arcade/sudoku.js') }}?v={{ filemtime(public_path('js/modules/arcade/sudoku.js')) }}"></script>
@endpush
