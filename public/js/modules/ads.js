/* Ads module: viewable-impression beacon for sponsored slots.
   An impression is sent once per slot, only after at least half of the card has been on screen for 1 second.
   The server de-duplicates per viewer + campaign + placement (30 min), so reloads do not inflate counts. */
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function send(slot) {
        if (slot.dataset.adSent) return;
        slot.dataset.adSent = '1';
        const body = new FormData();
        body.append('t', slot.dataset.adToken);
        body.append('_token', csrf);
        const url = slot.dataset.adEndpoint;
        if (navigator.sendBeacon && navigator.sendBeacon(url, body)) return;
        fetch(url, { method: 'POST', body, credentials: 'same-origin', keepalive: true, headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } }).catch(() => {});
    }

    function watch(slot) {
        if (!slot.dataset.adToken || slot.dataset.adWatched) return;
        slot.dataset.adWatched = '1';
        if (!('IntersectionObserver' in window)) { send(slot); return; }
        let timer = null;
        const io = new IntersectionObserver(entries => entries.forEach(e => {
            if (e.isIntersecting && e.intersectionRatio >= 0.5) {
                timer ??= setTimeout(() => { send(slot); io.disconnect(); }, 1000);
            } else if (timer) { clearTimeout(timer); timer = null; }
        }), { threshold: [0, 0.5, 1] });
        io.observe(slot);
    }

    const scan = () => document.querySelectorAll('[data-ad-token]').forEach(watch);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
    window.PkAds = { scan };
})();
