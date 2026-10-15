/* Sudoku: the solution never reaches the browser; only rule conflicts are shown locally.
   The finished grid is verified on the server against the puzzle it generated. */
(function () {
    'use strict';
    const $ = id => document.getElementById(id);
    let api, puzzle, grid, sel, cells, keyHandler, submitting;

    const row = i => Math.floor(i / 9), col = i => i % 9, box = i => Math.floor(row(i) / 3) * 3 + Math.floor(col(i) / 3);
    const peers = (a, b) => a !== b && (row(a) === row(b) || col(a) === col(b) || box(a) === box(b));

    function conflicts() {
        const bad = new Set();
        for (let a = 0; a < 81; a++) {
            if (!grid[a]) continue;
            for (let b = a + 1; b < 81; b++) if (grid[b] === grid[a] && peers(a, b)) { bad.add(a); bad.add(b); }
        }
        return bad;
    }

    function paint(changed, noAuto) {
        const bad = conflicts();
        const v = sel != null ? grid[sel] : 0;
        cells.forEach((c, i) => {
            c.textContent = grid[i] || '';
            c.classList.toggle('sel', i === sel);
            c.classList.toggle('peer', sel != null && peers(i, sel));
            c.classList.toggle('same', !!v && i !== sel && grid[i] === v);
            c.classList.toggle('bad', bad.has(i) && !puzzle[i]);
            c.setAttribute('aria-label', `Row ${row(i) + 1}, column ${col(i) + 1}, ${grid[i] || 'empty'}${puzzle[i] ? ', given' : ''}`);
        });
        if (changed != null) { cells[changed].classList.remove('pop'); void cells[changed].offsetWidth; cells[changed].classList.add('pop'); }
        const left = grid.filter(x => !x).length;
        $('sdLeft').textContent = left;
        $('sdSubmit').disabled = left > 0 || submitting;
        const counts = Array(10).fill(0); grid.forEach(x => counts[x]++);
        $('sdPad').querySelectorAll('[data-n]').forEach(k => {
            const n = +k.dataset.n; if (!n) return;
            k.classList.toggle('full', counts[n] >= 9);
            k.querySelector('.left').textContent = Math.max(0, 9 - counts[n]);
        });
        const filledCount = 81 - left;
        api.setScore(filledCount - puzzle.filter(Boolean).length);
        if (left === 0 && bad.size === 0 && !submitting && !noAuto) submit();
    }

    function setVal(n) {
        if (sel == null || puzzle[sel] || submitting) return;
        grid[sel] = grid[sel] === n ? 0 : n;
        paint(sel);
    }

    async function submit() {
        if (submitting) return;
        submitting = true; $('sdSubmit').disabled = true;
        const r = await api.finish({ grid });
        if (!r.ok) {
            submitting = false;
            const bad = conflicts();
            api.toast(bad.size ? `There are ${bad.size} conflicting cells.` : (r.message || 'Not solved yet.'), 'bad');
            paint(null, true);
        }
    }

    function build() {
        const g = $('sdGrid');
        g.innerHTML = '';
        cells = [];
        for (let i = 0; i < 81; i++) {
            const c = document.createElement('button');
            c.type = 'button';
            c.className = 'sd-cell' + (puzzle[i] ? ' given' : '') + (col(i) % 3 === 0 && col(i) ? ' c' + col(i) : '') + (row(i) % 3 === 0 && row(i) ? ' r' + row(i) : '');
            c.setAttribute('role', 'gridcell');
            c.addEventListener('click', () => { sel = i; paint(); });
            g.appendChild(c); cells.push(c);
        }
        $('sdPad').innerHTML = [1, 2, 3, 4, 5, 6, 7, 8, 9].map(n => `<button type="button" class="sd-key" data-n="${n}" aria-label="Enter ${n}">${n}<span class="left"></span></button>`).join('')
            + '<button type="button" class="sd-key erase" data-n="0" aria-label="Erase"><i class="bi bi-eraser"></i></button>';
        $('sdPad').querySelectorAll('.sd-key').forEach(k => k.addEventListener('click', () => { const n = +k.dataset.n; if (n) setVal(n); else if (sel != null && !puzzle[sel]) { grid[sel] = 0; paint(sel); } }));
        $('sdCheck').onclick = () => {
            const bad = conflicts(), left = grid.filter(x => !x).length;
            api.toast(bad.size ? `${bad.size} cells conflict. Look for the red ones.` : left ? `No conflicts so far. ${left} cells to go.` : 'Looks complete!', bad.size ? 'bad' : 'ok');
        };
        $('sdSubmit').onclick = submit;
    }

    window.ArcadeGames.sudoku = {
        begin(g, coreApi) {
            api = coreApi; puzzle = g.puzzle; grid = g.puzzle.slice(); submitting = false;
            sel = grid.indexOf(0);
            build(); paint();
            if (keyHandler) document.removeEventListener('keydown', keyHandler);
            keyHandler = e => {
                if ($('arcPlay').hidden || sel == null) return;
                if (/^[1-9]$/.test(e.key)) { setVal(+e.key); e.preventDefault(); }
                else if (e.key === 'Backspace' || e.key === 'Delete' || e.key === '0') { if (!puzzle[sel]) { grid[sel] = 0; paint(sel); } e.preventDefault(); }
                else if (e.key.startsWith('Arrow')) {
                    const d = { ArrowUp: -9, ArrowDown: 9, ArrowLeft: -1, ArrowRight: 1 }[e.key];
                    const n = sel + d; if (n >= 0 && n < 81 && !(Math.abs(d) === 1 && row(n) !== row(sel))) { sel = n; paint(); cells[sel].focus({ preventScroll: true }); }
                    e.preventDefault();
                }
            };
            document.addEventListener('keydown', keyHandler);
        },
        stop() { submitting = true; },
    };
})();
