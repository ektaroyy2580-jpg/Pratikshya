/* Live office announcements: polls the public feed every 10s and re-renders the banner (text only, never HTML). */
(() => {
    const ICONS = { info: 'bi-info-circle-fill', warning: 'bi-exclamation-triangle-fill', critical: 'bi-exclamation-octagon-fill' };
    const SEVS = ['info', 'warning', 'critical'];
    const INTERVAL = 10000;

    const card = (n) => {
        const sev = SEVS.includes(n.severity) ? n.severity : 'info';
        const el = document.createElement('div');
        el.className = `notice-preview sev-${sev}`;
        el.dataset.id = n.id;
        const ic = document.createElement('span'); ic.className = 'ic';
        const i = document.createElement('i'); i.className = `bi ${ICONS[sev]}`; ic.appendChild(i);
        const body = document.createElement('div'); body.className = 'min-w-0';
        const tt = document.createElement('div'); tt.className = 'tt'; tt.textContent = n.title || '';
        const mm = document.createElement('div'); mm.className = 'mm'; mm.textContent = n.message || '';
        body.append(tt, mm); el.append(ic, body);
        return el;
    };

    document.querySelectorAll('[data-ops-feed]').forEach(box => {
        let busy = false, failures = 0;
        const sig = () => Array.from(box.children).map(c => c.dataset.id).join(',');
        const tick = async () => {
            if (busy || document.hidden) return;
            busy = true;
            try {
                const res = await fetch(box.dataset.opsFeed, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
                if (!res.ok) throw new Error(res.status);
                const json = await res.json();
                const list = Array.isArray(json.data) ? json.data : [];
                const next = list.map(n => String(n.id)).join(',');
                // Re-render when the set changes or text may have changed; skip otherwise to avoid flicker.
                if (next !== sig() || list.some(n => { const c = box.querySelector(`[data-id="${n.id}"] .mm`); return !c || c.textContent !== n.message; })) {
                    box.replaceChildren(...list.map(card));
                }
                failures = 0;
            } catch (e) {
                failures++; // keep showing the last known notices; back off quietly
            } finally {
                busy = false;
            }
        };
        let skip = 0;
        setInterval(() => {
            if (skip > 0) { skip--; return; }
            tick().then(() => { if (failures) skip = Math.min(failures, 6); }); // linear back-off after errors
        }, INTERVAL);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    });
})();
