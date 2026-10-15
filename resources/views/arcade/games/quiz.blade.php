@extends('arcade.layouts.shell')
{{-- Shared page for the question games: Nepal GK quiz, Cyber Safety, Logic Puzzles. --}}

@push('head')
<style>
    .qz-top { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .8rem; }
    .qz-count { font-size: .8rem; font-weight: 800; color: var(--pk-muted); text-transform: uppercase; letter-spacing: .05em; white-space: nowrap; }
    .qz-q { font-size: clamp(1.15rem, 2.6vw, 1.45rem); font-weight: 800; letter-spacing: -.02em; line-height: 1.3; margin: 1.2rem 0 1.1rem; }
    .qz-q.seq { font-size: clamp(1.4rem, 4vw, 2rem); font-variant-numeric: tabular-nums; letter-spacing: .01em; }
    .qz-opts { display: grid; gap: .6rem; }
    @media (min-width: 768px) { .qz-opts.two-col { grid-template-columns: 1fr 1fr; } }
    .qz-opt { display: flex; align-items: center; gap: .8rem; width: 100%; text-align: left; padding: .9rem 1rem; border-radius: 14px; border: 1px solid var(--pk-line); background: #fff; font-weight: 700; color: var(--pk-ink); transition: border-color .2s, background .2s, transform .15s var(--pk-ease), box-shadow .2s; min-height: 56px; }
    .qz-opt:hover:not(:disabled) { border-color: #b9c6f5; background: #fafbff; transform: translateY(-1px); box-shadow: var(--pk-shadow-sm); }
    .qz-opt .k { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; background: var(--pk-bg); color: var(--pk-muted); font-size: .8rem; font-weight: 800; flex-shrink: 0; transition: background .2s, color .2s; }
    .qz-opt.right { border-color: #9fdcb5; background: var(--pk-success-tint); }
    .qz-opt.right .k { background: var(--pk-success); color: #fff; }
    .qz-opt.wrong { border-color: #ffb8c8; background: var(--pk-danger-tint); }
    .qz-opt.wrong .k { background: var(--pk-danger); color: #fff; }
    .qz-opt:disabled { cursor: default; opacity: 1; }
    .qz-opt:disabled:not(.right):not(.wrong) { opacity: .55; }
    /* Cyber: two big verdict buttons */
    .qz-opts.verdict { grid-template-columns: 1fr 1fr; }
    .qz-opts.verdict .qz-opt { flex-direction: column; justify-content: center; text-align: center; gap: .35rem; min-height: 92px; font-size: 1rem; }
    .qz-opts.verdict .qz-opt i { font-size: 1.5rem; }
    .qz-opts.verdict .qz-opt[data-c="0"] i { color: var(--pk-danger); }
    .qz-opts.verdict .qz-opt[data-c="1"] i { color: var(--pk-success); }
    .msg-mock { border: 1px solid var(--pk-line); border-radius: 16px; background: var(--pk-bg); padding: 1rem 1.1rem; margin: 1rem 0 1.1rem; }
    .msg-mock .hdr { display: flex; align-items: center; gap: .7rem; margin-bottom: .6rem; }
    .msg-mock .from { font-weight: 800; font-size: .92rem; word-break: break-all; }
    .msg-mock .ch { font-size: .72rem; font-weight: 800; color: var(--pk-muted); text-transform: uppercase; letter-spacing: .05em; }
    .msg-mock .subj { font-weight: 800; margin-bottom: .3rem; }
    .msg-mock .body { color: var(--pk-ink-2); font-size: .95rem; line-height: 1.5; background: #fff; border-radius: 12px; padding: .8rem .9rem; border: 1px solid var(--pk-line); }
    .msg-mock .link { display: inline-flex; align-items: center; gap: .35rem; margin-top: .6rem; font-size: .82rem; font-weight: 700; color: var(--pk-blue); background: #fff; border: 1px dashed #c3cdf3; border-radius: 8px; padding: .25rem .55rem; word-break: break-all; }
    .qz-feedback { margin-top: 1rem; border-radius: 16px; padding: 1rem 1.1rem; animation: pk-rise .35s var(--pk-ease) both; }
    .qz-feedback.ok { background: var(--pk-success-tint); border: 1px solid #c9eed6; }
    .qz-feedback.bad { background: var(--pk-danger-tint); border: 1px solid #ffd0dc; }
    .qz-feedback .t { font-weight: 800; display: flex; align-items: center; gap: .45rem; margin-bottom: .3rem; }
    .qz-feedback.ok .t { color: #116a35; } .qz-feedback.bad .t { color: #a3153a; }
    .qz-feedback p { margin: 0; color: var(--pk-ink-2); font-size: .92rem; }
    .qz-flags { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .6rem; }
    .qz-flags .chip { background: #fff; color: var(--pk-danger); border: 1px solid #ffd0dc; }
    .qz-ai { font-size: .72rem; }
</style>
@endpush

@section('arcade-intro')
    @if($gameKey === 'cyber') Eight real-looking messages. Decide if each is a scam or safe, then learn the red flags.
    @elseif($gameKey === 'logic') Eight sequence and pattern puzzles. Take your time: you get 40 seconds each.
    @else Eight questions about Nepal's geography, history and culture, each with a short explanation.
    @endif
@endsection

@section('arcade-hud')
    <span class="hud-item" title="Correct answers"><i class="bi bi-check2-circle"></i><span id="qzRight">0</span></span>
@endsection

@section('arcade-board')
    <div class="qz-top">
        <div class="arc-dots" id="qzDots" aria-hidden="true"></div>
        <span class="qz-count" id="qzCount">Question 1 of 8</span>
    </div>
    <div class="arc-countdown" id="qzBar" aria-hidden="true"><span id="qzBarFill"></span></div>
    <div id="qzCard" aria-live="polite"></div>
@endsection

@section('arcade-how')
    @if($gameKey === 'cyber')
        <li><span class="n">1</span><span>Read the message: who sent it, what it asks, where the link goes.</span></li>
        <li><span class="n">2</span><span>Tap <b>Phishing / scam</b> or <b>Looks safe</b> within 30 seconds.</span></li>
        <li><span class="n">3</span><span>See the red flags and why. Streaks of correct answers score extra.</span></li>
    @elseif($gameKey === 'logic')
        <li><span class="n">1</span><span>Find the rule behind each sequence or pattern.</span></li>
        <li><span class="n">2</span><span>Pick the answer that comes next. 40 seconds per puzzle.</span></li>
        <li><span class="n">3</span><span>Read the explanation, then continue. Streaks score extra.</span></li>
    @else
        <li><span class="n">1</span><span>Answer each question within 20 seconds.</span></li>
        <li><span class="n">2</span><span>Your answer locks instantly and is checked on the server.</span></li>
        <li><span class="n">3</span><span>Learn something from each explanation. Streaks score extra.</span></li>
    @endif
@endsection

@push('scripts')
<script src="{{ asset('js/modules/arcade/quiz.js') }}?v={{ filemtime(public_path('js/modules/arcade/quiz.js')) }}"></script>
@endpush
