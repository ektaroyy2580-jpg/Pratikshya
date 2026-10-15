/* Café hub — tabs, photo gallery, live side cart, live order status, mini map. Progressive enhancement:
   without JS every section is visible and every form still posts. */
(() => {
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
    let mapDone = false; // declared first: the initial tab may be "visit"
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const rs = p => { const w = Math.floor(Math.abs(p) / 100).toLocaleString('en-IN'), f = Math.abs(p) % 100; return 'Rs ' + w + (f ? '.' + String(f).padStart(2, '0') : ''); };

    // ---------- images already cached before the inline onload ran ----------
    $$('img.cf-img, img.fa-photo').forEach(img => { if (img.complete && img.naturalWidth) img.classList.add('is-in'); });

    // ---------- tabs (hash deep links: #menu #bill #order #visit) ----------
    const nav = $('.cf-tabs');
    const panels = $$('.cf-panel');
    const ids = panels.map(p => p.id);
    function show(id, focus = false, scroll = false) {
        if (!ids.includes(id)) return false;
        panels.forEach(p => { const on = p.id === id; p.hidden = !on; p.classList.toggle('is-on', on); });
        $$('[data-tab]', nav).forEach(a => { const on = a.dataset.tab === id; a.setAttribute('aria-selected', on ? 'true' : 'false'); a.tabIndex = on ? 0 : -1; });
        if (id === 'visit') drawMap();
        if (focus) $(`[data-tab="${id}"]`, nav)?.focus();
        if (scroll) $('.cf-tabs-wrap')?.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
        return true;
    }
    if (nav) {
        const fromHash = () => (location.hash || '').slice(1).split('-')[0];
        const initial = fromHash();
        // #cat-12 (menu section) → menu tab
        if (!show(initial) && !(initial === 'cat' && show('menu'))) show(nav.dataset.default || 'menu');
        nav.addEventListener('click', e => {
            const a = e.target.closest('[data-tab]'); if (!a) return;
            e.preventDefault(); show(a.dataset.tab); history.replaceState(null, '', '#' + a.dataset.tab);
        });
        nav.addEventListener('keydown', e => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(e.key)) return;
            const tabs = $$('[data-tab]', nav), cur = tabs.findIndex(t => t.getAttribute('aria-selected') === 'true');
            let n = e.key === 'Home' ? 0 : e.key === 'End' ? tabs.length - 1 : cur + (e.key === 'ArrowRight' ? 1 : -1);
            n = (n + tabs.length) % tabs.length; e.preventDefault(); show(tabs[n].dataset.tab, true); history.replaceState(null, '', '#' + tabs[n].dataset.tab);
        });
        document.addEventListener('click', e => {
            const a = e.target.closest('[data-tab-link]'); if (!a) return;
            e.preventDefault(); show(a.dataset.tabLink, false, true); history.replaceState(null, '', '#' + a.dataset.tabLink);
        });
        window.addEventListener('hashchange', () => { const h = fromHash(); if (h === 'cat') show('menu'); else show(h); });
    }

    // ---------- gallery: scroll-snap (native swipe) + arrows, dots, thumbnails, keyboard ----------
    $$('[data-gallery]').forEach(g => {
        const track = $('.cf-track', g), slides = $$('.cf-slide', g), dots = $$('.cf-dots button', g);
        const thumbs = $$('.cf-thumbs [data-thumb]', g.parentElement);
        const prev = $('.cf-nav.prev', g), next = $('.cf-nav.next', g);
        let idx = 0;
        const go = i => { i = Math.max(0, Math.min(slides.length - 1, i)); track.scrollTo({ left: slides[i].offsetLeft, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); };
        const sync = () => {
            const i = Math.round(track.scrollLeft / Math.max(1, track.clientWidth));
            if (i === idx && prev) { prev.disabled = i === 0; next.disabled = i === slides.length - 1; return; }
            idx = i;
            dots.forEach((d, k) => { d.classList.toggle('on', k === i); d.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
            thumbs.forEach((t, k) => t.classList.toggle('on', k === i));
            if (prev) { prev.disabled = i === 0; next.disabled = i === slides.length - 1; }
        };
        let raf = 0; track.addEventListener('scroll', () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(sync); }, { passive: true });
        prev?.addEventListener('click', () => go(idx - 1));
        next?.addEventListener('click', () => go(idx + 1));
        dots.forEach((d, k) => d.addEventListener('click', () => go(k)));
        thumbs.forEach(t => t.addEventListener('click', () => go(+t.dataset.thumb)));
        track.addEventListener('keydown', e => { if (e.key === 'ArrowRight') { e.preventDefault(); go(idx + 1); } if (e.key === 'ArrowLeft') { e.preventDefault(); go(idx - 1); } });
        sync();
    });

    // ---------- live side cart (fed by preorder.js) ----------
    const side = $('#cfCart');
    const menu = (() => { try { return JSON.parse($('#cfMenuData')?.textContent || '{}'); } catch (e) { return {}; } })();
    document.addEventListener('preorder:cart', e => {
        if (!side) return;
        const d = e.detail || {}, here = String(d.restaurant_id) === side.dataset.restaurant && d.count > 0;
        $('.js-cf-count', side).textContent = (here ? d.count : 0) + ' item' + ((here ? d.count : 0) === 1 ? '' : 's');
        $('.js-cf-empty', side).hidden = here; $('.js-cf-filled', side).hidden = !here;
        if (!here) return;
        $('.js-cf-lines', side).innerHTML = Object.entries(d.items || {}).map(([id, q]) => {
            const m = menu[id]; if (!m) return '';
            return `<div class="line"><span class="text-truncate">${q} × ${esc(m.name)}</span><span class="fw-semibold">${rs(m.paisa * q)}</span></div>`;
        }).join('');
        $('.js-cart-subtotal-side', side).textContent = d.subtotal_label;
        $('.js-cf-total', side).textContent = d.total_label;
        const timing = $('.js-cf-timing', side); if (timing) timing.hidden = true;
        const stale = $('.js-cf-stale', side); if (stale) stale.hidden = false;
    });

    // ---------- live order status ----------
    $$('[data-order-poll]').forEach(card => {
        let last = card.dataset.status, fails = 0;
        const steps = $$('.po-track .st', card);
        const tick = async () => {
            if (document.hidden) return;
            try {
                const r = await fetch(card.dataset.orderPoll, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) throw new Error(r.status);
                const { data } = await r.json(); fails = 0;
                $('.js-o-label', card).textContent = data.status_label;
                $('.js-o-eta', card).textContent = data.status === 'ready' ? 'Ready now' : (data.minutes_to_ready !== null ? `~${data.minutes_to_ready} min` : (data.estimated_ready_label || '—'));
                steps.forEach((el, i) => { el.classList.toggle('done', data.step !== null && (i < data.step || data.status === 'collected')); el.classList.toggle('now', data.step !== null && i === data.step && data.status !== 'collected'); });
                const code = $('.js-o-code', card); code.hidden = !data.pickup_code; if (data.pickup_code) $('.js-o-code-v', card).textContent = data.pickup_code;
                if (data.status !== last) { last = data.status; const l = $('#poLive'); if (l) l.textContent = 'Order update: ' + data.status_label; }
                if (!data.is_open) clearInterval(timer);
            } catch (e) { if (++fails > 3) clearInterval(timer); }
        };
        const timer = setInterval(tick, 8000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    });

    // ---------- mini map (drawn when "Getting there" is first shown) ----------
    function drawMap() {
        if (mapDone || !window.L || !$('#cfMap')) return;
        mapDone = true;
        const d = JSON.parse($('#cfMapData').textContent);
        const map = L.map('cfMap', { scrollWheelZoom: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        L.marker(d.from, { icon: L.divIcon({ className: '', html: '<div class="ex-home"><i class="bi bi-hourglass-split"></i>Queue</div>', iconSize: [80, 30], iconAnchor: [40, 15] }) }).addTo(map);
        L.marker(d.to, { icon: L.divIcon({ className: '', html: `<div class="ex-pin sel" style="--c:${d.color}"><i class="bi ${d.icon}"></i></div>`, iconSize: [32, 32], iconAnchor: [16, 38] }) }).addTo(map);
        const pts = d.geometry || [d.from, d.to];
        L.polyline(pts, d.geometry ? { color: '#2f5bea', weight: 5, opacity: .85 } : { color: '#2f5bea', weight: 3, dashArray: '2 10', lineCap: 'round' })
            .addTo(map).bindTooltip(d.label, { permanent: true, direction: 'center', className: 'ex-line-label' });
        map.fitBounds(L.latLngBounds(pts).pad(0.35), { maxZoom: 17.5 });
        setTimeout(() => map.invalidateSize(), 60);
    }
    if (!nav) drawMap();
})();
