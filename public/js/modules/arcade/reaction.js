/* Reaction Challenge: server-chosen delays; reported times are range-checked on the server. */
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    let api, game, round, times, state, goAt, timer, keyHandler, submitted;

    function pad(st, icon, big, sub) {
        state = st;
        const p = $('rxPad');
        p.dataset.state = st;
        p.innerHTML = `<i class="bi ${icon}"></i><span class="big">${big}</span>${sub ? `<span class="sub">${sub}</span>` : ''}`;
    }

    function rounds() {
        $('rxRounds').innerHTML = Array.from({ length: game.rounds }, (_, i) =>
            `<div class="rx-round ${i === round && times.length < game.rounds ? 'cur' : ''} ${times[i] ? 'done' : ''}"><div class="l">R${i + 1}</div><div class="v">${times[i] ? times[i] + '<small> ms</small>' : '—'}</div></div>`).join('');
        $('rxRound').textContent = Math.min(round + 1, game.rounds);
    }

    function arm() {
        pad('wait', 'bi-hourglass', 'Wait for green…', 'Do not tap yet');
        clearTimeout(timer);
        timer = setTimeout(() => { goAt = performance.now(); pad('go', 'bi-lightning-charge-fill', 'TAP!', ''); }, game.delays[round]);
    }

    function hit() {
        if (submitted) return;
        if (state === 'idle' || state === 'shown' || state === 'early') { arm(); return; }
        if (state === 'wait') {
            clearTimeout(timer);
            pad('early', 'bi-exclamation-octagon', 'Too soon!', 'Tap to retry this round');
            api.toast('Too soon: wait for green.', 'bad');
            return;
        }
        if (state === 'go') {
            const ms = Math.round(performance.now() - goAt);
            if (ms > 2000) { pad('early', 'bi-cup-hot', 'Too slow', 'Over 2 seconds. Tap to retry this round'); return; }
            times.push(ms);
            round++;
            const avg = Math.round(times.reduce((a, b) => a + b, 0) / times.length);
            api.setScore(Math.max(0, 1000 - avg));
            rounds();
            if (times.length >= game.rounds) {
                submitted = true;
                pad('shown', 'bi-check2-circle', ms + ' ms', 'Checking your results…');
                api.finish({ times });
                return;
            }
            const word = ms < 220 ? 'Lightning!' : ms < 300 ? 'Sharp!' : ms < 400 ? 'Nice.' : 'Keep going.';
            pad('shown', 'bi-stopwatch', ms + ' ms', `${word} Tap for round ${round + 1}`);
        }
    }

    window.ArcadeGames.reaction = {
        begin(g, coreApi) {
            api = coreApi; game = g; round = 0; times = []; submitted = false;
            api.timer.stop(); // reaction times are measured per round, so the HUD clock is hidden
            const clock = document.getElementById('arcTime'); if (clock) clock.parentElement.hidden = true;
            pad('idle', 'bi-hand-index-thumb', 'Tap to begin round 1', 'or press Space');
            rounds();
            const p = $('rxPad');
            p.onpointerdown = e => { e.preventDefault(); hit(); };
            p.onclick = e => { if (e.detail === 0) hit(); }; // keyboard activation only (pointer handled above)
            if (keyHandler) document.removeEventListener('keydown', keyHandler);
            keyHandler = e => { if (!$('arcPlay').hidden && (e.code === 'Space') && !e.repeat) { e.preventDefault(); hit(); } };
            document.addEventListener('keydown', keyHandler);
        },
        stop() { clearTimeout(timer); submitted = true; },
        pause() { if (state === 'wait' || state === 'go') { clearTimeout(timer); pad('early', 'bi-pause-circle', 'Paused', 'Tap to restart this round'); } },
    };
})();
