/* Word Challenge: build a guess from scrambled tiles; the server holds the word list and checks guesses. */
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    let api, game, idx, right, results, picked, letters, tick, busy, keyHandler;

    function stopTick() { if (tick) { cancelAnimationFrame(tick); tick = null; } }
    let lastLeft = 0, paused = null;
    function countdown(from) {
        stopTick();
        const total = game.seconds * 1000, end = performance.now() + (from ?? total);
        const step = () => {
            const left = end - performance.now(); lastLeft = left;
            $('wdBarFill').style.transform = `scaleX(${Math.max(0, left / total)})`;
            $('wdBar').classList.toggle('low', left < 8000);
            if (left <= 0) { tick = null; submit(true); return; }
            tick = requestAnimationFrame(step);
        };
        tick = requestAnimationFrame(step);
    }

    function dots() {
        $('wdDots').innerHTML = game.words.map((_, i) => `<span class="${results[i] === true ? 'ok' : results[i] === false ? 'bad' : i === idx ? 'cur' : ''}"></span>`).join('');
        $('wdCount').textContent = `Word ${Math.min(idx + 1, game.words.length)} of ${game.words.length}`;
    }

    function draw() {
        const w = game.words[idx];
        $('wdSlots').className = 'wd-row mb-3';
        $('wdSlots').innerHTML = Array.from({ length: w.length }, (_, i) => {
            const t = picked[i];
            return `<button type="button" class="wd-slot ${t !== undefined ? 'filled' : ''}" data-s="${i}" aria-label="${t !== undefined ? 'Letter ' + letters[t] + ', tap to remove' : 'Empty slot'}">${t !== undefined ? api.esc(letters[t]) : ''}</button>`;
        }).join('');
        $('wdTiles').innerHTML = letters.map((l, i) => `<button type="button" class="wd-tile" data-t="${i}" ${picked.includes(i) ? 'disabled' : ''} aria-label="Letter ${api.esc(l)}">${api.esc(l)}</button>`).join('');
        $('wdSlots').querySelectorAll('.wd-slot').forEach(b => b.addEventListener('click', () => {
            if (busy) return;
            const s = +b.dataset.s;
            if (picked[s] !== undefined) { picked.splice(s, 1); draw(); }
        }));
        $('wdTiles').querySelectorAll('.wd-tile').forEach(b => b.addEventListener('click', () => pick(+b.dataset.t)));
        $('wdSubmit').disabled = busy || picked.length !== w.length;
    }

    function pick(t) {
        if (busy || picked.includes(t) || picked.length >= game.words[idx].length) return;
        picked.push(t); draw();
        if (picked.length === game.words[idx].length) $('wdSubmit').focus({ preventScroll: true });
    }

    function load() {
        const w = game.words[idx];
        letters = w.letters.slice(); picked = []; busy = false;
        $('wdHint').textContent = w.hint;
        $('wdFeedback').innerHTML = '';
        $('wdActions').hidden = false;
        dots(); draw(); countdown();
    }

    async function submit(timeUp) {
        if (busy) return;
        busy = true; stopTick();
        const guess = picked.map(i => letters[i]).join('');
        draw();
        const r = await api.action({ index: idx, guess });
        if (!r.ok) {
            api.toast(r.message, 'bad');
            if (r.status === 0 || r.status === 429) { busy = false; draw(); countdown(); }
            return;
        }
        const d = r.data;
        results[idx] = d.correct;
        if (d.correct) right++;
        $('wdRight').textContent = right;
        api.setScore(d.score);
        $('wdSlots').innerHTML = d.solution.split('').map(c => `<span class="wd-slot filled">${api.esc(c)}</span>`).join('');
        $('wdSlots').classList.add(d.correct ? 'solved' : 'missed');
        if (!d.correct) $('wdSlots').classList.add('arc-shake');
        $('wdTiles').innerHTML = '';
        $('wdActions').hidden = true;
        const last = d.answered >= d.total;
        const verdict = d.correct ? 'Solved!' : (timeUp ? 'Time is up.' : guess ? 'Not this time.' : 'Skipped.');
        $('wdFeedback').innerHTML = `<div class="wd-feedback ${d.correct ? 'ok' : 'bad'}">
            <div class="t"><i class="bi ${d.correct ? 'bi-check-circle-fill' : 'bi-x-circle-fill'} me-1"></i>${verdict} The word was ${api.esc(d.solution)}.</div>
            <div class="small" style="color:var(--pk-ink-2)">${api.esc(d.explain)}</div>
            <div class="text-end mt-3"><button type="button" class="btn btn-pk btn-sm" id="wdNext">${last ? 'See results' : 'Next word'} <i class="bi bi-arrow-right ms-1"></i></button></div></div>`;
        dots();
        const next = $('wdNext');
        next.focus({ preventScroll: true });
        next.addEventListener('click', () => {
            next.disabled = true;
            if (last) { api.finish({}).then(res => { if (!res.ok) next.disabled = false; }); return; }
            idx++; load();
        });
    }

    function shuffleTiles() {
        if (busy) return;
        const keep = picked.map(i => letters[i]);
        letters = letters.map(l => [Math.random(), l]).sort((a, b) => a[0] - b[0]).map(x => x[1]);
        picked = [];
        keep.forEach(ch => { const j = letters.findIndex((l, k) => l === ch && !picked.includes(k)); picked.push(j); });
        draw();
    }

    function bind() {
        $('wdShuffle').onclick = shuffleTiles;
        $('wdClear').onclick = () => { if (!busy) { picked = []; draw(); } };
        $('wdSkip').onclick = () => { if (!busy) { picked = []; submit(false); } };
        $('wdSubmit').onclick = () => submit(false);
        if (keyHandler) document.removeEventListener('keydown', keyHandler);
        keyHandler = e => {
            if ($('arcPlay').hidden || busy || e.ctrlKey || e.metaKey || e.altKey) return;
            if (/^[a-z]$/i.test(e.key)) {
                const ch = e.key.toUpperCase();
                const t = letters.findIndex((l, i) => l === ch && !picked.includes(i));
                if (t >= 0) { pick(t); e.preventDefault(); }
            } else if (e.key === 'Backspace') { picked.pop(); draw(); e.preventDefault(); }
            else if (e.key === 'Enter' && picked.length === game.words[idx].length) { submit(false); e.preventDefault(); }
        };
        document.addEventListener('keydown', keyHandler);
    }

    window.ArcadeGames.word = {
        begin(g, coreApi) { api = coreApi; game = g; idx = 0; right = 0; results = []; $('wdRight').textContent = 0; bind(); load(); },
        stop() { stopTick(); busy = true; },
        pause() { if (tick) { stopTick(); paused = lastLeft; } },
        resume() { if (paused !== null) { const l = paused; paused = null; countdown(Math.max(3000, l)); } },
    };
})();
