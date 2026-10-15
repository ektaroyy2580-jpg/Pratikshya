// Smart Explore & Rides — trip planner (vanilla JS, Leaflet local).
(() => {
    const B = window.RIDES || {};
    const $ = id => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const npr = n => 'NPR ' + Number(n).toLocaleString('en-IN');

    const state = { pickup: null, dest: null, plan: null, mode: B.mode || null, consent: false, busy: false };

    // ---------- Map ----------
    const map = L.map('rdMap', { zoomControl: false }).setView([27.700, 85.318], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    L.control.zoom({ position: 'topright' }).addTo(map);
    const pin = (cls, t) => L.divIcon({ className: '', html: `<div class="rd-pin ${cls}"><span>${t}</span></div>`, iconSize: [30, 30], iconAnchor: [15, 30] });
    let pickMarker = null, destMarker = null, line = null;
    (B.locations || []).forEach(l => {
        L.marker([l.lat, l.lng], { icon: L.divIcon({ className: '', html: '<div class="rd-dot"></div>', iconSize: [12, 12], iconAnchor: [6, 6] }), keyboard: false })
            .addTo(map).bindTooltip(esc(l.name)).on('click', () => setDest({ ...l, location_id: l.id }));
    });

    function drawMarkers() {
        if (state.pickup) {
            if (!pickMarker) {
                pickMarker = L.marker([state.pickup.lat, state.pickup.lng], { icon: pin('pick', 'A'), draggable: true, title: 'Pickup (drag to adjust)' }).addTo(map);
                pickMarker.on('dragend', e => { const p = e.target.getLatLng(); setPickup({ lat: p.lat, lng: p.lng, label: 'Pinned pickup' }, false); });
            } else pickMarker.setLatLng([state.pickup.lat, state.pickup.lng]);
        }
        if (state.dest) {
            if (!destMarker) destMarker = L.marker([state.dest.lat, state.dest.lng], { icon: pin('dest', 'B'), title: 'Destination' }).addTo(map);
            else destMarker.setLatLng([state.dest.lat, state.dest.lng]);
        }
        if (line) { map.removeLayer(line); line = null; }
        if (state.pickup && state.dest) {
            const geo = state.plan && state.plan.geometry;
            line = geo
                ? L.polyline(geo, { color: '#2f5bea', weight: 5, opacity: .85 }).addTo(map).bindTooltip('Route from routing server — approximate', { sticky: true })
                : L.polyline([[state.pickup.lat, state.pickup.lng], [state.dest.lat, state.dest.lng]], { color: '#2f5bea', weight: 3, dashArray: '8 8', opacity: .9 })
                    .addTo(map).bindTooltip('direct line — actual route may differ', { permanent: true, direction: 'center', className: 'rd-line-label' });
            map.fitBounds(L.latLngBounds([[state.pickup.lat, state.pickup.lng], [state.dest.lat, state.dest.lng]]), { padding: [60, 60], maxZoom: 15 });
        } else {
            const p = state.pickup || state.dest; if (p) map.setView([p.lat, p.lng], 14);
        }
    }

    map.on('click', e => setPickup({ lat: e.latlng.lat, lng: e.latlng.lng, label: 'Pinned on map' }, false));

    // ---------- Pickup / destination ----------
    function setPickup(p, fromSearch = true) {
        state.pickup = { lat: +(+p.lat).toFixed(6), lng: +(+p.lng).toFixed(6), label: p.label || p.name || 'Pickup' };
        $('pickInput').value = state.pickup.label;
        document.querySelectorAll('[data-landmark]').forEach(b => b.classList.toggle('on', B.landmarks[b.dataset.landmark].name === state.pickup.label));
        drawMarkers(); requestPlan();
    }
    function setDest(d) {
        state.dest = { lat: +d.lat, lng: +d.lng, label: d.label || d.name, location_id: d.kind === 'location' ? (d.location_id || d.id) : (d.location_id || null), nearby_place_id: d.kind === 'place' ? (d.nearby_place_id || d.id) : (d.nearby_place_id || null) };
        $('destInput').value = state.dest.label;
        drawMarkers(); requestPlan();
    }

    document.querySelectorAll('[data-landmark]').forEach(b => b.addEventListener('click', () => {
        const lm = B.landmarks[b.dataset.landmark]; setPickup({ ...lm, label: lm.name });
    }));

    // GPS — only on explicit tap, with an explanation first.
    $('gpsBtn').addEventListener('click', () => {
        const note = $('gpsNote'); note.classList.remove('d-none');
        if (!('geolocation' in navigator)) { note.textContent = 'Your browser can’t share location. Pick a landmark or click the map instead.'; return; }
        note.innerHTML = '<i class="bi bi-info-circle me-1"></i>Your browser will ask permission. We only use your location to estimate this trip.';
        $('gpsBtn').disabled = true;
        navigator.geolocation.getCurrentPosition(pos => {
            $('gpsBtn').disabled = false; note.classList.add('d-none');
            setPickup({ lat: pos.coords.latitude, lng: pos.coords.longitude, label: 'My location' });
        }, err => {
            $('gpsBtn').disabled = false;
            note.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>' + (err.code === 1 ? 'Location permission was declined — no problem, pick a landmark or click the map.' : 'Couldn’t get your location. Pick a landmark or click the map.');
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 });
    });

    // Search suggestions (existing offices, nearby places, landmarks — all local data).
    function suggest(input, box, onPick, includeLandmarks) {
        let items = [];
        const render = () => {
            const q = input.value.trim().toLowerCase();
            const m = x => !q || (x.name + ' ' + (x.sub || '')).toLowerCase().includes(q);
            const groups = [];
            if (includeLandmarks) groups.push(['Landmarks', (B.landmarks || []).map(l => ({ ...l, sub: 'Landmark', kind: 'landmark' })).filter(m), 'bi-geo']);
            groups.push(['Offices', (B.locations || []).filter(m), 'bi-building']);
            groups.push(['Nearby places', (B.places || []).filter(m).slice(0, 8), 'bi-shop']);
            items = []; let html = '';
            groups.forEach(([t, list, icon]) => {
                if (!list.length) return;
                html += `<div class="grp">${t}</div>`;
                list.forEach(x => { items.push(x); html += `<button type="button" role="option" data-i="${items.length - 1}"><i class="bi ${icon}"></i><span>${esc(x.name)}<small>${esc(x.sub || '')}</small></span></button>`; });
            });
            box.innerHTML = html || '<div class="p-3 small pk-muted">No matches. Try another word or click the map.</div>';
            box.classList.remove('d-none'); input.setAttribute('aria-expanded', 'true');
        };
        const close = () => { setTimeout(() => { box.classList.add('d-none'); input.setAttribute('aria-expanded', 'false'); }, 150); };
        input.addEventListener('focus', () => { input.select(); render(); });
        input.addEventListener('input', render);
        input.addEventListener('blur', close);
        input.addEventListener('keydown', e => {
            const btns = [...box.querySelectorAll('button')]; let i = btns.findIndex(b => b.classList.contains('active'));
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); i = e.key === 'ArrowDown' ? Math.min(btns.length - 1, i + 1) : Math.max(0, i - 1); btns.forEach(b => b.classList.remove('active')); btns[i] && btns[i].classList.add('active'); }
            if (e.key === 'Enter') { e.preventDefault(); const b = btns[i >= 0 ? i : 0]; if (b) { onPick(items[b.dataset.i]); input.blur(); } }
            if (e.key === 'Escape') input.blur();
        });
        box.addEventListener('mousedown', e => { const b = e.target.closest('button[data-i]'); if (b) { e.preventDefault(); onPick(items[b.dataset.i]); input.blur(); } });
    }
    suggest($('pickInput'), $('pickSuggest'), x => setPickup({ lat: x.lat, lng: x.lng, label: x.name }), true);
    suggest($('destInput'), $('destSuggest'), x => setDest(x.kind === 'landmark' ? { ...x, kind: 'landmark' } : x), true);

    // ---------- Planning ----------
    let reqId = 0;
    function show(which) { ['emptyState', 'loadingState', 'errorState', 'planState'].forEach(id => $(id).classList.toggle('d-none', id !== which)); }
    async function requestPlan() {
        if (!state.pickup || !state.dest) {
            show('emptyState');
            if (state.pickup && !state.dest) $('emptyState').querySelector('.fw-bold').textContent = 'Where to?';
            return;
        }
        const my = ++reqId; if (!state.plan) show('loadingState');
        try {
            const res = await fetch(B.quoteUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(payload()) });
            const json = await res.json().catch(() => ({}));
            if (my !== reqId) return;
            if (!res.ok) { const errs = json.errors ? Object.values(json.errors).flat() : []; $('errorState').innerHTML = '<i class="bi bi-exclamation-octagon me-1"></i>' + esc(errs[0] || json.message || 'Could not plan this trip.'); show('errorState'); return; }
            state.plan = json.data;
            if (!state.mode) state.mode = state.plan.recommend.mode;
            drawMarkers(); render(); show('planState');
        } catch (e) {
            if (my !== reqId) return;
            $('errorState').innerHTML = '<i class="bi bi-wifi-off me-1"></i>Network problem — check your connection and try again.'; show('errorState');
        }
    }
    function payload() {
        return {
            pickup_lat: state.pickup.lat, pickup_lng: state.pickup.lng, pickup_label: state.pickup.label,
            destination_lat: state.dest.lat, destination_lng: state.dest.lng, destination_label: state.dest.label,
            location_id: state.dest.location_id || null, nearby_place_id: state.dest.nearby_place_id || null,
        };
    }

    function render() {
        const p = state.plan;
        $('modeTabs').innerHTML = p.modes.map(m => `<button type="button" class="rd-mode" role="tab" data-mode="${m.key}" aria-selected="${m.key === state.mode}">
            <i class="bi ${m.icon}"></i><b>${m.minutes} min</b><span>${esc(m.label.replace('Public transport', 'Bus'))}</span>${p.recommend.mode === m.key ? '<span class="rec">Suggested</span>' : ''}</button>`).join('');
        $('modeTabs').querySelectorAll('[data-mode]').forEach(b => b.addEventListener('click', () => { state.mode = b.dataset.mode; render(); }));
        $('tripMeta').innerHTML = `<span><i class="bi bi-signpost me-1"></i>≈ ${p.road_km.toFixed(1)} km by road</span><span>·</span><span>${p.routing === 'routing' ? 'routed distance' : 'straight line × 1.3'}</span><span class="rd-est"><i class="bi bi-clock"></i>approx.</span>`;
        renderLeave();

        const m = p.modes.find(x => x.key === state.mode);
        let html = `<div class="rd-info mb-3"><i class="bi bi-lightbulb"></i><div>${esc(p.recommend.reason)}</div></div>`;
        if (state.mode === 'walking') {
            html += `<div class="pk-card p-3 d-flex gap-3 align-items-center"><span class="pk-icon green"><i class="bi bi-person-walking"></i></span><div class="flex-grow-1"><div class="fw-bold">Walk about ${m.minutes} min</div><div class="small pk-muted">Free · at ~${m.speed_kmh} km/h. Follow the map, actual footpaths may differ.</div></div></div>`;
            html += compareLink();
        } else if (state.mode === 'transit') {
            html += `<div class="pk-card p-3 d-flex gap-3 align-items-center"><span class="pk-icon amber"><i class="bi bi-bus-front"></i></span><div class="flex-grow-1"><div class="fw-bold">Bus / microbus about ${m.minutes} min</div><div class="small pk-muted">Includes ~${m.wait_minutes} min waiting at the stop. Fares are low but vary by route — check with the conductor.</div></div></div>`;
            html += compareLink();
        } else {
            const quotes = p.quotes.filter(q => q.mode === state.mode).sort((a, b) => a.fare_low - b.fare_low);
            html += `<h2 class="rd-section-t">Ride options <span class="rd-est"><i class="bi bi-calculator"></i>Estimates</span></h2>`;
            if (!quotes.length) html += '<div class="rd-empty">No ride options for this mode.</div>';
            html += '<div class="d-grid gap-2">' + quotes.map((q, i) => `
                <button type="button" class="rd-ride" style="--acc:${esc(q.color)}" data-q="${esc(q.provider)}|${esc(q.vehicle_type)}" aria-pressed="false">
                    ${i === 0 ? '<span class="rd-cheapest">Lowest estimate</span>' : ''}
                    <span class="rd-badge">${esc(q.initial)}</span>
                    <span class="flex-grow-1" style="min-width:0">
                        <span class="nm d-block">${esc(q.provider_name)} <span class="pk-muted fw-semibold">· ${esc(q.vehicle_label)}</span></span>
                        <span class="sub d-block"><i class="bi bi-clock me-1"></i>~${q.minutes} min trip${q.pickup_eta ? ` · pickup usually ${q.pickup_eta[0]}–${q.pickup_eta[1]} min` : ''}${q.seats ? ` · <i class="bi bi-person"></i> ${q.seats}` : ''}</span>
                    </span>
                    <span class="fare">${npr(q.fare_low)}–${Number(q.fare_high).toLocaleString('en-IN')}<small><span class="rd-est">Estimate</span></small></span>
                </button>`).join('') + '</div>';
        }
        $('modeBody').innerHTML = html;
        $('modeBody').querySelectorAll('[data-q]').forEach(b => b.addEventListener('click', () => select(b)));
        $('modeBody').querySelectorAll('[data-go-mode]').forEach(b => b.addEventListener('click', () => { state.mode = b.dataset.goMode; render(); }));
    }
    function compareLink() {
        return `<button type="button" class="btn btn-ghost btn-sm mt-2 w-100" data-go-mode="car"><i class="bi bi-car-front me-1"></i>Compare ride fares instead</button>`;
    }
    function renderLeave() {
        const card = $('leaveCard'); if (!card) return;
        const lb = state.plan && state.plan.leave_by && state.plan.leave_by[state.mode];
        if (!lb) { $('leaveSub').textContent = state.plan ? 'Leave-by appears when the destination is the office you are queued at.' : $('leaveSub').textContent; return; }
        const mode = state.plan.modes.find(x => x.key === state.mode);
        card.classList.toggle('urgent', lb.leave_now && lb.ticket_status === 'waiting' ? lb.late_by > 0 : lb.leave_now);
        if (lb.ticket_status !== 'waiting') { $('leaveBig').textContent = 'You’ve been called — leave now'; }
        else if (lb.leave_now) { $('leaveBig').textContent = 'Leave now'; }
        else { $('leaveBig').textContent = `Leave by ${lb.leave_at}`; }
        $('leaveSub').textContent = `${mode.label} ~${lb.travel_minutes} min + ${lb.buffer_minutes} min buffer · your turn in ~${lb.turn_low}–${lb.turn_high} min (estimate)` + (lb.late_by > 0 ? ` · you may be ~${lb.late_by} min late — a faster mode helps` : '');
    }

    // ---------- Select → confirmation sheet ----------
    const sheet = $('sheet'), backdrop = $('sheetBackdrop');
    function openSheet() { sheet.classList.add('show'); backdrop.classList.add('show'); sheet.setAttribute('aria-hidden', 'false'); setTimeout(() => $('handoffBtn').focus(), 300); }
    function closeSheet() { sheet.classList.remove('show'); backdrop.classList.remove('show'); sheet.setAttribute('aria-hidden', 'true'); document.querySelectorAll('.rd-ride').forEach(b => b.setAttribute('aria-pressed', 'false')); }
    $('sheetClose').addEventListener('click', closeSheet); backdrop.addEventListener('click', closeSheet);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && sheet.classList.contains('show')) closeSheet(); });

    async function select(btn) {
        if (state.busy) return;
        const [provider, vehicle] = btn.dataset.q.split('|');
        const q = state.plan.quotes.find(x => x.provider === provider && x.vehicle_type === vehicle);
        document.querySelectorAll('.rd-ride').forEach(b => b.setAttribute('aria-pressed', String(b === btn)));
        state.busy = true; btn.disabled = true;
        try {
            const res = await fetch(B.storeUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ ...payload(), provider, vehicle_type: vehicle, consent_share: state.consent }) });
            const json = await res.json().catch(() => ({}));
            if (!res.ok) { const errs = json.errors ? Object.values(json.errors).flat() : []; alertBox(errs[0] || json.message || 'Could not save this trip.'); return; }
            const d = json.data;
            $('sheetBadge').textContent = q.initial; $('sheetBadge').style.setProperty('--acc', q.color);
            $('sheetTitle').textContent = `${d.provider} · ${d.vehicle}`;
            $('sheetSub').textContent = q.tagline || '';
            $('sheetFrom').textContent = state.pickup.label; $('sheetTo').textContent = state.dest.label;
            $('sheetFare').textContent = `${npr(d.fare_low)}–${Number(d.fare_high).toLocaleString('en-IN')}`;
            $('sheetTime').innerHTML = `~${d.est_minutes} min<br>${d.distance_km.toFixed(1)} km`;
            $('sheetNote').textContent = q.surge_note || '';
            $('handoffForm').action = d.handoff_url; $('demoForm').action = d.demo_url;
            $('handoffForm').dataset.show = d.show_url;
            $('handoffTitle').textContent = `Open ${d.provider}`;
            $('handoffText').textContent = `Fare and booking are confirmed in the ${d.provider} app`;
            openSheet();
        } catch (e) { alertBox('Network problem — please try again.'); }
        finally { state.busy = false; btn.disabled = false; }
    }
    function alertBox(msg) { $('errorState').innerHTML = '<i class="bi bi-exclamation-octagon me-1"></i>' + esc(msg); $('errorState').classList.remove('d-none'); }

    // The hand-off opens the official site in a new tab; this tab moves to the trip page.
    $('handoffForm').addEventListener('submit', () => { const url = $('handoffForm').dataset.show; setTimeout(() => { window.location = url; }, 600); });

    // ---------- Carpool consent ----------
    const tgl = $('carpoolToggle'), ack = $('carpoolAck');
    const syncConsent = () => { $('carpoolConsent').classList.toggle('d-none', !tgl.checked); state.consent = tgl.checked && ack.checked; };
    tgl.addEventListener('change', () => { if (!tgl.checked) ack.checked = false; syncConsent(); });
    ack.addEventListener('change', syncConsent);

    // ---------- Boot ----------
    if (B.destination) setDest(B.destination);
    if (B.pickup) setPickup(B.pickup);
    if (!B.pickup && !B.destination) map.setView([27.700, 85.318], 13);
    setTimeout(() => { map.invalidateSize(); if (state.pickup && state.dest) map.fitBounds(L.latLngBounds([[state.pickup.lat, state.pickup.lng], [state.dest.lat, state.dest.lng]]), { padding: [60, 60], maxZoom: 15 }); }, 250);
})();
