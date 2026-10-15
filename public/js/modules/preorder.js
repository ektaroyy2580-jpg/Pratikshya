/* Cafe preordering — progressive enhancement (pages work without JS). */
window.PreorderUI = (() => {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const live = msg => { const el = document.getElementById('poLive'); if (el) { el.textContent = ''; setTimeout(() => (el.textContent = msg), 30); } };

    function toast(msg, ok = true) {
        document.getElementById('poToast')?.remove();
        const d = document.createElement('div');
        d.id = 'poToast'; d.className = 'toast-ww'; d.setAttribute('role', 'status');
        d.innerHTML = `<div class="alert ${ok ? 'alert-success' : 'alert-danger'} shadow d-flex align-items-center gap-2 mb-0"><i class="bi ${ok ? 'bi-check-circle-fill' : 'bi-exclamation-octagon-fill'}"></i><span class="flex-grow-1 fw-semibold"></span></div>`;
        d.querySelector('span').textContent = msg;
        document.body.appendChild(d);
        setTimeout(() => d.remove(), 3200);
    }

    async function post(url, body) {
        const r = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body, credentials: 'same-origin' });
        let json = {}; try { json = await r.json(); } catch (e) {}
        return { ok: r.ok, status: r.status, json };
    }

    // ---------------- Menu page ----------------
    function menu({ max }) {
        const bar = document.getElementById('poCartBar');

        function render(form, qty) {
            form.classList.toggle('has-qty', qty > 0);
            const add = form.querySelector('.js-add'), st = form.querySelector('.js-stepper');
            if (!st) return;
            add.hidden = qty > 0; st.hidden = qty === 0;
            form.querySelector('.js-qty').textContent = qty;
            form.querySelector('.js-dec').value = Math.max(0, qty - 1);
            const inc = form.querySelector('.js-inc'); inc.value = qty + 1; inc.disabled = qty >= max;
            st.classList.remove('bump'); void st.offsetWidth; st.classList.add('bump');
            document.querySelector(`[data-item-card="${form.dataset.item}"]`)?.classList.toggle('in-cart', qty > 0);
        }
        function updateBar(data) {
            if (!bar || !data) return;
            bar.querySelector('.js-cart-count').textContent = data.count;
            bar.querySelector('.js-cart-subtotal').textContent = data.subtotal_label;
            bar.classList.toggle('show', data.count > 0);
        }

        document.querySelectorAll('form.js-item').forEach(form => {
            form.addEventListener('submit', async e => {
                e.preventDefault();
                const btn = e.submitter; if (!btn) return;
                const fd = new FormData(form); fd.set('qty', btn.value);
                form.querySelectorAll('button').forEach(b => (b.disabled = true));
                let res = await post(form.action, fd);
                if (res.status === 422 && res.json?.data?.code === 'cart_conflict') {
                    if (confirm(res.json.message + '\n\nStart a new cart from this café?')) { fd.set('replace', '1'); res = await post(form.action, fd); }
                    else { form.querySelectorAll('button').forEach(b => (b.disabled = false)); return; }
                }
                form.querySelectorAll('button').forEach(b => (b.disabled = false));
                if (!res.ok) { toast(res.json?.message || 'Could not update your cart. Please try again.', false); return; }
                // A replaced cart resets every other item on the page.
                document.querySelectorAll('form.js-item').forEach(f => {
                    const q = +(res.json.data.items?.[f.dataset.item] || 0);
                    if (f === form || +f.querySelector('.js-qty')?.textContent !== q) render(f, q);
                });
                updateBar(res.json.data); live(res.json.message);
                // Let the café page (and anything else) react to cart changes.
                document.dispatchEvent(new CustomEvent('preorder:cart', { detail: res.json.data }));
            });
        });
    }

    // ---------------- Order status page ----------------
    function order({ url, steps, initial }) {
        let last = initial, timer = null, fails = 0;
        const $ = s => document.querySelector(s);
        function apply(d) {
            $('#poStatusLabel').textContent = d.status_label;
            const stepEls = document.querySelectorAll('.po-track .st');
            stepEls.forEach((el, i) => { el.classList.toggle('done', d.step !== null && i < d.step); el.classList.toggle('now', d.step !== null && i === d.step && d.status !== 'collected'); if (d.status === 'collected' && i <= d.step) el.classList.add('done'); });
            const eta = $('#poEta'); if (eta) eta.textContent = d.status === 'ready' ? 'Ready now' : (d.minutes_to_ready !== null ? `~${d.minutes_to_ready} min` : (d.estimated_ready_label || '—'));
            const codeBox = $('#poCodeBox'); if (codeBox) { codeBox.hidden = !d.pickup_code; if (d.pickup_code) $('#poCode').textContent = d.pickup_code; }
            const hint = $('#poPlacedHint'); if (hint) hint.hidden = d.status !== 'placed';
            const cancel = $('#poCancel'); if (cancel) cancel.hidden = !d.can_cancel;
            const reason = $('#poReason'); if (reason) { reason.hidden = !d.status_reason || !['rejected', 'cancelled'].includes(d.status); reason.querySelector('span').textContent = d.status_reason || ''; }
        }
        async function tick() {
            try {
                const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) throw new Error(r.status);
                const { data } = await r.json(); fails = 0;
                $('#poConn')?.setAttribute('hidden', '');
                if (data.status !== last) {
                    // Status changed: reload once so the receipt, timestamps and banners are all fresh.
                    if (['ready', 'rejected', 'collected', 'cancelled'].includes(data.status)) { location.reload(); return; }
                    apply(data); live('Order update: ' + data.status_label); last = data.status;
                } else apply(data);
                if (!data.is_open) { clearInterval(timer); return; }
            } catch (e) { if (++fails >= 2) $('#poConn')?.removeAttribute('hidden'); }
        }
        timer = setInterval(tick, 7000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });
    }

    // ---------------- Merchant dashboard ----------------
    function merchant({ url, signature }) {
        let busy = false;
        setInterval(async () => {
            if (busy || document.hidden) return;
            const a = document.activeElement;
            if (a && ['INPUT', 'TEXTAREA', 'SELECT'].includes(a.tagName) && a.value) return; // don't reload while typing
            busy = true;
            try {
                const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (r.ok) { const { data } = await r.json(); if (data.signature !== signature) location.reload(); }
            } catch (e) {}
            busy = false;
        }, 8000);
        document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    }

    // ---------------- Checkout ----------------
    function checkout() {
        const form = document.getElementById('poCheckout'); if (!form) return;
        const ack = form.querySelector('[name="acknowledge_conflict"]'), btn = form.querySelector('#poPlace');
        const sync = () => { if (ack) btn.disabled = !ack.checked; };
        ack?.addEventListener('change', sync); sync();
        form.addEventListener('submit', () => { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Placing order…'; });
    }

    return { menu, order, merchant, checkout, toast };
})();
