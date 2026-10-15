/* Ops console helpers: live notice preview + character count, local-time inputs/labels, submit feedback. */
(() => {
    const SEV = {
        info: ['sev-info', 'bi-info-circle-fill'],
        warning: ['sev-warning', 'bi-exclamation-triangle-fill'],
        critical: ['sev-critical', 'bi-exclamation-octagon-fill'],
    };
    const pad = n => String(n).padStart(2, '0');
    const toLocalInput = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;

    // Absolute times rendered by the server in UTC → viewer's local time.
    document.querySelectorAll('time.js-local').forEach(t => {
        const d = new Date(t.getAttribute('datetime'));
        if (!isNaN(d)) t.textContent = d.toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    });

    // datetime-local inputs mirror into a hidden ISO field (so the server gets an unambiguous instant).
    document.querySelectorAll('.js-local-input').forEach(inp => {
        const target = document.getElementById(inp.dataset.target);
        if (inp.dataset.iso) {
            const d = new Date(inp.dataset.iso);
            if (!isNaN(d)) inp.value = toLocalInput(d);
        }
        const sync = () => {
            if (!target) return;
            if (!inp.value) { target.value = ''; return; }
            const d = new Date(inp.value);
            target.value = isNaN(d) ? '' : d.toISOString();
        };
        inp.addEventListener('change', sync);
        inp.addEventListener('input', sync);
    });

    // Notice composer: preview + counter.
    document.querySelectorAll('.js-notice-form').forEach(form => {
        const title = form.querySelector('.js-n-title'), msg = form.querySelector('.js-n-msg'), sev = form.querySelector('.js-n-sev');
        const count = form.querySelector('.js-n-count'), prev = form.querySelector('.js-n-preview');
        if (!title || !msg) return;
        const render = () => {
            const n = msg.value.length;
            if (count) { count.textContent = `${n}/280`; count.classList.toggle('over', n > 280); }
            if (prev) {
                const [cls, icon] = SEV[sev?.value] || SEV.info;
                prev.className = `notice-preview mb-3 js-n-preview ${cls}`;
                prev.querySelector('.ic i').className = `bi ${icon}`;
                prev.querySelector('.js-p-title').textContent = title.value || 'Title';
                prev.querySelector('.js-p-msg').textContent = msg.value || 'Your public message appears here.';
            }
        };
        [title, msg, sev].forEach(el => el && el.addEventListener('input', render));
        sev && sev.addEventListener('change', render);
        render();
    });

    // Disable the submit button once to prevent double posts; show progress.
    document.querySelectorAll('form[method="POST"]').forEach(f => f.addEventListener('submit', e => {
        if (e.defaultPrevented) return;
        const b = e.submitter || f.querySelector('button:not([type="button"])');
        if (b && !b.disabled) { setTimeout(() => { b.disabled = true; }, 0); b.insertAdjacentHTML('afterbegin', '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>'); }
    }));

    // Confirmation for destructive actions.
    document.querySelectorAll('form.js-confirm').forEach(f => f.addEventListener('submit', e => {
        if (!window.confirm(f.dataset.confirm || 'Are you sure?')) e.preventDefault();
    }, true));
})();
