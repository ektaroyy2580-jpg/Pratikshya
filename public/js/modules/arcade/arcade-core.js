/* Arcade core: start/finish API, timer, HUD, result screen and queue-call watcher.
   Each game registers itself as window.ArcadeGames[key] = { begin(game, api), stop() }. */
(function () {
    'use strict';
    window.ArcadeGames = window.ArcadeGames || {};
    const CFG = window.ARCADE || {};
    const $ = id => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let session = null, timerId = null, t0 = 0, frozen = 0, score = 0, busy = false;

    async function post(url, body) {
        let res;
        try {
            res = await fetch(url, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(body || {}),
            });
        } catch (e) {
            return { ok: false, status: 0, message: 'Network problem. Check your connection and try again.', data: null };
        }
        let json = {};
        try { json = await res.json(); } catch (e) {}
        if (res.status === 419) json.message = 'Your session expired. Refresh the page to keep playing.';
        if (res.status === 429) json.message = 'Too many requests. Wait a moment and try again.';
        return { ok: res.ok, status: res.status, message: json.message || (res.ok ? '' : 'Something went wrong.'), data: json.data ?? null };
    }

    function show(name) {
        ['arcIntro', 'arcPlay', 'arcResult'].forEach(id => { const el = $(id); if (el) el.hidden = id !== name; });
        const stage = $('arcStage');
        if (stage && stage.getBoundingClientRect().top < 0) stage.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function toast(msg, type) {
        const stage = $('arcStage'); if (!stage) return;
        stage.querySelectorAll('.arc-toast').forEach(t => t.remove());
        const el = document.createElement('div');
        el.className = 'arc-toast ' + (type || '');
        el.setAttribute('role', 'status');
        el.innerHTML = `<i class="bi ${type === 'bad' ? 'bi-exclamation-circle' : type === 'ok' ? 'bi-check-circle' : 'bi-info-circle'}"></i><span>${esc(msg)}</span>`;
        stage.appendChild(el);
        setTimeout(() => el.remove(), 2800);
    }

    const fmt = ms => { const s = Math.floor(ms / 1000); return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0'); };
    const timer = {
        start() { this.stop(); t0 = performance.now(); frozen = 0; const el = $('arcTime'); timerId = setInterval(() => { if (el) el.textContent = fmt(performance.now() - t0); }, 250); },
        stop() { if (timerId) { clearInterval(timerId); timerId = null; frozen = performance.now() - t0; } },
        ms() { return timerId ? performance.now() - t0 : frozen; },
    };

    function setScore(n) {
        if (n === score) return;
        score = n;
        const el = $('arcScore'); if (!el) return;
        el.textContent = Number(n).toLocaleString();
        el.parentElement.classList.remove('score-bump'); void el.offsetWidth; el.parentElement.classList.add('score-bump');
    }

    function countUp(el, to) {
        const start = performance.now(), dur = 900;
        const step = now => { const p = Math.min(1, (now - start) / dur); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))).toLocaleString(); if (p < 1) requestAnimationFrame(step); };
        requestAnimationFrame(step);
    }

    function burst(host) {
        const b = document.createElement('div'); b.className = 'arc-burst';
        const colors = ['#2f5bea', '#7046e8', '#16a34a', '#f59e0b', '#e11d48'];
        for (let i = 0; i < 26; i++) {
            const p = document.createElement('i');
            const a = Math.random() * Math.PI * 2, d = 80 + Math.random() * 140;
            p.style.cssText = `background:${colors[i % 5]};--x:${Math.cos(a) * d}px;--y:${Math.sin(a) * d - 40}px;--r:${Math.random() * 540}deg;animation-delay:${Math.random() * .12}s`;
            b.appendChild(p);
        }
        host.appendChild(b); setTimeout(() => b.remove(), 1500);
    }

    function renderResult(data, rejected) {
        const box = $('arcResult');
        const s = data?.summary || {};
        const stats = (s.stats || []).map(x => `<div class="stat"><div class="v">${esc(x.value)}</div><div class="l">${esc(x.label)}</div></div>`).join('');
        const won = !rejected && (data?.points > 0 || data?.score > 0);
        const medalCls = rejected ? 'bad' : (won ? '' : 'neutral');
        const icon = rejected ? 'bi-x-lg' : (won ? 'bi-trophy-fill' : 'bi-flag-fill');
        const isBest = !rejected && data?.best != null && data.score >= data.best && data.score > 0;
        box.innerHTML = `
            <span class="medal ${medalCls}"><i class="bi ${icon}"></i></span>
            <div class="mt-3"><span class="pk-eyebrow">${rejected ? 'Result not accepted' : (isBest ? 'New personal best' : 'Game complete')}</span></div>
            <h2 class="mt-1 mb-2">${esc(rejected ? (data?.message || 'Result rejected') : (s.headline || 'Well played'))}</h2>
            ${rejected ? `<p class="pk-muted mx-auto" style="max-width:28rem">${esc(data?.reason || 'The server could not verify this result, so no points were awarded. Start a new game to try again.')}</p>` : `
            <div class="big mt-3" id="arcBig">0</div><div class="small pk-muted fw-bold">score</div>
            <div class="stats">${stats}</div>
            <div class="d-flex flex-wrap justify-content-center align-items-center gap-2">
                ${data.points > 0 ? `<span class="arc-points"><i class="bi bi-gem"></i>+${data.points} WaitPoints</span>` : ''}
                ${data.verified ? '<span class="chip green"><i class="bi bi-patch-check-fill"></i> Verified by server</span>' : ''}
                ${data.best != null ? `<span class="chip neutral"><i class="bi bi-trophy"></i> Best ${Number(data.best).toLocaleString()}</span>` : ''}
            </div>
            ${data.points_note ? `<div class="small pk-muted mt-2">${esc(data.points_note)}</div>` : ''}`}
            <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
                <button type="button" class="btn btn-pk" id="arcAgain"><i class="bi bi-arrow-repeat me-1"></i>Play again</button>
                <a class="btn btn-ghost" href="${esc(CFG.hubUrl)}"><i class="bi bi-grid me-1"></i>More games</a>
            </div>`;
        show('arcResult');
        if (!rejected) {
            countUp($('arcBig'), data.score || 0);
            if (won) burst(box);
            if (data.best != null) { const c = $('arcBestChip'); if (c) c.textContent = Number(data.best).toLocaleString(); }
            if (typeof data.balance === 'number') { const b = $('arcBalance'); if (b) b.textContent = data.balance.toLocaleString(); }
            if (data.points > 0) {
                CFG.today = (CFG.today || 0) + data.points;
                const t = $('arcToday'); if (t) t.textContent = CFG.today;
                const bar = $('arcTodayBar'); if (bar && CFG.cap) bar.style.width = Math.min(100, CFG.today / CFG.cap * 100) + '%';
            }
        }
        $('arcAgain').addEventListener('click', () => start());
        $('arcAgain').focus({ preventScroll: true });
    }

    const api = {
        esc, toast, timer, setScore, post,
        /** Step action (answer / move). Resolves {ok, status, message, data}. */
        action(body) { return post(session.urls.action, body); },
        /**
         * Submit the final result. On success or final rejection the result screen is shown.
         * Non-final rejections (e.g. sudoku not solved yet) resolve {ok:false, message} for the game to handle.
         */
        async finish(body) {
            if (busy) return { ok: false };
            busy = true; timer.stop();
            const r = await post(session.urls.finish, body);
            busy = false;
            const g = window.ArcadeGames[CFG.game];
            if (r.ok) { g?.stop?.(); renderResult(r.data, false); return r; }
            if (r.status === 422 && r.data && r.data.final === false) { timer.resume(); return r; }
            if (r.status === 0 || r.status === 429 || r.status === 419) { timer.resume(); toast(r.message, 'bad'); return r; }
            g?.stop?.();
            renderResult({ message: 'Result not accepted', reason: r.message }, true);
            return r;
        },
        /** Abandon without a result (e.g. UNO forfeit uses finish({forfeit:true})). */
        restart() { start(); },
    };
    timer.resume = function () { if (!timerId) { const already = frozen; t0 = performance.now() - already; const el = $('arcTime'); timerId = setInterval(() => { if (el) el.textContent = fmt(performance.now() - t0); }, 250); } };

    async function start() {
        const btn = $('arcStartBtn');
        const game = window.ArcadeGames[CFG.game];
        if (!game) { toast('This game failed to load. Refresh the page.', 'bad'); return; }
        game.stop?.();
        const variant = document.querySelector('input[name="arcVariant"]:checked')?.value;
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Preparing a fresh round…'; }
        const again = $('arcAgain'); if (again) again.disabled = true;
        const r = await post(CFG.startUrl, variant ? { variant } : {});
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-play-fill me-1"></i>Start game'; }
        if (!r.ok) { if (again) again.disabled = false; toast(r.message || 'Could not start the game.', 'bad'); return; }
        session = r.data;
        score = -1; setScore(0);
        const te = $('arcTime'); if (te) te.textContent = '0:00';
        show('arcPlay');
        timer.start();
        game.begin(session.game, api, session);
    }

    // ---------- Queue watcher: stop the player if their token is called ----------
    function watchTicket() {
        if (!CFG.ticket) return;
        let dismissed = false;
        const statusMap = { waiting: 'waiting', called: 'called', serving: 'serving' };
        const poll = async () => {
            if (document.hidden) return;
            let res; try { res = await fetch(CFG.ticket.stateUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); } catch (e) { return; }
            if (!res.ok) return;
            const d = (await res.json()).data || {};
            const st = $('arcTicketStatus');
            if (st) { st.className = 'pk-status ' + (statusMap[d.status] || 'done'); st.textContent = d.status ? d.status[0].toUpperCase() + d.status.slice(1) : ''; }
            const ah = $('arcTicketAhead'); if (ah && typeof d.ahead === 'number') ah.textContent = d.ahead;
            if (d.status === 'called' && !dismissed && !document.querySelector('.arc-called')) {
                const ov = document.createElement('div'); ov.className = 'arc-called'; ov.setAttribute('role', 'alertdialog'); ov.setAttribute('aria-label', 'You are being called');
                ov.innerHTML = `<div class="box"><span class="pk-icon green mb-3" style="width:64px;height:64px;font-size:1.8rem"><i class="bi bi-megaphone-fill"></i></span>
                    <h3 class="mb-2">It's your turn: ${esc(CFG.ticket.token)}</h3>
                    <p class="pk-muted">Please go to counter ${esc(d.counter ?? '')} now. Your game is paused and your place is safe.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2"><a class="btn btn-pk" href="${esc(CFG.ticket.url)}">Open my ticket</a><button type="button" class="btn btn-ghost" id="arcKeep">Keep playing</button></div></div>`;
                $('arcStage').appendChild(ov);
                const g = window.ArcadeGames[CFG.game];
                g?.pause?.();
                $('arcKeep').addEventListener('click', () => { dismissed = true; ov.remove(); g?.resume?.(); });
            }
        };
        setInterval(poll, 10000);
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('arcStartBtn')?.addEventListener('click', start);
        watchTicket();
    });
    window.Arcade = { start, api };
})();
