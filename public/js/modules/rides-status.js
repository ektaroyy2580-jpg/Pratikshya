// Ride status page: map + polling of the server-computed (demo) status.
(() => {
    const R = window.RIDE || {};
    const $ = id => document.getElementById(id);
    const map = L.map('rdMap', { zoomControl: false, scrollWheelZoom: false });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap contributors', maxZoom: 19 }).addTo(map);
    L.control.zoom({ position: 'topright' }).addTo(map);
    const pin = (cls, t) => L.divIcon({ className: '', html: `<div class="rd-pin ${cls}"><span>${t}</span></div>`, iconSize: [30, 30], iconAnchor: [15, 30] });
    L.marker(R.pickup, { icon: pin('pick', 'A'), title: 'Pickup' }).addTo(map);
    L.marker(R.dest, { icon: pin('dest', 'B'), title: 'Destination' }).addTo(map);
    L.polyline([R.pickup, R.dest], { color: '#2f5bea', weight: 3, dashArray: '8 8' }).addTo(map)
        .bindTooltip('direct line — actual route may differ', { permanent: true, direction: 'center', className: 'rd-line-label' });
    map.fitBounds([R.pickup, R.dest], { padding: [50, 50], maxZoom: 15 });
    setTimeout(() => { map.invalidateSize(); map.fitBounds([R.pickup, R.dest], { padding: [50, 50], maxZoom: 15 }); }, 400);
    let car = null;

    const labels = { demo_requested: 'Demo · requested', demo_driver_assigned: 'Demo · driver', demo_arriving: 'Demo · arriving', demo_on_trip: 'Demo · on trip', demo_completed: 'Demo · done', cancelled: 'Cancelled' };
    const cls = { demo_requested: 'waiting', demo_driver_assigned: 'waiting', demo_arriving: 'waiting', demo_on_trip: 'called', demo_completed: 'done', cancelled: 'done' };

    function apply(s) {
        const idx = R.steps.indexOf(s.status);
        document.querySelectorAll('#steps li').forEach((li, i) => {
            li.classList.toggle('done', idx >= 0 && (i < idx || s.status === 'demo_completed'));
            li.classList.toggle('now', i === idx && s.status !== 'demo_completed');
        });
        if ($('tripBar')) $('tripBar').style.width = (s.progress || 0) + '%';
        const chip = document.querySelector('#statusChip [data-status-chip]');
        if (chip && labels[s.status]) { chip.textContent = labels[s.status]; chip.className = 'pk-status ' + cls[s.status]; }
        if ($('etaText')) {
            const e = s.eta_seconds;
            $('etaText').textContent = s.status === 'demo_completed' ? 'Simulation finished'
                : e == null ? 'Simulating…' : (s.status === 'demo_on_trip' ? 'Arrives in ' : 'Pickup in ') + (e >= 60 ? Math.ceil(e / 60) + ' min' : e + ' s') + ' (simulated)';
        }
        if (s.driver && $('driverCard')) { $('driverCard').classList.remove('d-none'); $('driverName').textContent = s.driver.name; $('driverVeh').textContent = s.driver.vehicle + ' · ' + s.driver.plate; }
        if (s.vehicle) {
            const ll = [s.vehicle.lat, s.vehicle.lng];
            if (!car) car = L.marker(ll, { icon: L.divIcon({ className: '', html: '<div class="rd-car"><i class="bi bi-car-front-fill"></i></div>', iconSize: [34, 34], iconAnchor: [17, 17] }), title: 'Simulated vehicle' }).addTo(map).bindTooltip('Simulated — DEMO');
            else car.setLatLng(ll);
        }
        if (s.carpool) Object.entries(s.carpool).forEach(([k, v]) => { const el = document.querySelector(`[data-cp="${k}"]`); if (el) el.textContent = v; });
        if (!s.cancellable && $('cancelForm')) $('cancelForm').remove();
    }

    apply(R.state || {});
    if (!R.poll) return;
    let timer = null, fails = 0;
    async function poll() {
        try {
            const res = await fetch(R.stateUrl, { headers: { Accept: 'application/json' } });
            if (!res.ok) throw new Error(res.status);
            const { data } = await res.json(); fails = 0; apply(data);
            if (['demo_completed', 'cancelled'].includes(data.status)) return;
        } catch (e) { if (++fails > 5) { if ($('etaText')) $('etaText').textContent = 'Connection lost — refresh to update'; return; } }
        timer = setTimeout(poll, document.hidden ? 10000 : 5000);
    }
    timer = setTimeout(poll, 3000);
})();
