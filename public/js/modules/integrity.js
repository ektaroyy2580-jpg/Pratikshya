/* Integrity module: exchange countdowns, two-tap confirm, polling for new requests. No dependencies. */
(() => {
    'use strict';
    if (window.__pkIntegrity) return;   // partials may include this file more than once
    window.__pkIntegrity = true;

    // ---- Countdowns: <span class="ig-countdown" data-expires="ISO"> ----
    const fmt = s => s >= 3600 ? Math.floor(s / 3600) + 'h ' + String(Math.floor(s % 3600 / 60)).padStart(2, '0') + 'm'
        : Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
    const counters = [...document.querySelectorAll('[data-expires]')];
    const tick = () => {
        const now = Date.now();
        counters.forEach(el => {
            const left = Math.max(0, Math.round((Date.parse(el.dataset.expires) - now) / 1000));
            const label = el.querySelector('.v') || el;
            if (left === 0) {
                label.textContent = 'Expired';
                el.classList.add('over');
                // The server enforces expiry too; this just prevents pointless clicks.
                el.closest('[data-ig-card]')?.querySelectorAll('button[data-expiring]').forEach(b => { b.disabled = true; });
            } else {
                label.textContent = fmt(left) + ' left';
            }
        });
    };
    if (counters.length) { tick(); setInterval(tick, 1000); }

    // ---- Two-tap confirmation for consequential buttons ----
    document.querySelectorAll('button[data-ig-confirm]').forEach(btn => {
        let armed = false, timer;
        const original = btn.innerHTML;
        btn.addEventListener('click', e => {
            if (armed) { btn.disabled = true; btn.form?.submit(); return; }
            e.preventDefault();
            armed = true;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>' + btn.dataset.igConfirm;
            timer = setTimeout(() => { armed = false; btn.innerHTML = original; }, 4000);
        });
        btn.form?.addEventListener('submit', () => clearTimeout(timer));
    });

    // ---- Disable submit buttons after submit (prevents double posts) ----
    document.querySelectorAll('form[data-ig-once]').forEach(f => f.addEventListener('submit', () => {
        f.querySelectorAll('button[type=submit]').forEach(b => {
            b.disabled = true;
            if (b.dataset.busy) b.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + b.dataset.busy;
        });
    }));

    // ---- Token input: uppercase, strip spaces ----
    document.querySelectorAll('input[data-ig-token]').forEach(i => i.addEventListener('input', () => {
        const pos = i.selectionStart;
        i.value = i.value.toUpperCase().replace(/\s+/g, '');
        i.setSelectionRange(pos, pos);
    }));

    // ---- Polling (exchange page): show a refresh bar when something changed ----
    const poll = document.querySelector('[data-ig-poll]');
    if (poll) {
        const bar = document.getElementById('igUpdateBar');
        let sig = poll.dataset.igSignature || null;
        const check = async () => {
            if (document.hidden) return;
            try {
                const r = await fetch(poll.dataset.igPoll, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) return;
                const { data } = await r.json();
                if (sig === null) { sig = data.signature; return; }
                if (data.signature !== sig) {
                    const typing = document.activeElement && ['INPUT', 'TEXTAREA'].includes(document.activeElement.tagName) && document.activeElement.value;
                    if (!typing) { location.reload(); return; }
                    bar?.classList.add('show');
                }
            } catch (_) { /* offline: try again next tick */ }
        };
        setInterval(check, 8000);
    }
})();
