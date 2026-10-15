/* Memory Match: records every move as [a, b]; the server replays the list against its deck. */
(function () {
    'use strict';
    let deck = [], open = [], moves = [], matched = 0, lock = false, api = null, done = false, timeouts = [];
    const $ = id => document.getElementById(id);

    function render() {
        const grid = $('mmGrid');
        grid.innerHTML = deck.map((face, i) => `
            <button type="button" class="mm-card fade-up" style="animation-delay:${i * 0.025}s" data-i="${i}" aria-label="Card ${i + 1}, hidden">
                <span class="mm-inner"><span class="mm-face mm-back"></span><span class="mm-face mm-front"><i class="bi ${api.esc(face)}"></i></span></span>
            </button>`).join('');
        grid.querySelectorAll('.mm-card').forEach(btn => btn.addEventListener('click', () => flip(+btn.dataset.i, btn)));
    }

    function flip(i, btn) {
        if (lock || done || btn.classList.contains('open') || btn.classList.contains('done')) return;
        btn.classList.add('open');
        btn.setAttribute('aria-label', `Card ${i + 1}, ${deck[i].replace('bi-', '').replace(/-/g, ' ')}`);
        open.push([i, btn]);
        if (open.length < 2) return;

        const [[a, ba], [b, bb]] = open; open = [];
        moves.push([a, b]);
        $('mmMoves').textContent = moves.length;
        if (deck[a] === deck[b]) {
            ba.classList.add('done'); bb.classList.add('done');
            matched++;
            $('mmPairs').textContent = matched;
            api.setScore(Math.max(50, 1000 - Math.max(0, moves.length - 8) * 35));
            if (matched === deck.length / 2) {
                done = true;
                timeouts.push(setTimeout(() => api.finish({ moves }), 550));
            }
        } else {
            lock = true;
            ba.classList.add('miss'); bb.classList.add('miss');
            timeouts.push(setTimeout(() => {
                [ba, bb].forEach(x => { x.classList.remove('open', 'miss'); x.setAttribute('aria-label', 'Hidden card'); });
                lock = false;
            }, 750));
        }
    }

    window.ArcadeGames.memory = {
        begin(game, coreApi) {
            api = coreApi; deck = game.deck; open = []; moves = []; matched = 0; lock = false; done = false;
            $('mmMoves').textContent = 0; $('mmPairs').textContent = 0;
            render();
        },
        stop() { timeouts.forEach(clearTimeout); timeouts = []; lock = false; },
    };
})();
