// Platform module: Leaflet pin picker (local Leaflet, OSM tiles).
// Usage: <div data-pf-map data-lat="#lat" data-lng="#lng"></div>
// Click the map or drag the pin to update the lat/lng inputs; typing coordinates moves the pin.
// If tiles fail to load (offline), the coordinate inputs keep working.
(() => {
    if (!window.L) return;
    document.querySelectorAll('[data-pf-map]').forEach((el) => {
        const latIn = document.querySelector(el.dataset.lat);
        const lngIn = document.querySelector(el.dataset.lng);
        if (!latIn || !lngIn) return;
        const start = [parseFloat(latIn.value) || 27.7, parseFloat(lngIn.value) || 85.32];
        const map = L.map(el, { scrollWheelZoom: false }).setView(start, 15);
        const tiles = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19, attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);
        let warned = false;
        tiles.on('tileerror', () => {
            if (warned) return; warned = true;
            const note = document.querySelector(el.dataset.note || '');
            if (note) note.innerHTML = '<i class="bi bi-wifi-off me-1"></i>Map tiles could not load (offline?). You can still type coordinates.';
        });
        const marker = L.marker(start, { draggable: true, keyboard: true, title: 'Location pin' }).addTo(map);
        const write = (ll) => {
            latIn.value = ll.lat.toFixed(6);
            lngIn.value = ll.lng.toFixed(6);
            [latIn, lngIn].forEach((i) => { i.classList.add('is-valid'); setTimeout(() => i.classList.remove('is-valid'), 900); });
        };
        marker.on('dragend', () => write(marker.getLatLng()));
        map.on('click', (e) => { marker.setLatLng(e.latlng); write(e.latlng); });
        const read = () => {
            const la = parseFloat(latIn.value), ln = parseFloat(lngIn.value);
            if (Number.isFinite(la) && Number.isFinite(ln)) { marker.setLatLng([la, ln]); map.panTo([la, ln]); }
        };
        latIn.addEventListener('change', read);
        lngIn.addEventListener('change', read);
        // Re-measure if the map was hidden in a collapsed section.
        setTimeout(() => map.invalidateSize(), 300);
    });
})();
