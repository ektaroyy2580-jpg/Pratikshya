// Platform module: shared form behaviour for console/settings pages.
// - <form data-confirm="..."> asks before submitting (message comes from an escaped attribute, never eval'd).
// - Submit buttons show a spinner and are disabled once, to prevent double submits.
(() => {
    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        const msg = form.dataset.confirm;
        if (msg && !window.confirm(msg)) { e.preventDefault(); return; }
        if (form.dataset.noBusy !== undefined) return;
        const btn = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
        if (btn && !btn.disabled) {
            btn.dataset.label = btn.innerHTML;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + (btn.dataset.busy || 'Working…');
            setTimeout(() => { btn.disabled = true; }, 0);
            // Safety: restore after 8s if navigation didn't happen (e.g. file download).
            setTimeout(() => { btn.disabled = false; btn.innerHTML = btn.dataset.label; }, 8000);
        }
    });
})();
