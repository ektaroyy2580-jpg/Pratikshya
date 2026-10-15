/* Question games (Nepal GK quiz, Cyber Safety, Logic). Answers lock server-side; the server
   returns correctness + explanation, and computes the final score. */
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const CH_ICON = { SMS: 'bi-chat-left-text', Email: 'bi-envelope', Call: 'bi-telephone', Website: 'bi-globe2', Social: 'bi-people', App: 'bi-phone' };
    let api, game, idx, right, tick, deadline, busy, kind, results;

    function dots() {
        $('qzDots').innerHTML = game.questions.map((_, i) => `<span class="${results[i] === true ? 'ok' : results[i] === false ? 'bad' : i === idx ? 'cur' : ''}"></span>`).join('');
        $('qzCount').textContent = `${kind === 'cyber' ? 'Message' : kind === 'logic' ? 'Puzzle' : 'Question'} ${Math.min(idx + 1, game.questions.length)} of ${game.questions.length}`;
    }

    function stopTick() { if (tick) { cancelAnimationFrame(tick); tick = null; } }
    let lastLeft = 0, paused = null;
    function countdown(from) {
        stopTick();
        const total = game.seconds * 1000; deadline = performance.now() + (from ?? total);
        const fill = $('qzBarFill'), bar = $('qzBar');
        const step = () => {
            const left = deadline - performance.now(); lastLeft = left;
            fill.style.transform = `scaleX(${Math.max(0, left / total)})`;
            bar.classList.toggle('low', left < 5000);
            if (left <= 0) { tick = null; answer(null); return; }
            tick = requestAnimationFrame(step);
        };
        tick = requestAnimationFrame(step);
    }

    function render() {
        const q = game.questions[idx]; const e = api.esc;
        dots();
        let html = '';
        if (q.source === 'ai') html += '<span class="chip violet qz-ai mt-3"><i class="bi bi-stars"></i> AI-generated variation</span>';
        if (kind === 'cyber') {
            html += `<div class="msg-mock fade-up">
                <div class="hdr"><span class="pk-icon" style="width:38px;height:38px;font-size:1rem"><i class="bi ${CH_ICON[q.channel] || 'bi-chat'}"></i></span>
                <div class="min-w-0"><div class="ch">${e(q.channel)}</div><div class="from">${e(q.from)}</div></div></div>
                ${q.subject ? `<div class="subj">${e(q.subject)}</div>` : ''}
                <div class="body">${e(q.body)}</div>
                ${q.link ? `<span class="link"><i class="bi bi-link-45deg"></i>${e(q.link)}</span>` : ''}
            </div>
            <div class="qz-opts verdict">
                <button type="button" class="qz-opt" data-c="0"><i class="bi bi-shield-exclamation"></i>Phishing / scam</button>
                <button type="button" class="qz-opt" data-c="1"><i class="bi bi-shield-check"></i>Looks safe</button>
            </div>`;
        } else {
            const seq = kind === 'logic' && /^[\w\s,.?]+$/.test(q.q) && q.q.includes(',') && q.q.length < 50;
            const twoCol = q.options.every(o => o.length < 26);
            html += `<div class="qz-q fade-up ${seq ? 'seq' : ''}">${e(q.q)}</div>
            <div class="qz-opts ${twoCol ? 'two-col' : ''}">${q.options.map((o, i) =>
                `<button type="button" class="qz-opt fade-up" style="animation-delay:${0.04 * i}s" data-c="${i}"><span class="k">${'ABCD'[i] || i + 1}</span><span>${e(o)}</span></button>`).join('')}</div>`;
        }
        html += '<div id="qzFeedback"></div>';
        $('qzCard').innerHTML = html;
        $('qzCard').querySelectorAll('.qz-opt').forEach(b => b.addEventListener('click', () => answer(+b.dataset.c)));
        busy = false;
        countdown();
    }

    async function answer(choice) {
        if (busy) return; busy = true; stopTick();
        const buttons = [...$('qzCard').querySelectorAll('.qz-opt')];
        buttons.forEach(b => b.disabled = true);
        const r = await api.action({ index: idx, choice });
        if (!r.ok) {
            api.toast(r.message, 'bad');
            if (r.status === 0 || r.status === 429) { buttons.forEach(b => b.disabled = false); busy = false; countdown(); }
            return;
        }
        const d = r.data;
        results[idx] = d.correct;
        if (d.correct) right++;
        $('qzRight').textContent = right;
        api.setScore(d.score);
        buttons.forEach(b => {
            const c = +b.dataset.c;
            if (c === d.answer) b.classList.add('right');
            else if (c === choice) b.classList.add('wrong', 'arc-shake');
        });
        const last = d.answered >= d.total;
        const title = choice === null ? 'Time is up' : d.correct ? (kind === 'cyber' ? 'Well spotted!' : 'Correct!') : 'Not quite';
        const flags = (d.flags || []).map(f => `<span class="chip"><i class="bi bi-flag-fill"></i>${api.esc(f)}</span>`).join('');
        $('qzFeedback').innerHTML = `<div class="qz-feedback ${d.correct ? 'ok' : 'bad'}">
            <div class="t"><i class="bi ${d.correct ? 'bi-check-circle-fill' : 'bi-x-circle-fill'}"></i>${title}</div>
            <p>${api.esc(d.explain)}</p>${flags ? `<div class="qz-flags">${flags}</div>` : ''}
            <div class="text-end mt-3"><button type="button" class="btn btn-pk btn-sm" id="qzNext">${last ? 'See results' : 'Next'} <i class="bi bi-arrow-right ms-1"></i></button></div>
        </div>`;
        dots();
        const next = $('qzNext');
        next.focus({ preventScroll: true });
        next.addEventListener('click', () => {
            next.disabled = true;
            if (last) { api.finish({}).then(res => { if (!res.ok) next.disabled = false; }); return; }
            idx++; render();
        });
    }

    const module = {
        begin(g, coreApi) {
            api = coreApi; game = g; idx = 0; right = 0; results = []; busy = false;
            kind = window.ARCADE.game;
            $('qzRight').textContent = 0;
            render();
        },
        stop() { stopTick(); },
        pause() { if (tick) { stopTick(); paused = lastLeft; } },
        resume() { if (paused !== null) { const l = paused; paused = null; countdown(Math.max(3000, l)); } },
    };
    window.ArcadeGames.quiz = window.ArcadeGames.cyber = window.ArcadeGames.logic = module;
})();
