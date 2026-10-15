/* UNO client: renders the server's view and animates its event list. The server holds the
   deck, all hands and the rules; this file only sends intents (play / draw / pass). */
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    const CN = { R: 'Red', G: 'Green', B: 'Blue', Y: 'Yellow' };
    const CV = { R: 'var(--R)', G: 'var(--G)', B: 'var(--B)', Y: 'var(--Y)' };
    let api, st, busy, unoArmed, counts, lastHand, alive, finished;

    const wait = ms => new Promise(r => setTimeout(r, ms));
    const isWild = c => c[0] === 'W';
    const val = c => isWild(c) ? c : c.slice(1);

    function sym(c) {
        const v = val(c);
        if (c === 'W') return '<i class="bi bi-stars"></i>';
        if (c === 'W4') return '+4';
        if (v === 'S') return '<i class="bi bi-slash-circle"></i>';
        if (v === 'R') return '<i class="bi bi-arrow-left-right"></i>';
        if (v === 'D') return '+2';
        return v;
    }
    function cardName(c, color) {
        if (c === 'W') return 'Wild' + (color ? ' → ' + CN[color] : '');
        if (c === 'W4') return 'Wild Draw Four' + (color ? ' → ' + CN[color] : '');
        const v = val(c);
        return CN[c[0]] + ' ' + ({ S: 'Skip', R: 'Reverse', D: 'Draw Two' }[v] || v);
    }
    function cardHtml(c, extra) {
        const color = isWild(c) ? 'W' : c[0];
        const s = sym(c);
        return `<span class="uno-card c-${color} ${extra || ''}" aria-hidden="true"><span class="corner tl">${s}</span><span class="oval"><span class="sym">${s}</span></span><span class="corner br">${s}</span></span>`;
    }
    const back = () => '<span class="uno-card uno-back"><span class="oval"><span class="sym"></span></span></span>';

    function name(p) { return st.players[p]?.name || 'Player'; }

    function log(text, mine) {
        $('unoLog').innerHTML = `<span class="${mine ? 'mine' : ''}">${text}</span>`;
    }

    function renderOpps(turn) {
        const opps = st.players.slice(1);
        $('unoOpps').style.setProperty('--n', opps.length);
        $('unoOpps').innerHTML = opps.map((p, k) => {
            const i = k + 1, n = counts[i];
            return `<div class="uno-seat ${turn === i ? 'turn' : ''}" id="unoSeat${i}">
                <span class="av" aria-hidden="true">${api.esc(p.name[0])}</span>
                <div class="nm">${api.esc(p.name)}</div>
                <div class="ct">${n} card${n === 1 ? '' : 's'}${turn === i ? ' · thinking' : ''}</div>
                <div class="uno-fan" aria-hidden="true">${Array.from({ length: Math.min(n, 9) }, back).join('')}</div>
                ${n === 1 ? '<span class="uno-bubble">UNO</span>' : ''}
            </div>`;
        }).join('');
    }

    function renderCenter(top, color, dir, land) {
        $('unoDiscard').innerHTML = cardHtml(top, land ? 'land' : '');
        $('unoDiscard').setAttribute('aria-label', 'Top card: ' + cardName(top, isWild(top) ? color : null));
        $('unoColor').style.background = CV[color] || 'var(--K)';
        $('unoColorName').textContent = CN[color] || '';
        $('unoDir').classList.toggle('rev', dir === -1);
    }

    function renderHand() {
        const myTurn = st.turn === 0 && st.winner === null && !busy;
        const hand = $('unoHand');
        hand.classList.toggle('idle', !myTurn);
        hand.classList.toggle('many', st.hand.length > 9);
        hand.innerHTML = st.hand.map((h, i) => {
            const isNew = i >= lastHand;
            return `<button type="button" class="h ${myTurn ? (h.playable ? 'ok' : 'no') : ''} ${isNew ? 'new' : ''}" data-i="${i}" role="listitem" aria-label="${cardName(h.card)}${myTurn && h.playable ? ', playable' : ''}" ${myTurn && h.playable ? '' : 'aria-disabled="true"'}>${cardHtml(h.card)}</button>`;
        }).join('');
        lastHand = st.hand.length;
        hand.querySelectorAll('.h').forEach(b => b.addEventListener('click', () => play(+b.dataset.i)));
        $('unoCount').textContent = st.hand.length;
        $('unoDraw').disabled = !myTurn || st.drawn !== null;
        $('unoDeck').textContent = st.deck + ' left';
        $('unoPass').hidden = !(myTurn && st.drawn !== null);
        const canUno = myTurn && st.hand.length === 2 && st.hand.some(h => h.playable);
        $('unoCall').disabled = !canUno;
        if (!canUno) setUno(false);
        $('unoTurn').textContent = st.winner !== null ? 'Game over' : myTurn ? (st.drawn !== null ? 'Play the drawn card or pass' : 'Your turn') : 'Waiting…';
    }

    function setUno(on) {
        unoArmed = on;
        $('unoCall').classList.toggle('armed', on);
        $('unoCall').setAttribute('aria-pressed', on ? 'true' : 'false');
        $('unoCall').textContent = on ? 'UNO called' : 'UNO!';
    }

    function renderAll(land) {
        counts = st.players.map(p => p.count);
        renderOpps(st.winner === null ? st.turn : -1);
        renderCenter(st.top, st.color, st.dir, land);
        renderHand();
    }

    async function animate(events, next) {
        // Replay what happened (CPU turns etc.) one step at a time, then show the new state.
        let top = st.top, color = st.color, dir = st.dir;
        for (const e of events) {
            if (!alive) return;
            const who = e.p === 0 ? 'You' : api.esc(name(e.p));
            const mine = e.p === 0;
            if (e.t === 'play') {
                top = e.card; color = e.color;
                if (!mine) counts[e.p]--;
                renderCenter(top, color, dir, true);
                renderOpps(e.p);
                log(`${who} played <b>${api.esc(cardName(e.card, isWild(e.card) ? e.color : null))}</b>`, mine);
            } else if (e.t === 'draw') {
                if (!mine) counts[e.p] += e.n;
                renderOpps(mine ? -1 : e.p);
                log(mine ? `You drew ${e.n} card${e.n === 1 ? '' : 's'}${e.cards && e.cards.length === 1 ? `: <b>${api.esc(cardName(e.cards[0]))}</b>` : ''}` : `${who} drew ${e.n} card${e.n === 1 ? '' : 's'}`, mine);
            } else if (e.t === 'penalty') {
                if (!mine) counts[e.p] += e.n;
                renderOpps(-1);
                log(`<i class="bi bi-exclamation-triangle"></i>${who} forgot to call UNO: +${e.n} cards`, mine);
            } else if (e.t === 'uno') {
                log(`<i class="bi bi-megaphone"></i>${who} ${mine ? 'call' : 'calls'} UNO!`, mine);
            } else if (e.t === 'skip') {
                log(`${mine ? 'You are' : who + ' is'} skipped`, mine);
                const seat = $('unoSeat' + e.p); if (seat) seat.classList.add('flash');
            } else if (e.t === 'reverse') {
                dir = -dir; renderCenter(top, color, dir, false);
                log('<i class="bi bi-arrow-left-right"></i>Direction reversed', mine);
            } else if (e.t === 'pass') {
                log(`${who} ${mine ? 'pass' : 'passes'}`, mine);
            } else if (e.t === 'win') {
                log(`<i class="bi bi-trophy"></i>${who} ${mine ? 'win' : 'wins'}!`, mine);
            }
            await wait(mine ? 450 : 750);
        }
        st = next;
        renderAll(false);
    }

    async function send(body) {
        busy = true; renderHand();
        const r = await api.action(body);
        if (!r.ok) {
            busy = false; renderHand();
            api.toast(r.message, 'bad');
            return;
        }
        const next = r.data;
        await animate(next.events || [], next);
        busy = false;
        if (!alive) return;
        renderHand();
        if (st.winner !== null && !finished) {
            finished = true;
            await wait(600);
            api.finish({});
        } else if (st.turn === 0) {
            const playable = st.hand.some(h => h.playable);
            if (st.drawn === null) log(playable ? 'Your turn: play a raised card' : 'No match: draw from the deck', true);
        }
    }

    function pickColor() {
        return new Promise(resolve => {
            const ov = document.createElement('div');
            ov.className = 'uno-pick'; ov.setAttribute('role', 'dialog'); ov.setAttribute('aria-label', 'Choose a colour');
            ov.innerHTML = `<div class="box"><div class="fw-bold">Choose the next colour</div>
                <div class="grid">${['R', 'B', 'Y', 'G'].map(c => `<button type="button" data-c="${c}" style="background:${CV[c]}">${CN[c]}</button>`).join('')}</div>
                <button type="button" class="btn btn-ghost btn-sm mt-3" data-c="">Cancel</button></div>`;
            $('unoTable').appendChild(ov);
            ov.querySelectorAll('button').forEach(b => b.addEventListener('click', () => { ov.remove(); resolve(b.dataset.c || null); }));
            ov.querySelector('button').focus();
        });
    }

    async function play(i) {
        if (busy || st.turn !== 0 || st.winner !== null) return;
        const h = st.hand[i];
        if (!h.playable) {
            api.toast(st.drawn !== null ? 'Play the card you drew, or pass.' : (h.card === 'W4' ? 'Wild Draw Four only when you have no card of the current colour.' : 'That card does not match.'), 'bad');
            const btn = $('unoHand').querySelector(`[data-i="${i}"]`); if (btn) { btn.classList.remove('arc-shake'); void btn.offsetWidth; btn.classList.add('arc-shake'); }
            return;
        }
        let color = null;
        if (isWild(h.card)) { color = await pickColor(); if (!color) return; }
        const body = { action: 'play', card: i, uno: unoArmed };
        if (color) body.color = color;
        setUno(false);
        send(body);
    }

    window.ArcadeGames.uno = {
        begin(game, coreApi) {
            api = coreApi; st = game; busy = false; alive = true; finished = false; lastHand = game.hand.length;
            setUno(false);
            renderAll(true);
            api.setScore(0);
            log(`Game on! ${api.esc(st.players.slice(1).map(p => p.name).join(', '))} ${st.players.length > 2 ? 'are' : 'is'} at the table. You go first.`, true);
            $('unoDraw').onclick = () => { if (!busy && st.turn === 0 && st.drawn === null) send({ action: 'draw' }); };
            $('unoPass').onclick = () => { if (!busy) send({ action: 'pass' }); };
            $('unoCall').onclick = () => { setUno(!unoArmed); if (unoArmed) api.toast('UNO called. Now play your card.', 'ok'); };
            $('unoQuit').onclick = () => {
                if (busy || finished) return;
                if (window.confirm('Leave this game? It will count as a forfeit with no points.')) { finished = true; api.finish({ forfeit: true }); }
            };
        },
        stop() { alive = false; document.querySelector('.uno-pick')?.remove(); },
    };
})();
