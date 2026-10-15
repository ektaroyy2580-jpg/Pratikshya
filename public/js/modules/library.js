// E-Library: live search suggestions + favourite toggle (progressive enhancement; forms work without JS).
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---------- Live search ----------
    document.querySelectorAll('form[data-suggest-url]').forEach(form => {
        const input = form.querySelector('input[name="q"]');
        const box = form.querySelector('.lib-suggest');
        if (!input || !box) return;
        let timer, ctrl, active = -1;
        const close = () => { box.classList.remove('open'); input.setAttribute('aria-expanded', 'false'); active = -1; };
        const items = () => [...box.querySelectorAll('a')];
        const mark = () => items().forEach((a, i) => { a.classList.toggle('active', i === active); a.setAttribute('aria-selected', i === active); });

        input.addEventListener('input', () => {
            clearTimeout(timer);
            const q = input.value.trim();
            if (q.length < 2) { close(); return; }
            timer = setTimeout(async () => {
                ctrl?.abort(); ctrl = new AbortController();
                box.innerHTML = '<div class="s-empty"><span class="skeleton d-block" style="height:14px;width:70%"></span></div>';
                box.classList.add('open'); input.setAttribute('aria-expanded', 'true');
                try {
                    const res = await fetch(form.dataset.suggestUrl + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, signal: ctrl.signal });
                    if (!res.ok) throw new Error(res.status);
                    const json = await res.json();
                    active = -1;
                    box.innerHTML = json.data.length
                        ? json.data.map(b => `<a href="${esc(b.url)}" role="option"><i class="bi bi-book text-primary"></i><span class="min-w-0"><span class="s-t d-block text-truncate">${esc(b.title)}</span><span class="s-m">${esc(b.author)} · ${esc(b.category)} · ${esc(b.minutes)} min</span></span></a>`).join('')
                            + `<a href="${esc(form.action)}?q=${encodeURIComponent(q)}" role="option"><i class="bi bi-search"></i><span class="s-t">See all results for “${esc(q)}”</span></a>`
                        : `<div class="s-empty">No matches for “${esc(q)}”. Try an author or a genre like “mystery”.</div>`;
                } catch (e) {
                    if (e.name !== 'AbortError') box.innerHTML = '<div class="s-empty">Search is unavailable right now — press Enter to search.</div>';
                }
            }, 220);
        });
        input.addEventListener('keydown', e => {
            const list = items();
            if (!box.classList.contains('open') || !list.length) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % list.length; mark(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + list.length) % list.length; mark(); }
            else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); list[active].click(); }
            else if (e.key === 'Escape') close();
        });
        document.addEventListener('click', e => { if (!form.contains(e.target)) close(); });
    });

    // ---------- Favourite toggle ----------
    document.querySelectorAll('form[data-fav]').forEach(form => {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            const btn = form.querySelector('button');
            btn.disabled = true;
            try {
                const res = await fetch(form.action, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
                if (!res.ok) throw new Error(res.status);
                const { data } = await res.json();
                btn.setAttribute('aria-pressed', data.favorite ? 'true' : 'false');
                btn.querySelector('i').className = 'bi ' + (data.favorite ? 'bi-heart-fill' : 'bi-heart');
                btn.querySelector('span').textContent = data.favorite ? 'Favourited' : 'Favourite';
                btn.classList.remove('pop'); void btn.offsetWidth; btn.classList.add('pop');
            } catch (err) {
                form.submit();
            } finally { btn.disabled = false; }
        });
    });
})();
