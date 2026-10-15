/* Explore module — map + list + waiting-time-aware recommendations.
 * - Location is only requested after the user explicitly agrees; manual pin otherwise.
 * - The chosen position stays in this page (sent only as query params to estimate times; never stored).
 * - Without a routing provider we draw a dashed DIRECT LINE, labelled "direct line, not a route".
 */
(() => {
    'use strict';
    const dataEl = document.getElementById('exploreData');
    if (!dataEl || !window.L) return;
    const D = JSON.parse(dataEl.textContent);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => [...r.querySelectorAll(s)];
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const NON_REC = ['hospital', 'transport'];
    const places = new Map(D.places.map(p => [p.id, p]));
    const dismissed = new Set(D.dismissed || []);
    let origin = null, originSource = null, cat = 'all', selected = null, picking = false;
    let meMarker = null, line = null, lineReq = 0, recReq = 0;

    // ---------------- Map ----------------
    const map = L.map('exMap', { zoomControl: false, scrollWheelZoom: true, zoomSnap: 0.5 }).setView([D.location.lat, D.location.lng], 16);
    L.control.zoom({ position: 'topright' }).addTo(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);

    const homeLabel = D.ticketId ? 'Your queue' : esc(D.location.name.length > 22 ? D.location.name.slice(0, 21) + '…' : D.location.name);
    const home = L.marker([D.location.lat, D.location.lng], {
        icon: L.divIcon({ className: '', html: `<div class="ex-home"><i class="bi bi-hourglass-split"></i>${homeLabel}</div>`, iconSize: [130, 30], iconAnchor: [65, 15] }),
        zIndexOffset: 1000, keyboard: false, title: D.location.name,
    }).addTo(map);

    const layer = L.layerGroup().addTo(map);
    const markers = new Map();
    const pinIcon = p => L.divIcon({
        className: '',
        html: `<div class="ex-pin ${p.fits && D.remaining !== null && !NON_REC.includes(p.category) ? 'fits' : ''} ${selected === p.id ? 'sel' : ''}" style="--c:${esc(p.color)}"><i class="bi ${esc(p.icon)}"></i></div>`,
        iconSize: [32, 32], iconAnchor: [16, 38],
    });
    D.places.forEach(p => {
        const m = L.marker([p.lat, p.lng], { icon: pinIcon(p), title: p.name, alt: p.name, riseOnHover: true });
        m.on('click', () => openPlace(p.id));
        markers.set(p.id, m);
        m.addTo(layer);
    });
    const fitAll = () => {
        const pts = [[D.location.lat, D.location.lng], ...[...markers.entries()].filter(([id]) => visible(places.get(id))).map(([, m]) => m.getLatLng())];
        if (origin) pts.push(origin);
        if (pts.length > 1) map.fitBounds(L.latLngBounds(pts).pad(0.15), { maxZoom: 17, animate: true });
    };
    fitAll();
    // Map container changes size on load / orientation changes.
    setTimeout(() => { map.invalidateSize(); if (!selected) fitAll(); }, 150);
    window.addEventListener('resize', () => map.invalidateSize());

    // ---------------- Helpers ----------------
    const toRad = d => d * Math.PI / 180;
    const haversine = (a, b) => {
        const dLat = toRad(b[0] - a[0]), dLng = toRad(b[1] - a[1]);
        const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a[0])) * Math.cos(toRad(b[0])) * Math.sin(dLng / 2) ** 2;
        return 6371000 * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    };
    const fmtM = m => m >= 1000 ? (m / 1000).toFixed(1) + ' km' : Math.round(m).toLocaleString() + ' m';
    const isMobile = () => window.matchMedia('(max-width: 991.98px)').matches;
    function visible(p) { return cat === 'all' || p.category === cat; }
    const url = (key, id) => D.urls[key].replace('__ID__', encodeURIComponent(id));

    let toastTimer = null;
    function toast(msg, action) {
        $('.ex-toast')?.remove();
        clearTimeout(toastTimer);
        const t = document.createElement('div');
        t.className = 'ex-toast'; t.setAttribute('role', 'status');
        t.innerHTML = `<span>${esc(msg)}</span>`;
        if (action) {
            const b = document.createElement('button'); b.type = 'button'; b.textContent = action.label;
            b.onclick = () => { t.remove(); action.run(); };
            t.appendChild(b);
        }
        document.body.appendChild(t);
        toastTimer = setTimeout(() => t.remove(), 6000);
    }

    async function api(u, opts = {}) {
        const res = await fetch(u, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', ...opts });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(body.message || ('Request failed (' + res.status + ')'));
        return body;
    }

    function queryParams() {
        const q = new URLSearchParams({ location: D.location.id });
        if (D.ticketId) q.set('ticket', D.ticketId);
        if (D.manualMinutes !== null && D.manualMinutes !== undefined) q.set('minutes', D.manualMinutes);
        if (origin) { q.set('lat', origin[0].toFixed(6)); q.set('lng', origin[1].toFixed(6)); }
        return q;
    }

    // Café photo thumbnail (fades in; the category icon stays underneath as the fallback).
    function icoHtml(it) {
        return it.image
            ? `<span class="ico photo" aria-hidden="true"><i class="bi ${esc(it.icon)}"></i><img src="${esc(it.image)}" alt="" loading="lazy" decoding="async" onload="this.classList.add('is-in')" onerror="this.remove()"></span>`
            : `<span class="ico" aria-hidden="true"><i class="bi ${esc(it.icon)}"></i></span>`;
    }

    // ---------------- Recommendations ----------------
    function recCard(it, k) {
        const rem = Math.max(1, D.remaining || 1), pw = m => Math.max(0, Math.min(100, m / rem * 100)).toFixed(1);
        return `<article class="ex-rec fade-up" style="--c:${esc(it.color)};animation-delay:${k * 0.05}s" data-place="${it.id}" tabindex="0" role="button" aria-label="Show ${esc(it.name)} on the map">
            ${icoHtml(it)}
            <div class="flex-grow-1 min-w-0 pe-4">
                <div class="nm">${esc(it.name)}</div>
                <div class="meta"><span><i class="bi bi-person-walking"></i> ${it.walk_there} min</span><span><i class="bi bi-hourglass"></i> ${it.visit} min visit</span><span><i class="bi bi-star-fill" style="color:#f5b301"></i> ${Number(it.rating).toFixed(1)}</span><span class="text-success"><i class="bi bi-check2-circle"></i> ${it.spare} min spare</span></div>
                <div class="ex-timebar" aria-hidden="true"><span class="w" style="width:${pw(it.walk_there)}%"></span><span class="v" style="width:${pw(it.visit)}%"></span><span class="w" style="width:${pw(it.walk_back)}%"></span><span class="b" style="width:${pw(D.buffer)}%"></span></div>
                <div class="why">${esc(it.explanation)}</div>
                ${it.is_cafe ? `<a href="${esc(it.url)}" class="ex-open mt-2">Open café <i class="bi bi-arrow-right"></i></a>` : ''}
            </div>
            <button type="button" class="x" data-dismiss-place="${it.id}" aria-label="Dismiss ${esc(it.name)}" title="Not interested"><i class="bi bi-x-lg"></i></button>
        </article>`;
    }

    function renderRecs(rec) {
        const box = $('#recList');
        if (rec.items && rec.items.length) {
            box.innerHTML = rec.items.map(recCard).join('');
            $('#recNote').textContent = rec.message || '';
        } else {
            box.innerHTML = `<div class="ex-empty fade-up"><i class="bi ${rec.enabled ? 'bi-hourglass-split' : 'bi-toggle-off'} d-block fs-4 mb-1"></i>${esc(rec.message)}</div>`;
            $('#recNote').textContent = '';
        }
    }

    async function refreshRecs() {
        const my = ++recReq;
        const box = $('#recList');
        box.style.opacity = '.55'; box.setAttribute('aria-busy', 'true');
        try {
            const res = await api(D.urls.recs + '?' + queryParams());
            if (my !== recReq) return;
            const d = res.data;
            D.remaining = d.remaining;
            renderRecs({ items: d.items, enabled: d.enabled, message: res.message });
            d.places.forEach(u => { const p = places.get(u.id); if (p) Object.assign(p, u); });
            updatePlaceRows();
            if (selected) fillDrawer(places.get(selected));
        } catch (e) {
            if (my === recReq) toast('Could not update suggestions: ' + e.message);
        } finally {
            if (my === recReq) { box.style.opacity = ''; box.removeAttribute('aria-busy'); }
        }
    }

    function updatePlaceRows() {
        $$('#placeList .ex-place').forEach(row => {
            const p = places.get(+row.dataset.place); if (!p) return;
            const dist = $('[data-f="dist"]', row); if (dist) dist.textContent = `${p.walk_there} min walk · ${Math.round(p.distance_m).toLocaleString()} m`;
            const fit = $('[data-f="fit"]', row);
            if (fit) { fit.className = 'ex-fit ' + (p.fits ? 'yes' : 'no'); fit.textContent = p.fits ? 'Fits' : 'Too long'; }
            markers.get(p.id)?.setIcon(pinIcon(p));
        });
    }

    // Recommendation interactions (delegated)
    $('#recList').addEventListener('click', e => {
        const x = e.target.closest('[data-dismiss-place]');
        if (x) { e.stopPropagation(); dismiss(+x.dataset.dismissPlace, x.closest('.ex-rec')); return; }
        if (e.target.closest('a')) return; // "Open café" link navigates
        const card = e.target.closest('.ex-rec'); if (card) openPlace(+card.dataset.place);
    });
    $('#recList').addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('ex-rec')) { e.preventDefault(); openPlace(+e.target.dataset.place); }
    });

    async function dismiss(id, card) {
        const p = places.get(id);
        if (card) card.classList.add('leaving');
        try {
            const res = await api(url('dismiss', id), { method: 'POST' });
            dismissed.add(id);
            if (card) setTimeout(() => card.remove(), 280);
            toast(res.message || 'Hidden from suggestions.', { label: 'Undo', run: () => undo(res.data.dismissal_id, id) });
            setTimeout(refreshRecs, 300);
            if (selected === id) fillDrawer(p);
        } catch (e) {
            if (card) card.classList.remove('leaving');
            toast('Could not dismiss: ' + e.message);
        }
    }
    async function undo(dismissalId, placeId) {
        try {
            await api(url('undismiss', dismissalId), { method: 'DELETE' });
            dismissed.delete(placeId);
            toast('Suggestion restored.');
            refreshRecs();
        } catch (e) { toast('Could not restore: ' + e.message); }
    }

    // ---------------- Category chips + list ----------------
    $('#chips')?.addEventListener('click', e => {
        const b = e.target.closest('.ex-chip'); if (!b) return;
        cat = b.dataset.cat;
        $$('#chips .ex-chip').forEach(c => { const on = c === b; c.classList.toggle('on', on); c.setAttribute('aria-pressed', on); });
        let n = 0;
        $$('#placeList .ex-place').forEach(row => { const show = cat === 'all' || row.dataset.cat === cat; row.classList.toggle('d-none', !show); if (show) n++; });
        $('#placeCount').textContent = n + (n === 1 ? ' place' : ' places');
        markers.forEach((m, id) => { const show = visible(places.get(id)); if (show && !layer.hasLayer(m)) m.addTo(layer); if (!show && layer.hasLayer(m)) layer.removeLayer(m); });
        fitAll();
    });
    $('#placeList').addEventListener('click', e => { if (e.target.closest('a')) return; const r = e.target.closest('.ex-place'); if (r) openPlace(+r.dataset.place); });
    $('#placeList').addEventListener('keydown', e => {
        if ((e.key === 'Enter' || e.key === ' ') && e.target.classList.contains('ex-place')) { e.preventDefault(); openPlace(+e.target.dataset.place); }
    });

    // ---------------- Drawer ----------------
    const drawerEl = $('#placeDrawer');
    let drawer = null;
    function getDrawer() {
        const want = isMobile() ? 'offcanvas-bottom' : 'offcanvas-end';
        if (!drawerEl.classList.contains(want)) {
            drawerEl.classList.remove('offcanvas-bottom', 'offcanvas-end');
            drawerEl.classList.add(want);
        }
        drawer = drawer || bootstrap.Offcanvas.getOrCreateInstance(drawerEl, { scroll: true, backdrop: false });
        return drawer;
    }
    drawerEl.addEventListener('hidden.bs.offcanvas', () => {
        const prev = selected; selected = null;
        if (prev) { markers.get(prev)?.setIcon(pinIcon(places.get(prev))); }
        $$('.ex-place.sel').forEach(r => r.classList.remove('sel'));
        if (line) { map.removeLayer(line); line = null; }
    });

    function fillDrawer(p) {
        if (!p) return;
        $('#drawerTitle').textContent = p.name;
        const c = $('#dCat'); c.innerHTML = `<i class="bi ${esc(p.icon)}"></i>${esc(p.category_label)}${p.subcategory ? ' · ' + esc(p.subcategory) : ''}`;
        const rec = !NON_REC.includes(p.category);
        const isDismissed = dismissed.has(p.id);
        let fit = '';
        if (rec && D.remaining !== null && D.remaining !== undefined) {
            fit = `<div class="alert ${p.fits ? 'alert-success' : 'alert-warning'} small py-2 px-3 mb-3"><i class="bi ${p.fits ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} me-1"></i>${esc(p.explanation)}</div>`;
        } else if (rec) {
            fit = `<div class="small pk-muted mb-3">${esc(p.explanation)}</div>`;
        }
        const btns = (p.is_cafe ? [
            // Cafés: everything (menu, pre-order, split a bill, your order) lives inside the café page.
            `<a class="btn btn-pk btn-sm" href="${esc(p.url)}"><i class="bi bi-shop me-1"></i>Open café</a>`,
            p.preorder_url ? `<a class="btn btn-ghost btn-sm" href="${esc(p.preorder_url)}"><i class="bi bi-bag-check me-1"></i>Pre-order</a>` : '',
            `<button type="button" class="btn btn-ghost btn-sm" data-act="line"><i class="bi bi-signpost me-1"></i>Show on map</button>`,
            p.ride_url ? `<a class="btn btn-ghost btn-sm" href="${esc(p.ride_url)}"><i class="bi bi-car-front me-1"></i>Ride there</a>` : '',
        ] : [
            `<button type="button" class="btn btn-pk btn-sm" data-act="line"><i class="bi bi-signpost me-1"></i>Show on map</button>`,
            `<a class="btn btn-ghost btn-sm" href="${esc(p.url)}"><i class="bi bi-info-circle me-1"></i>Details</a>`,
            p.ride_url ? `<a class="btn btn-ghost btn-sm" href="${esc(p.ride_url)}"><i class="bi bi-car-front me-1"></i>Ride there</a>` : '',
            p.preorder_url ? `<a class="btn btn-ghost btn-sm" href="${esc(p.preorder_url)}"><i class="bi bi-bag-check me-1"></i>Pre-order</a>` : '',
        ]).concat([
            rec && !isDismissed ? `<button type="button" class="btn btn-link btn-sm text-decoration-none text-danger" data-act="dismiss"><i class="bi bi-eye-slash me-1"></i>Not interested</button>` : '',
        ]).join('');
        const photo = p.image ? `<a href="${esc(p.url)}" class="ex-drawer-photo mb-3" aria-label="Open café: ${esc(p.name)}"><img src="${esc(p.image)}" alt="" decoding="async" onload="this.classList.add('is-in')" onerror="this.parentNode.remove()"><span>Representative photo</span></a>` : '';
        $('#drawerBody').innerHTML = `${photo}
            <div class="row g-2 mb-3 text-center">
                <div class="col-4"><div class="ex-stat"><div class="l">Walk there</div><div class="v">${p.walk_there}<small class="fs-6 pk-muted"> min</small></div></div></div>
                <div class="col-4"><div class="ex-stat"><div class="l">${rec ? 'Visit' : 'Stop'}</div><div class="v">${p.visit}<small class="fs-6 pk-muted"> min</small></div></div></div>
                <div class="col-4"><div class="ex-stat"><div class="l">Back</div><div class="v">${p.walk_back}<small class="fs-6 pk-muted"> min</small></div></div></div>
            </div>
            ${fit}
            <div class="d-flex flex-wrap gap-2 mb-3">${btns}</div>
            <div class="ex-kv"><i class="bi bi-rulers"></i><div>≈ ${fmtM(p.distance_m)} on foot <span class="pk-muted">(${esc(p.estimate_label)})</span></div></div>
            <div class="ex-kv"><i class="bi bi-clock"></i><div>${esc(p.hours_text)}${p.hours_verified ? ' <span class="badge text-bg-success ms-1">Verified</span>' : ''}</div></div>
            ${p.tip ? `<div class="ex-kv"><i class="bi bi-lightbulb"></i><div>${esc(p.tip)}</div></div>` : ''}
            ${p.accessibility ? `<div class="ex-kv"><i class="bi bi-universal-access"></i><div>${esc(p.accessibility)}</div></div>` : ''}
            ${p.address ? `<div class="ex-kv"><i class="bi bi-geo-alt"></i><div>${esc(p.address)}</div></div>` : ''}
            <div class="ex-kv"><i class="bi bi-star"></i><div>${Number(p.rating).toFixed(1)} rating <span class="pk-muted">(demo)</span>${p.is_partner ? ' · <span class="chip py-0">Participating business</span>' : ''}${p.sponsored ? ' · <span class="ex-badge">Sponsored</span>' : ''}</div></div>
            ${isDismissed ? '<div class="small pk-muted mt-2"><i class="bi bi-eye-slash me-1"></i>Hidden from your suggestions. Manage in Explore preferences.</div>' : ''}`;
        $('[data-act="line"]', drawerEl)?.addEventListener('click', () => drawLine(p, true));
        $('[data-act="dismiss"]', drawerEl)?.addEventListener('click', () => dismiss(p.id, $(`#recList .ex-rec[data-place="${p.id}"]`)));
    }

    function openPlace(id) {
        const p = places.get(id); if (!p) return;
        const prev = selected; selected = id;
        if (prev && prev !== id) markers.get(prev)?.setIcon(pinIcon(places.get(prev)));
        markers.get(id)?.setIcon(pinIcon(p));
        if (!visible(p)) $('#chips .ex-chip[data-cat="all"]')?.click();
        $$('.ex-place').forEach(r => r.classList.toggle('sel', +r.dataset.place === id));
        fillDrawer(p);
        getDrawer().show();
        if (isMobile()) window.scrollTo({ top: 0, behavior: 'smooth' });
        drawLine(p, true);
    }

    // ---------------- Direct line / route ----------------
    async function drawLine(p, fly) {
        const from = origin || [D.location.lat, D.location.lng];
        const my = ++lineReq;
        if (line) { map.removeLayer(line); line = null; }
        const pad = isMobile() ? { paddingTopLeft: [30, 40], paddingBottomRight: [30, 120] } : { paddingTopLeft: [40, 40], paddingBottomRight: [460, 40] };
        if (fly) map.flyToBounds(L.latLngBounds([from, [p.lat, p.lng]]), { ...pad, maxZoom: 17.5, duration: 0.6 });
        let est = null;
        try {
            const q = new URLSearchParams(); if (origin) { q.set('lat', origin[0].toFixed(6)); q.set('lng', origin[1].toFixed(6)); }
            est = (await api(url('travel', p.id) + (origin ? '?' + q : ''))).data;
        } catch (e) { /* fall back to a client-side direct line */ }
        if (my !== lineReq) return;
        const routed = est && Array.isArray(est.geometry) && est.geometry.length > 1;
        const pts = routed ? est.geometry : [from, [p.lat, p.lng]];
        line = L.polyline(pts, routed
            ? { color: '#2f5bea', weight: 5, opacity: 0.85 }
            : { color: '#2f5bea', weight: 3, opacity: 0.9, dashArray: '2 10', lineCap: 'round' }).addTo(map);
        const label = routed ? `${est.walk_minutes} min · walking route (approximate)` : `${est ? est.walk_minutes : p.walk_there} min · direct line, not a route`;
        line.bindTooltip(label, { permanent: true, direction: 'center', className: 'ex-line-label' }).openTooltip();
    }

    // ---------------- Where am I: consent → geolocation → manual fallback ----------------
    const note = $('#originNote');
    function setOrigin(latlng, source) {
        origin = [latlng.lat ?? latlng[0], latlng.lng ?? latlng[1]];
        originSource = source;
        if (!meMarker) {
            meMarker = L.marker(origin, { icon: L.divIcon({ className: '', html: '<div class="ex-me"></div>', iconSize: [20, 20], iconAnchor: [10, 10] }), draggable: true, zIndexOffset: 1100, title: 'You', keyboard: true })
                .addTo(map)
                .on('dragend', e => setOrigin(e.target.getLatLng(), 'manual'));
        } else {
            meMarker.setLatLng(origin);
        }
        const far = haversine(origin, [D.location.lat, D.location.lng]);
        note.innerHTML = `<i class="bi ${source === 'gps' ? 'bi-crosshair' : 'bi-pin-map'} me-1"></i>From ${source === 'gps' ? 'your location' : 'your pin'} (${fmtM(far)} from ${esc(D.location.name)}), then back to the queue. Drag the blue dot to adjust.`
            + (far > 3000 ? `<div class="text-danger mt-1"><i class="bi bi-exclamation-triangle me-1"></i>That's far from the queue — suggestions include the walk back, so few places will fit.</div>` : '');
        $('#btnResetOrigin').classList.remove('d-none');
        refreshRecs();
        if (selected) drawLine(places.get(selected), false);
    }
    function resetOrigin() {
        origin = null; originSource = null;
        if (meMarker) { map.removeLayer(meMarker); meMarker = null; }
        note.innerHTML = `<i class="bi bi-geo me-1"></i>Times are from ${esc(D.location.name)} and back.`;
        $('#btnResetOrigin').classList.add('d-none');
        refreshRecs();
        if (selected) drawLine(places.get(selected), false);
    }
    function startPicking(reason) {
        picking = true;
        $('#exShell').classList.add('ex-picking');
        const hint = $('#pickHint'); hint.classList.add('show');
        if (reason) note.innerHTML = `<i class="bi bi-info-circle me-1"></i>${esc(reason)}`;
        if (isMobile()) window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function stopPicking() { picking = false; $('#exShell').classList.remove('ex-picking'); $('#pickHint').classList.remove('show'); }
    map.on('click', e => { if (picking) { stopPicking(); setOrigin(e.latlng, 'manual'); } });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && picking) stopPicking(); });

    const consent = $('#consentBox');
    $('#btnLocate').addEventListener('click', () => { consent.classList.remove('d-none'); $('#consentYes').focus(); });
    $('#consentNo').addEventListener('click', () => consent.classList.add('d-none'));
    $('#consentPick').addEventListener('click', () => { consent.classList.add('d-none'); startPicking(); });
    $('#btnPick').addEventListener('click', () => startPicking());
    $('#btnResetOrigin').addEventListener('click', resetOrigin);
    $('#consentYes').addEventListener('click', () => {
        consent.classList.add('d-none');
        if (!('geolocation' in navigator) || !window.isSecureContext) {
            startPicking('Location isn’t available in this browser here. Tap the map to set where you are.');
            return;
        }
        const btn = $('#btnLocate');
        btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Locating…';
        navigator.geolocation.getCurrentPosition(
            pos => {
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Use my location';
                setOrigin({ lat: pos.coords.latitude, lng: pos.coords.longitude }, 'gps');
                map.flyTo(origin, Math.max(map.getZoom(), 16), { duration: 0.6 });
            },
            err => {
                btn.disabled = false; btn.innerHTML = '<i class="bi bi-crosshair me-1"></i>Use my location';
                const why = err.code === 1 ? 'Location permission was denied.' : 'We couldn’t get your location.';
                startPicking(why + ' Tap the map to set where you are instead.');
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 }
        );
    });
})();
