/* WaitWise game hub: picker + Memory Match, Tic-Tac-Toe, Chess, Ludo. Quiz lives in ticket.blade.php. */
(function () {
    const $ = id => document.getElementById(id);
    const ME = { name: 'You', color: '#4f3df5' };
    const OPP = [
        { name: 'Sita', token: 'D-21', color: '#ff6b5b' },
        { name: 'Aarav', token: 'B-44', color: '#22c55e' },
        { name: 'Bikash', token: 'E-115', color: '#ffb020' },
    ];
    const GAMES = {
        play: [
            { id: 'quiz', em: '🧠', name: 'Quick Quiz', sub: '6 timed Nepal questions', players: '1 player' },
            { id: 'memory', em: '🃏', name: 'Memory Match', sub: 'Flip & match 8 pairs', players: '1 player' },
            { id: 'ttt', em: '❌', name: 'Tic-Tac-Toe', sub: 'Beat the computer', players: '1 vs CPU' },
            { id: 'chess', em: '♟️', name: 'Chess', sub: 'Quick game vs computer', players: '1 vs CPU' },
        ],
        compete: [
            { id: 'ludo', em: '🎲', name: 'Ludo', sub: 'Race 4 players to home', players: '4 players' },
            { id: 'chess', em: '♟️', name: 'Chess', sub: 'Head-to-head match', players: '2 players' },
            { id: 'quizc', em: '⚔️', name: 'Quiz Battle', sub: 'Live leaderboard', players: '4 players' },
            { id: 'ttt', em: '❌', name: 'Tic-Tac-Toe', sub: 'Best of quick wits', players: '2 players' },
        ],
    };
    let mode = 'play', cleanup = null;

    const css = document.createElement('style');
    css.textContent = `
    .gpick{border:2px solid #ebe9f7;border-radius:20px;padding:1rem;cursor:pointer;transition:all .2s;background:#fff;height:100%;text-align:center}
    .gpick:hover{transform:translateY(-4px);border-color:var(--ww-indigo);background:#f7f6ff}
    .gpick .em{font-size:2.2rem;display:block}
    .mm-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;max-width:380px;margin:auto}
    .mm-card{aspect-ratio:1;border-radius:14px;background:linear-gradient(135deg,var(--ww-indigo),var(--ww-violet));display:grid;place-items:center;font-size:1.9rem;cursor:pointer;transition:transform .3s;color:transparent;user-select:none}
    .mm-card.open,.mm-card.done{background:#fff;border:2px solid #ebe9f7;color:inherit;transform:rotateY(180deg)}
    .mm-card.done{border-color:var(--ww-mint);background:#f0fbf4}
    .ttt{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;max-width:300px;margin:auto}
    .ttt div{aspect-ratio:1;border-radius:16px;background:#f3f1ff;display:grid;place-items:center;font-size:2.6rem;font-weight:800;cursor:pointer;transition:background .2s}
    .ttt div:hover{background:#e7e3ff}
    .ttt .X{color:var(--ww-indigo)} .ttt .O{color:var(--ww-coral)} .ttt .win{background:var(--ww-lime)}
    .chess{display:grid;grid-template-columns:repeat(8,1fr);max-width:400px;margin:auto;border-radius:12px;overflow:hidden;border:4px solid var(--ww-ink)}
    .chess div{aspect-ratio:1;display:grid;place-items:center;font-size:clamp(1.3rem,5.5vw,2.1rem);cursor:pointer;user-select:none;line-height:1}
    .chess .l{background:#eeebff} .chess .d{background:#8f84f0}
    .chess .sel{background:var(--ww-lime)!important} .chess .tgt{box-shadow:inset 0 0 0 4px rgba(198,244,50,.95)} .chess .last{box-shadow:inset 0 0 0 4px rgba(255,176,32,.8)}
    .chess .w{color:#fff;text-shadow:0 0 2px #000,0 0 3px #000} .chess .b{color:#14123a}
    .ludo{display:grid;grid-template-columns:repeat(8,1fr);gap:3px;max-width:400px;margin:auto}
    .ludo .c{aspect-ratio:1;border-radius:8px;background:#f3f1ff;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:2px;position:relative}
    .ludo .c.start{outline:3px solid var(--sc);outline-offset:-3px}
    .ludo .in{background:transparent}
    .ludo .base{border-radius:12px;display:flex;flex-wrap:wrap;align-content:center;justify-content:center;gap:4px;font-size:.7rem;font-weight:700;color:#fff}
    .pawn{width:44%;aspect-ratio:1;border-radius:50%;border:2px solid #fff;box-shadow:0 2px 4px rgba(0,0,0,.3);transition:transform .2s}
    .pawn.mov{cursor:pointer;animation:bump .8s infinite}
    .dice{width:70px;height:70px;border-radius:18px;background:#fff;border:3px solid var(--ww-ink);display:grid;place-items:center;font-size:2.4rem;font-weight:800;margin:auto}
    .dice.roll{animation:shake .4s}
    `;
    document.head.appendChild(css);

    function stop() { if (cleanup) { cleanup(); cleanup = null; } }
    function hideAll() { ['lobby', 'quiz', 'result', 'picker', 'arena'].forEach(id => $(id).classList.add('d-none')); }

    window.showPicker = function (m) {
        stop(); mode = m; if (window.G) clearInterval(G.timer);
        hideAll();
        const p = $('picker'); p.classList.remove('d-none');
        p.innerHTML = `<h5 class="fw-bold mb-3 text-center">${m === 'play' ? '🎮 Pick a game to play' : '⚔️ Pick a game — compete with people waiting here'}</h5>
            <div class="row g-3">${GAMES[m].map(g => `<div class="col-6 col-md-3"><div class="gpick fade-up" data-g="${g.id}"><span class="em">${g.em}</span><div class="fw-bold">${g.name}</div><div class="small text-muted-ww">${g.sub}</div><span class="chip mt-2" style="font-size:.7rem">${g.players}</span></div></div>`).join('')}</div>`;
        p.querySelectorAll('.gpick').forEach(el => el.onclick = () => launch(el.dataset.g));
    };

    function launch(id) {
        stop(); hideAll();
        if (id === 'quiz' || id === 'quizc') return startGame(id === 'quizc');
        const a = $('arena'); a.classList.remove('d-none');
        ({ memory, ttt, chess, ludo })[id](a);
    }

    function header(title, right) {
        return `<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <button class="btn btn-sm btn-outline-secondary rounded-4" onclick="showPicker('${mode}')"><i class="bi bi-arrow-left"></i> Games</button>
            <h5 class="fw-bold mb-0">${title}</h5><span class="fw-bold small" id="gStat">${right || ''}</span></div>`;
    }

    async function gameOver(em, title, sub, pts, party) {
        stop(); hideAll();
        const r = $('result'); r.classList.remove('d-none');
        r.innerHTML = `<div style="font-size:3.4rem">${em}</div><h3 class="fw-bold font-display">${title}</h3><p class="text-muted-ww">${sub}</p>
            <div class="points-pop mb-3" style="font-size:1.3rem">+${pts} WaitPoints</div>
            <div class="d-flex justify-content-center gap-2 flex-wrap"><button class="btn btn-ww" onclick="showPicker('${mode}')">Play another game</button>
            <button class="btn btn-outline-secondary rounded-4" onclick="document.querySelector('[data-panel=explore]').click()">Explore nearby</button></div>`;
        if (party) confetti();
        try { render(await (await post(URLS.points, { points: pts })).json()); } catch (e) {}
    }

    // ---------------- Memory Match ----------------
    function memory(a) {
        const icons = shuffle([...'🏔️🛕🥟☕🐘🦏🪁🌺'.match(/\p{Extended_Pictographic}️?/gu)].flatMap(e => [e, e]));
        let open = [], moves = 0, done = 0, secs = 0, lock = false;
        a.innerHTML = header('🃏 Memory Match', 'Moves: 0 · 0s') + `<div class="mm-grid">${icons.map((e, i) => `<div class="mm-card" data-i="${i}">${e}</div>`).join('')}</div>`;
        const t = setInterval(() => { secs++; stat(); }, 1000); cleanup = () => clearInterval(t);
        const stat = () => $('gStat').textContent = `Moves: ${moves} · ${secs}s`;
        a.querySelectorAll('.mm-card').forEach(c => c.onclick = () => {
            if (lock || c.classList.contains('open') || c.classList.contains('done')) return;
            c.classList.add('open'); open.push(c);
            if (open.length === 2) {
                moves++; stat(); const [x, y] = open;
                if (x.textContent === y.textContent) {
                    x.classList.add('done'); y.classList.add('done'); open = []; done++;
                    if (done === 8) { const pts = Math.max(20, 120 - moves * 4); setTimeout(() => gameOver('🧠', 'All pairs matched!', `${moves} moves in ${secs} seconds`, pts, true), 500); }
                } else { lock = true; setTimeout(() => { x.classList.remove('open'); y.classList.remove('open'); open = []; lock = false; }, 700); }
            }
        });
    }

    // ---------------- Tic-Tac-Toe ----------------
    function ttt(a) {
        const opp = mode === 'compete' ? OPP[0] : { name: 'CPU', token: 'AI' };
        const b = Array(9).fill(''); let over = false;
        const L = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];
        const win = (bd, p) => L.find(l => l.every(i => bd[i] === p));
        a.innerHTML = header(`❌ Tic-Tac-Toe · You (X) vs ${opp.name} (O)`, 'Your move') + `<div class="ttt">${b.map((_, i) => `<div data-i="${i}"></div>`).join('')}</div>`;
        const cells = [...a.querySelectorAll('.ttt div')];
        const draw = () => cells.forEach((c, i) => { c.textContent = b[i]; c.className = b[i]; });
        function mm(bd, p) { // minimax with a little randomness so it can be beaten in "compete"
            if (win(bd, 'O')) return { s: 1 }; if (win(bd, 'X')) return { s: -1 }; if (bd.every(Boolean)) return { s: 0 };
            let best = null;
            bd.forEach((v, i) => { if (v) return; bd[i] = p; const s = mm(bd, p === 'O' ? 'X' : 'O').s; bd[i] = '';
                if (!best || (p === 'O' ? s > best.s : s < best.s)) best = { s, i }; });
            return best;
        }
        function check() {
            const w = win(b, 'X') || win(b, 'O');
            if (w) { over = true; w.forEach(i => cells[i].classList.add('win')); const me = b[w[0]] === 'X';
                setTimeout(() => gameOver(me ? '🏆' : '😅', me ? 'You won!' : `${opp.name} won this one`, 'Tic-Tac-Toe', me ? 60 : 15, me), 900); return true; }
            if (b.every(Boolean)) { over = true; setTimeout(() => gameOver('🤝', "It's a draw!", 'Tic-Tac-Toe', 30, false), 700); return true; }
        }
        cells.forEach((c, i) => c.onclick = () => {
            if (over || b[i]) return; b[i] = 'X'; draw(); if (check()) return;
            $('gStat').textContent = `${opp.name} is thinking…`;
            setTimeout(() => {
                const free = b.map((v, k) => v ? null : k).filter(k => k !== null);
                const k = Math.random() < (mode === 'compete' ? .25 : .15) ? free[Math.floor(Math.random() * free.length)] : mm(b, 'O').i;
                b[k] = 'O'; draw(); $('gStat').textContent = 'Your move'; check();
            }, 450);
        });
    }

    // ---------------- Chess ----------------
    function chess(a) {
        if (typeof Chess === 'undefined') { a.innerHTML = header('♟️ Chess') + '<p class="text-center text-muted-ww">Chess engine failed to load (offline?).</p>'; return; }
        const opp = mode === 'compete' ? OPP[0] : { name: 'CPU', token: 'AI' };
        const g = new Chess(); let sel = null, last = [], busy = false;
        const P = { k: '♚', q: '♛', r: '♜', b: '♝', n: '♞', p: '♟' }, V = { p: 1, n: 3, b: 3, r: 5, q: 9, k: 0 };
        a.innerHTML = header(`♟️ Chess · You (white) vs ${opp.name} ${opp.token !== 'AI' ? '(' + opp.token + ')' : ''}`, 'Your move') +
            `<div class="chess" id="cb"></div><div class="text-center mt-3"><button class="btn btn-sm btn-outline-secondary rounded-4" id="resign">🏳️ Resign</button></div>`;
        $('resign').onclick = () => finish('resign');
        function draw() {
            const bd = g.board(), tg = sel ? g.moves({ square: sel, verbose: true }).map(m => m.to) : [];
            $('cb').innerHTML = bd.map((row, r) => row.map((p, c) => {
                const sq = 'abcdefgh'[c] + (8 - r);
                return `<div data-sq="${sq}" class="${(r + c) % 2 ? 'd' : 'l'} ${sq === sel ? 'sel' : ''} ${tg.includes(sq) ? 'tgt' : ''} ${last.includes(sq) ? 'last' : ''}">${p ? `<span class="${p.color}">${P[p.type]}</span>` : ''}</div>`;
            }).join('')).join('');
            $('cb').querySelectorAll('div').forEach(d => d.onclick = () => click(d.dataset.sq));
        }
        function click(sq) {
            if (busy || g.turn() !== 'w') return;
            const p = g.get(sq);
            if (sel && g.moves({ square: sel, verbose: true }).some(m => m.to === sq)) {
                const m = g.move({ from: sel, to: sq, promotion: 'q' }); last = [m.from, m.to]; sel = null; draw();
                if (!end()) { busy = true; $('gStat').textContent = `${opp.name} is thinking…`; setTimeout(ai, 500); }
                return;
            }
            sel = p && p.color === 'w' ? sq : null; draw();
        }
        function ai() {
            const ms = g.moves({ verbose: true });
            let best = null, bs = -1e9;
            ms.forEach(m => {
                g.move(m); let s = Math.random() * .8;
                if (g.in_checkmate()) s += 1000;
                if (m.captured) s += V[m.captured] * 10;
                if (g.in_check()) s += 2;
                // avoid hanging the moved piece
                if (g.moves({ verbose: true }).some(r => r.to === m.to)) s -= V[m.piece] * 8;
                g.undo(); if (s > bs) { bs = s; best = m; }
            });
            const m = g.move(best); last = [m.from, m.to]; busy = false; draw();
            $('gStat').textContent = g.in_check() ? '⚠️ Check! Your move' : 'Your move'; end();
        }
        function end() {
            if (!g.game_over()) return false;
            finish(g.in_checkmate() ? (g.turn() === 'b' ? 'win' : 'loss') : 'draw'); return true;
        }
        function finish(r) {
            busy = true;
            const o = { win: ['🏆', 'Checkmate — you win!', 150, true], loss: ['♚', `${opp.name} wins by checkmate`, 25, false], draw: ['🤝', 'Draw', 70, false], resign: ['🏳️', 'You resigned', 10, false] }[r];
            setTimeout(() => gameOver(o[0], o[1], `Chess vs ${opp.name} · ${Math.ceil(g.history().length / 2)} moves`, o[2], o[3]), 700);
        }
        draw();
    }

    // ---------------- Ludo (4 players, 2 pawns each, 28-square track) ----------------
    function ludo(a) {
        const N = 28, players = [{ ...ME, token: T.token, bot: false }, ...OPP.map(o => ({ ...o, bot: true }))]
            .map((p, k) => ({ ...p, start: k * 7, pawns: [-1, -1] })); // -1 = base, 0..N-1 progress, N = home
        let turn = 0, dice = 0, waiting = false, over = false;
        const cellRC = i => i < 7 ? [0, i] : i < 14 ? [i - 7, 7] : i < 21 ? [7, 7 - (i - 14)] : [7 - (i - 21), 0];
        const abs = (p, prog) => (p.start + prog) % N;
        a.innerHTML = header('🎲 Ludo · first to bring both pawns home', '') + `
            <div class="row g-3 align-items-center"><div class="col-md-8"><div class="ludo" id="lb8"></div></div>
            <div class="col-md-4 text-center"><div class="dice mb-2" id="dice">🎲</div><div class="fw-bold mb-2" id="lmsg"></div>
            <button class="btn btn-ww w-100 mb-3" id="roll">Roll dice</button><div id="lplayers" class="text-start small"></div>
            <div class="small text-muted-ww mt-2">Roll 6 or 1 to leave base. Land on a rival to send them back. A 6 rolls again.</div></div></div>`;
        function draw(movable = []) {
            let html = '';
            for (let r = 0; r < 8; r++) for (let c = 0; c < 8; c++) {
                const edge = r === 0 || c === 0 || r === 7 || c === 7;
                if (edge) {
                    const i = [...Array(N).keys()].find(k => { const [rr, cc] = cellRC(k); return rr === r && cc === c; });
                    const owner = players.find(p => p.start === i);
                    const pawns = players.flatMap((p, pi) => p.pawns.map((pr, k) => ({ p, pi, k, pr }))).filter(x => x.pr >= 0 && x.pr < N && abs(x.p, x.pr) === i);
                    html += `<div class="c ${owner ? 'start' : ''}" style="${owner ? `--sc:${owner.color};background:${owner.color}22` : ''}">${pawns.map(x => pawnHtml(x, movable)).join('')}</div>`;
                } else html += `<div class="c in" data-r="${r}" data-c="${c}"></div>`;
            }
            $('lb8').innerHTML = html;
            // bases + home counts in the inner 6x6
            const inner = [...$('lb8').querySelectorAll('.in')];
            players.forEach((p, pi) => {
                const [br, bc] = [[1, 1], [1, 6], [6, 6], [6, 1]][pi];
                const cell = inner.find(e => +e.dataset.r === br && +e.dataset.c === bc);
                cell.style.gridColumn = 'span 1';
                const inBase = p.pawns.map((pr, k) => ({ p, pi, k, pr })).filter(x => x.pr === -1);
                const home = p.pawns.filter(pr => pr === N).length;
                cell.className = 'c base'; cell.style.background = p.color;
                cell.innerHTML = inBase.map(x => pawnHtml(x, movable)).join('') + (home ? `<span>🏠${home}</span>` : '');
            });
            $('lb8').querySelectorAll('.pawn.mov').forEach(el => el.onclick = () => move(0, +el.dataset.k));
            $('lplayers').innerHTML = players.map((p, i) => `<div class="d-flex align-items-center gap-2 mb-1 ${i === turn ? 'fw-bold' : ''}"><span class="avatar" style="width:22px;height:22px;background:${p.color};font-size:.65rem">${p.name[0]}</span>${p.name} <span class="text-muted-ww">${p.token}</span><span class="ms-auto">${p.pawns.filter(x => x === N).length}/2 🏠</span>${i === turn ? '◀' : ''}</div>`).join('');
        }
        const pawnHtml = (x, movable) => `<div class="pawn ${x.pi === 0 && movable.includes(x.k) ? 'mov' : ''}" data-k="${x.k}" style="background:${x.p.color}"></div>`;
        const canMove = (p, k, d) => { const pr = p.pawns[k]; if (pr === N) return false; if (pr === -1) return d === 6 || d === 1; return pr + d <= N; };
        function rollFor() {
            dice = 1 + Math.floor(Math.random() * 6);
            const el = $('dice'); el.classList.remove('roll'); void el.offsetWidth; el.classList.add('roll'); el.textContent = '⚀⚁⚂⚃⚄⚅'[dice - 1];
            const p = players[turn], opts = [0, 1].filter(k => canMove(p, k, dice));
            $('lmsg').textContent = `${p.name} rolled ${dice}`;
            if (!opts.length) { draw(); return setTimeout(next, 700); }
            if (p.bot) {
                // bot: prefer capture, then entering, then furthest pawn
                const score = k => { const pr = p.pawns[k], np = pr === -1 ? 0 : pr + dice, sq = abs(p, np);
                    return (np === N ? 50 : 0) + (pr === -1 ? 20 : 0) + (players.some((q, qi) => q !== p && q.pawns.some(x => x >= 0 && x < N && abs(q, x) === sq)) ? 40 : 0) + np; };
                draw(); setTimeout(() => move(turn, opts.sort((x, y) => score(y) - score(x))[0]), 600);
            } else if (opts.length === 1) { draw(); setTimeout(() => move(0, opts[0]), 400); }
            else { waiting = true; draw(opts); $('lmsg').textContent = `You rolled ${dice} — tap a glowing pawn`; }
        }
        function move(pi, k) {
            if (over) return; waiting = false;
            const p = players[pi]; p.pawns[k] = p.pawns[k] === -1 ? 0 : p.pawns[k] + dice;
            if (p.pawns[k] < N) {
                const sq = abs(p, p.pawns[k]);
                if (!players.some(q => q.start === sq)) players.forEach(q => { if (q !== p) q.pawns = q.pawns.map(x => x >= 0 && x < N && abs(q, x) === sq ? (($('lmsg').textContent = `💥 ${p.name} captured ${q.name}!`), -1) : x); });
            }
            draw();
            if (p.pawns.every(x => x === N)) {
                over = true; const me = pi === 0;
                return setTimeout(() => gameOver(me ? '🏆' : '🎲', me ? 'You won Ludo!' : `${p.name} (${p.token}) won Ludo`, 'Ludo · 4 players waiting in this hall', me ? 150 : 30, me), 800);
            }
            setTimeout(() => dice === 6 ? turnStart() : next(), 600);
        }
        function next() { turn = (turn + 1) % 4; turnStart(); }
        function turnStart() {
            if (over) return; draw();
            const p = players[turn]; $('roll').disabled = p.bot;
            $('lmsg').textContent = p.bot ? `${p.name} is rolling…` : 'Your turn — roll!';
            if (p.bot) setTimeout(rollFor, 700);
        }
        $('roll').onclick = () => { if (!over && turn === 0 && !waiting) { $('roll').disabled = true; rollFor(); } };
        cleanup = () => { over = true; };
        turnStart();
    }
})();
