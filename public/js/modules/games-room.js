/* Quick Brain Battle room — renders server state; the server owns timing, scoring and ranking.
   Polls the JSON state endpoint (~2.5s, or right at the next known server-side boundary). */
(function () {
    'use strict';
    const root = document.getElementById('gmRoom');
    if (!root) return;

    const $ = (id) => document.getElementById(id);
    const url = (k) => root.dataset[k];
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const REVEAL_MS = parseInt(url('revealMs'), 10) || 4000;
    const POLL_MS = 2500;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const KEYS = ['A', 'B', 'C', 'D'];

    let S = JSON.parse($('gmInitial').textContent);
    let offset = S.server_time - Date.now();          // server clock − client clock
    const serverNow = () => Date.now() + offset;

    let pollTimer = null, failures = 0, busy = false;
    let stageKey = '';                                 // what the stage currently shows
    let pending = null;                                // {qid, choice} while an answer POST is in flight
    let localResult = {};                              // qid → server answer response
    let lastScores = {};
    let rafId = null;

    // ------------------------------------------------------------------ network
    async function api(method, href, body) {
        const res = await fetch(href, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: body ? JSON.stringify(body) : undefined,
            credentials: 'same-origin',
        });
        let json = null;
        try { json = await res.json(); } catch (e) { /* non-JSON */ }
        return { ok: res.ok, status: res.status, json };
    }

    async function poll() {
        clearTimeout(pollTimer);
        const t0 = Date.now();
        try {
            const r = await api('GET', url('stateUrl'));
            if (r.status === 403 || r.status === 404) { fatal('You are not part of this battle any more.'); return; }
            if (r.status === 419 || r.status === 401) { fatal('Your session expired. Please log in again.'); return; }
            if (!r.ok || !r.json?.data) throw new Error('bad');
            const rtt = Date.now() - t0;
            offset = r.json.data.server_time - (t0 + rtt / 2);
            failures = 0;
            $('gmNet').classList.add('d-none');
            apply(r.json.data);
        } catch (e) {
            failures++;
            if (failures >= 2) $('gmNet').classList.remove('d-none');
        }
        schedule();
    }

    /** Next poll: the regular interval, or just after the next known server boundary (whichever is sooner). */
    function schedule() {
        if (S.status === 'completed' || S.status === 'cancelled') return;
        let delay = failures ? Math.min(10000, POLL_MS * (failures + 1)) : POLL_MS;
        const now = serverNow();
        const marks = [];
        if (S.status === 'starting' && S.starts_at) marks.push(S.starts_at);
        if (S.question) { marks.push(S.question.closes_at); marks.push(S.question.closes_at + REVEAL_MS); }
        if (S.next_opens_at) marks.push(S.next_opens_at);
        marks.filter((m) => m > now).forEach((m) => { delay = Math.min(delay, m - now + 150); });
        delay = Math.max(350, delay);
        if (document.hidden) delay = Math.max(delay, 5000);
        pollTimer = setTimeout(poll, delay);
    }

    function toast(msg, kind) {
        $('gmToast').textContent = msg;
        const box = document.createElement('div');
        box.className = 'toast-ww';
        box.innerHTML = `<div class="alert ${kind === 'err' ? 'alert-danger' : 'alert-success'} shadow d-flex align-items-center gap-2 mb-0">
            <i class="bi ${kind === 'err' ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill'}"></i><span class="flex-grow-1 fw-semibold">${esc(msg)}</span></div>`;
        document.body.appendChild(box);
        setTimeout(() => box.remove(), 3800);
    }

    async function action(href, body, okMsg) {
        if (busy) return;
        busy = true;
        try {
            const r = await api('POST', href, body);
            const msg = r.json?.message || (r.ok ? okMsg : 'Something went wrong. Please try again.');
            if (!r.ok) { toast(msg, 'err'); } else if (r.json?.data?.redirect && /leave|cancel/.test(href)) { window.location = r.json.data.redirect; return; } else if (okMsg !== null) { toast(msg); }
        } catch (e) {
            toast('Network error. Please try again.', 'err');
        } finally {
            busy = false;
        }
        poll();
    }

    function fatal(msg) {
        clearTimeout(pollTimer);
        $('gmStage').innerHTML = `<div class="gm-alert err"><i class="bi bi-exclamation-octagon fs-5"></i><div><strong>${esc(msg)}</strong><div class="mt-2"><a class="btn btn-ghost btn-sm" href="${esc(url('hubUrl'))}">Back to the game hub</a></div></div></div>`;
    }

    // ------------------------------------------------------------------ render
    function apply(data) {
        S = data;
        if (S.status === 'completed' && S.results_url) {
            renderStatus();
            $('gmStage').innerHTML = `<div class="text-center py-5"><div class="spinner-border text-primary mb-3" role="status"></div><div class="fw-bold">Battle finished — loading results…</div></div>`;
            setTimeout(() => { window.location = S.results_url; }, 900);
            return;
        }
        renderStatus();
        renderPlayers();
        renderInvite();
        renderStage();
    }

    function renderStatus() {
        const pill = $('gmStatus');
        const cls = { ready: 'ready', starting: 'live', in_progress: 'live', completed: 'done', cancelled: 'done' }[S.status] || '';
        pill.className = 'gm-status-pill ' + cls;
        pill.innerHTML = (S.status === 'completed' || S.status === 'cancelled' ? '<i class="bi bi-flag"></i>' : '<span class="live-dot"></span>') + ` <span>${esc(S.status_label)}</span>`;
    }

    function badge(p) {
        const out = [];
        if (p.is_host) out.push('<span class="gm-badge host">Host</span>');
        const map = { ready: ['ready', 'Ready'], joined: ['', 'Not ready'], paused: ['paused', 'Called'], left: ['', 'Left'], playing: null, finished: null };
        const b = map[p.status];
        if (b) out.push(`<span class="gm-badge ${b[0]}">${b[1]}</span>`);
        return out.join(' ');
    }

    function renderPlayers() {
        const box = $('gmPlayers');
        const lobby = S.status === 'waiting_for_players' || S.status === 'ready';
        $('gmPlayerCount').textContent = `${S.players.filter((p) => p.status !== 'left').length}/${S.max_players}`;
        if (lobby) {
            let html = '<div class="d-grid gap-2">';
            S.players.forEach((p, i) => {
                html += `<div class="gm-seat ${p.is_you ? 'you' : ''}">
                    <span class="gm-av c${i % 4}">${esc(p.label)}</span>
                    <div class="flex-grow-1 min-w-0"><div class="fw-bold">${p.is_you ? 'You' : esc(p.label)}</div><div class="gm-sub small">${p.is_you ? esc(p.label) + ' · your token' : 'Waiting in your queue'}</div></div>
                    <div class="d-flex gap-1 flex-wrap justify-content-end">${badge(p)}</div></div>`;
            });
            for (let i = S.players.length; i < S.max_players; i++) {
                html += `<div class="gm-seat empty"><span class="gm-av"><i class="bi bi-person"></i></span><div class="small fw-semibold">Open seat</div></div>`;
            }
            box.innerHTML = html + '</div>';
            return;
        }
        // Live scoreboard (sorted by score)
        const sorted = [...S.players].sort((a, b) => b.score - a.score || a.id - b.id);
        box.innerHTML = sorted.map((p) => {
            const changed = lastScores[p.id] !== undefined && lastScores[p.id] !== p.score;
            const tick = S.question && S.question.phase === 'open' && p.answered ? '<i class="bi bi-check-circle-fill tick" title="Answered"></i>' : '';
            return `<div class="gm-score ${p.is_you ? 'you' : ''}">
                <div class="d-flex align-items-center gap-2">
                    <span class="gm-av c${S.players.indexOf(p) % 4}" style="width:32px;height:32px;border-radius:10px;font-size:.68rem">${esc(p.label)}</span>
                    <span class="fw-bold flex-grow-1 text-truncate">${p.is_you ? 'You' : esc(p.label)} ${badge(p)} ${tick}</span>
                    <span class="pts ${changed ? 'gm-bump' : ''}">${p.score}</span>
                </div>
                <div class="bar"><div style="width:${Math.max(2, p.bar)}%"></div></div>
            </div>`;
        }).join('');
        S.players.forEach((p) => { lastScores[p.id] = p.score; });
    }

    function renderInvite() {
        const panel = $('gmInvite');
        const lobby = S.status === 'waiting_for_players' || S.status === 'ready';
        if (!(S.is_host && lobby)) { panel.classList.add('d-none'); return; }
        panel.classList.remove('d-none');
        const full = S.players.length >= S.max_players;
        $('gmCandidates').innerHTML = S.candidates.length
            ? S.candidates.map((c) => `<span class="gm-cand">${esc(c.label)} <button class="btn btn-pk btn-sm" data-invite="${c.ticket_id}" ${full ? 'disabled' : ''}>Invite</button></span>`).join('')
            : '<div class="gm-sub small">Nobody else with an account is waiting in this queue right now.</div>';
        $('gmInvites').innerHTML = S.invites.length
            ? '<div class="gm-sub small fw-bold mb-1">Sent</div>' + S.invites.map((i) => `<div class="d-flex justify-content-between small py-1"><span class="fw-semibold">${esc(i.label)}</span><span class="gm-badge ${i.status === 'accepted' ? 'ready' : ''}">${esc(i.status)}</span></div>`).join('')
            : '';
    }

    function renderStage() {
        const me = S.me || {};
        const stage = $('gmStage');

        if (S.status === 'cancelled') {
            stageKey = 'cancelled';
            const why = { expired: 'The lobby expired before the battle started.', cancelled_by_host: 'The host cancelled this battle.', everyone_left: 'Everyone left the lobby.' }[S.end_reason] || 'This battle was cancelled.';
            stage.innerHTML = `<div class="text-center py-4"><span class="pk-icon mb-3" style="width:60px;height:60px;font-size:1.6rem"><i class="bi bi-x-circle"></i></span><h2 class="h4">Battle cancelled</h2><p class="pk-muted">${esc(why)}</p><a class="btn btn-pk" href="${esc(url('hubUrl'))}">Back to the game hub</a></div>`;
            return;
        }
        if (me.status === 'left') {
            stageKey = 'left';
            stage.innerHTML = `<div class="text-center py-4"><h2 class="h4">You're no longer in this battle</h2><p class="pk-muted">Your queue position is unchanged.</p><a class="btn btn-pk" href="${esc(url('hubUrl'))}">Back to the game hub</a></div>`;
            return;
        }
        if (S.status === 'waiting_for_players' || S.status === 'ready') return renderLobby(stage, me);
        if (me.status === 'paused') {
            const k = 'paused';
            if (stageKey !== k) {
                stageKey = k;
                stage.innerHTML = `<div class="gm-alert warn"><i class="bi bi-bell fs-4"></i><div><strong>You've been called to the counter.</strong><div>Your game is paused and your score of <b id="gmPausedScore">${me.score}</b> is saved. Head to the counter — your place in the queue is not affected.</div>
                    <a class="btn btn-pk btn-sm mt-3" href="${esc(url('ticketUrl'))}">Open my token</a></div></div>`;
            }
            return;
        }
        if (S.status === 'starting' || (S.status === 'in_progress' && !S.question)) return renderCountdown(stage);
        if (S.status === 'in_progress' && S.question) return renderQuestion(stage, S.question);
    }

    function renderLobby(stage, me) {
        const k = 'lobby:' + S.status + ':' + me.status + ':' + S.can_start + ':' + S.players.length + ':' + S.ready_count;
        if (stageKey === k) return;
        stageKey = k;
        const ready = me.status === 'ready';
        const need = Math.max(0, 2 - S.players.filter((p) => p.status === 'ready').length);
        const headline = S.status === 'ready' ? 'Everyone set? The host can start.' : (S.players.length < 2 ? 'Waiting for another player to join…' : 'Waiting for players to get ready…');
        stage.innerHTML = `
            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="pk-icon ${S.status === 'ready' ? 'green' : ''}"><i class="bi ${S.status === 'ready' ? 'bi-check2-circle' : 'bi-hourglass-split'}"></i></span>
                <div><h2 class="h5 mb-1">${esc(headline)}</h2>
                <div class="gm-sub">${S.visibility === 'invite' ? 'Invite-only battle.' : 'Anyone waiting in this queue can join.'} ${need ? `${need} more ready player${need > 1 ? 's' : ''} needed.` : ''} Updates live.</div></div>
            </div>
            <ol class="small pk-muted ps-3 mb-4">
                <li>Up to ${S.max_players} players from your queue join this lobby.</li>
                <li>Everyone taps <b>I'm ready</b>; the host starts the battle.</li>
                <li>${S.question_count} questions, ${S.seconds_per_question} seconds each. Faster correct answers score more.</li>
            </ol>
            <div class="d-flex flex-wrap gap-2">
                ${S.is_host ? '' : `<button class="btn ${ready ? 'btn-ghost' : 'btn-pk'}" data-ready="${ready ? 0 : 1}"><i class="bi ${ready ? 'bi-x-circle' : 'bi-check2-circle'} me-1"></i>${ready ? 'Not ready' : "I'm ready"}</button>`}
                ${S.is_host ? `<button class="btn btn-pk" data-start ${S.can_start ? '' : 'disabled'}><i class="bi bi-play-fill me-1"></i>Start battle</button>` : ''}
                ${S.is_host ? '<button class="btn btn-ghost" data-cancel>Cancel battle</button>' : '<button class="btn btn-ghost" data-leave>Leave</button>'}
            </div>
            ${S.is_host && !S.can_start ? '<div class="gm-sub small mt-2"><i class="bi bi-info-circle me-1"></i>Start unlocks when at least 2 players are ready.</div>' : ''}`;
    }

    function renderCountdown(stage) {
        const target = S.status === 'starting' ? S.starts_at : S.next_opens_at;
        if (stageKey !== 'countdown') {
            stageKey = 'countdown';
            stage.innerHTML = `<div class="gm-countdown"><div class="mb-2"><span class="pk-eyebrow">${S.status === 'starting' ? 'Get ready' : 'Next question'}</span></div><div class="n" id="gmCount">·</div><div class="pk-muted mt-2">${S.question_count} questions · ${S.seconds_per_question}s each</div></div>`;
        }
        startTicker(() => {
            const el = $('gmCount');
            if (!el || !target) return;
            const n = Math.max(0, Math.ceil((target - serverNow()) / 1000));
            const txt = n > 0 ? String(n) : 'Go!';
            if (el.textContent !== txt) { el.textContent = txt; el.classList.remove('tick'); void el.offsetWidth; el.classList.add('tick'); }
        });
    }

    function renderQuestion(stage, q) {
        const res = localResult[q.id];
        const myChoice = q.your_choice ?? res?.choice ?? (pending && pending.qid === q.id ? pending.choice : null);
        const answered = myChoice !== null && myChoice !== undefined;
        const correctIdx = q.correct_index ?? res?.correct_index ?? null;
        const closed = q.phase === 'reveal' || serverNow() >= q.closes_at;
        const k = `q:${q.id}:${closed ? 'closed' : 'open'}:${answered ? myChoice : '-'}:${correctIdx ?? '-'}:${pending ? 'p' : ''}`;
        if (stageKey === k) return;
        const firstPaint = !stageKey.startsWith('q:' + q.id + ':');
        stageKey = k;

        const opts = q.options.map((o, i) => {
            let cls = '';
            if (correctIdx !== null) {
                if (i === correctIdx) cls = 'right';
                else if (i === myChoice) cls = 'wrong';
                else cls = 'dim';
            } else if (i === myChoice) cls = 'picked';
            const dis = answered || closed || pending ? 'disabled' : '';
            return `<button type="button" class="gm-opt ${cls}" data-choice="${i}" ${dis} aria-label="Option ${KEYS[i]}: ${esc(o)}"><span class="k">${KEYS[i]}</span><span>${esc(o)}</span></button>`;
        }).join('');

        let fb = '';
        if (pending && pending.qid === q.id) {
            fb = '<div class="gm-feedback info"><div class="spinner-border spinner-border-sm mt-1" role="status"></div><div>Checking your answer…</div></div>';
        } else if (answered && correctIdx !== null) {
            const ok = myChoice === correctIdx;
            const pts = q.your_points ?? res?.points ?? 0;
            fb = `<div class="gm-feedback ${ok ? 'ok' : 'no'}"><i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill'} fs-5"></i>
                <div class="flex-grow-1"><div class="fw-bold">${ok ? 'Correct!' : 'Not quite.'} ${ok ? `<span class="gm-plus">+${pts}</span>` : ''}</div>
                ${q.explanation || res?.explanation ? `<div class="small mt-1">${esc(q.explanation || res.explanation)}</div>` : ''}</div></div>`;
        } else if (closed) {
            fb = `<div class="gm-feedback no"><i class="bi bi-alarm fs-5"></i><div><div class="fw-bold">Time's up.</div>${q.explanation ? `<div class="small mt-1">${esc(q.explanation)}</div>` : ''}</div></div>`;
        }

        const isLast = q.number >= q.total;
        stage.innerHTML = `
            <div class="gm-q-meta">
                <div class="d-flex align-items-center gap-2"><span class="chip">Question ${q.number} of ${q.total}</span><span class="chip" style="background:var(--pk-tint-violet);color:var(--pk-violet)">${esc(q.category)}</span><span class="gm-sub small text-capitalize">${esc(q.difficulty)}</span></div>
                <div class="d-flex align-items-center gap-2"><i class="bi bi-stopwatch pk-muted"></i><span class="gm-secs" id="gmSecs">–</span></div>
            </div>
            <div class="gm-timer mt-3" id="gmTimer"><div id="gmTimerBar"></div></div>
            <h2 class="gm-q-text ${firstPaint ? 'fade-up' : ''}">${esc(q.text)}</h2>
            <div class="gm-opts" role="group" aria-label="Answer options">${opts}</div>
            ${fb}
            <div class="gm-sub small mt-3" id="gmNext"></div>`;

        startTicker(() => {
            const now = serverNow();
            const total = Math.max(1, q.closes_at - q.opens_at);
            const left = Math.max(0, q.closes_at - now);
            const bar = $('gmTimerBar'), secs = $('gmSecs'), next = $('gmNext');
            if (bar) bar.style.transform = `scaleX(${left / total})`;
            if (secs) secs.textContent = Math.ceil(left / 1000) + 's';
            $('gmTimer')?.classList.toggle('low', left < 5000);
            if (next) {
                if (left <= 0) {
                    const n = Math.max(0, Math.ceil((q.closes_at + REVEAL_MS - now) / 1000));
                    next.textContent = isLast ? `Final results in ${n}s…` : `Next question in ${n}s…`;
                } else if (answered) {
                    next.textContent = 'Answer locked in. Waiting for the others…';
                }
            }
            if (left <= 0 && !closed && stageKey === k) { stageKey = ''; renderQuestion(stage, q); }
        });
    }

    function startTicker(fn) {
        cancelAnimationFrame(rafId);
        const loop = () => { fn(); rafId = requestAnimationFrame(loop); };
        loop();
    }

    // ------------------------------------------------------------------ events
    root.addEventListener('click', async (e) => {
        const opt = e.target.closest('[data-choice]');
        if (opt && !opt.disabled && S.question) {
            const q = S.question;
            const choice = parseInt(opt.dataset.choice, 10);
            pending = { qid: q.id, choice };
            stageKey = '';
            renderQuestion($('gmStage'), q);
            try {
                const r = await api('POST', url('answerUrl'), { question_id: q.id, choice });
                pending = null;
                if (r.ok && r.json?.data) {
                    localResult[q.id] = { ...r.json.data, choice };
                } else {
                    toast(r.json?.message || 'Your answer could not be saved.', 'err');
                    if (r.status === 409 && /already answered/i.test(r.json?.message || '')) localResult[q.id] = { choice };
                }
            } catch (err) {
                pending = null;
                toast('Network error — your answer may not have been saved.', 'err');
            }
            stageKey = '';
            renderQuestion($('gmStage'), S.question || q);
            poll();
            return;
        }
        const t = e.target.closest('button');
        if (!t) return;
        if (t.hasAttribute('data-ready')) { t.disabled = true; action(url('readyUrl'), { ready: t.dataset.ready === '1' }, null); }
        else if (t.hasAttribute('data-start')) { t.disabled = true; action(url('startUrl'), {}, 'Starting!'); }
        else if (t.hasAttribute('data-leave')) { if (confirm('Leave this battle? Your queue position is not affected.')) action(url('leaveUrl'), {}, null); }
        else if (t.hasAttribute('data-cancel')) { if (confirm('Cancel this battle for everyone?')) action(url('cancelUrl'), {}, null); }
        else if (t.dataset.invite) { t.disabled = true; t.textContent = 'Sending…'; action(url('inviteUrl'), { ticket_id: parseInt(t.dataset.invite, 10) }, 'Invitation sent.'); }
    });

    // Keyboard shortcuts 1–4 / A–D for answers.
    document.addEventListener('keydown', (e) => {
        if (!S.question || e.metaKey || e.ctrlKey || e.altKey) return;
        const idx = { '1': 0, '2': 1, '3': 2, '4': 3, a: 0, b: 1, c: 2, d: 3 }[e.key.toLowerCase()];
        if (idx === undefined) return;
        const btn = document.querySelector(`.gm-opt[data-choice="${idx}"]`);
        if (btn && !btn.disabled) btn.click();
    });

    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });

    apply(S);
    schedule();
})();
