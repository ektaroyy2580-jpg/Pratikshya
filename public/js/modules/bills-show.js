/* Bill detail page: live assignment preview, member self-selection, custom-amount sum, invite link + QR. */
(function () {
    'use strict';
    const C = window.BillCalc;
    const dataEl = document.getElementById('billData');
    if (!C || !dataEl) return;
    const D = JSON.parse(dataEl.textContent);
    const input = D.input;
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const esc = (s) => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    // ---------- Render a calculation result into the page ----------
    function render(res) {
        Object.values(res.people).forEach(p => {
            const card = document.querySelector(`.bl-person[data-p="${p.id}"]`);
            if (!card) return;
            ['total', 'items', 'service', 'vat', 'tip'].forEach(f => C.tick(card.querySelector(`[data-f="${f}"]`), p[f]));
            C.tick(card.querySelector('[data-f="discount"]'), p.discount, '−');
            const dr = card.querySelector('[data-row="discount"]'); if (dr) dr.hidden = !p.discount;
            const tr = card.querySelector('[data-row="tip"]'); if (tr) tr.hidden = !p.tip;
            const lines = card.querySelector('[data-f="lines"]');
            if (lines) lines.innerHTML = p.lines.length
                ? p.lines.map(l => `<div class="text-truncate">${esc(l.name)}${l.of > l.weight ? ` <span>(${l.weight}/${l.of})</span>` : ''} · ${C.money(l.amount)}</div>`).join('')
                : '<div>No items yet</div>';
            if (String(p.id) === String(D.me)) C.tick(document.querySelector('[data-g="mine"]'), p.total);
        });
        C.tick(document.querySelector('[data-g="grand"]'), res.totals.grand_total);
        Object.entries(res.items).forEach(([id, it]) => {
            const row = document.querySelector(`tr[data-item="${id}"]`);
            if (row) row.classList.toggle('unassigned', !it.assigned);
        });
        renderVerify(res.verification);
    }

    function renderVerify(v) {
        const panel = document.getElementById('verifyPanel');
        if (!panel) return;
        const wasOk = panel.classList.contains('ok');
        panel.classList.toggle('ok', v.ok); panel.classList.toggle('bad', !v.ok);
        panel.querySelector('.seal i').className = 'bi ' + (v.ok ? 'bi-patch-check-fill' : 'bi-exclamation-triangle-fill');
        if (v.ok && !wasOk) { const s = panel.querySelector('.seal'); s.style.animation = 'none'; void s.offsetWidth; s.style.animation = ''; }
        panel.querySelector('[data-v="title"]').textContent = v.ok ? 'All allocations add up ✓' : 'Allocations need attention';
        panel.querySelector('[data-v="sub"]').textContent = `Shares total NPR ${C.money(v.sum)} of NPR ${C.money(v.total)}` + (v.difference !== 0 ? ` · off by NPR ${C.money(Math.abs(v.difference))}` : ' · exact to the paisa');
        panel.querySelector('[data-v="checks"]').innerHTML = v.checks.map(c =>
            `<li><i class="bi ${c.ok ? 'bi-check-circle-fill ok' : 'bi-x-circle-fill no'}"></i><span>${esc(c.label)}</span><span class="d">${esc(c.detail)}</span></li>`).join('');
        panel.querySelector('[data-v="notes"]').innerHTML =
            v.errors.map(e => `<div class="small fw-semibold mt-2" style="color:#a3153a"><i class="bi bi-x-octagon me-1"></i>${esc(e)}</div>`).join('') +
            v.warnings.map(w => `<div class="small fw-semibold mt-2" style="color:#8a4b04"><i class="bi bi-info-circle me-1"></i>${esc(w)}</div>`).join('');
    }

    function toast(msg, bad) {
        document.getElementById('blToast')?.remove();
        const t = document.createElement('div');
        t.id = 'blToast'; t.className = 'toast-ww'; t.setAttribute('role', 'status');
        t.innerHTML = `<div class="alert ${bad ? 'alert-danger' : 'alert-success'} shadow d-flex align-items-center gap-2 mb-0"><i class="bi ${bad ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill'}"></i><span class="fw-semibold">${esc(msg)}</span></div>`;
        document.body.appendChild(t);
        setTimeout(() => t.remove(), 3500);
    }

    // ---------- Owner matrix: instant client preview, saved by form submit ----------
    const matrix = document.getElementById('matrixForm');
    if (matrix && D.owner && D.open) {
        let dirty = false;
        const sync = () => {
            input.items.forEach(it => {
                const alloc = {};
                matrix.querySelectorAll(`input[data-cell][data-item="${it.id}"]`).forEach(cb => {
                    const w = matrix.querySelector(`input[data-weight][data-item="${it.id}"][data-p="${cb.dataset.p}"]`);
                    if (w) w.disabled = !cb.checked;
                    if (cb.checked) alloc[cb.dataset.p] = Math.max(1, Math.min(20, parseInt(w ? w.value : 1, 10) || 1));
                });
                it.alloc = alloc;
            });
            render(C.calculate(input));
            if (!dirty) { dirty = true; const h = document.getElementById('matrixHint'); if (h) h.innerHTML = '<i class="bi bi-dot" style="color:var(--pk-warn)"></i><strong>Unsaved changes</strong> — preview only until you save.'; }
        };
        matrix.addEventListener('change', (e) => {
            const cb = e.target.closest('input[data-cell]');
            if (cb && cb.checked && cb.closest('tr').dataset.shared === '0') {
                // Non-shared item: exactly one person — untick the others.
                cb.closest('tr').querySelectorAll('input[data-cell]').forEach(o => { if (o !== cb) o.checked = false; });
            }
            sync();
        });
        matrix.addEventListener('input', (e) => { if (e.target.matches('[data-weight]')) sync(); });
        matrix.addEventListener('submit', () => { dirty = false; const b = document.getElementById('matrixSave'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…'; });
        window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    }

    // ---------- Member: tick own items, saved immediately ----------
    if (matrix && !D.owner && D.open) {
        matrix.addEventListener('change', async (e) => {
            const cb = e.target.closest('input[data-cell]');
            if (!cb || String(cb.dataset.p) !== String(D.me)) return;
            cb.disabled = true;
            try {
                const res = await fetch(D.mineUrl.replace('/items/0/', `/items/${cb.dataset.item}/`), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                    body: JSON.stringify({ on: cb.checked }),
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok) { cb.checked = !cb.checked; toast(j.message || 'Could not update — please try again.', true); }
                else toast(j.message || 'Updated');
                if (j.data && j.data.calculation) render(j.data.calculation);
            } catch (err) {
                cb.checked = !cb.checked; toast('You seem to be offline. Nothing was changed.', true);
            } finally { cb.disabled = false; }
        });
    }

    // ---------- Custom amounts running sum ----------
    const customForm = document.getElementById('customForm');
    if (customForm) {
        const out = document.getElementById('customSum');
        const target = Number(out.dataset.target);
        const upd = () => {
            const sum = [...customForm.querySelectorAll('[data-custom]')].reduce((s, i) => s + C.toPaisa(i.value), 0);
            const diff = target - sum;
            out.innerHTML = `Entered NPR ${C.money(sum)} of ${C.money(target)} ` + (diff === 0
                ? '<span class="bl-pill paid ms-1"><i class="bi bi-check2"></i>Exact</span>'
                : `<span class="bl-pill due ms-1">${diff > 0 ? C.money(diff) + ' left' : C.money(-diff) + ' over'}</span>`);
        };
        customForm.addEventListener('input', upd); upd();
    }

    // ---------- Invite link: copy + QR (generated locally) ----------
    const copy = document.getElementById('copyLink');
    if (copy) copy.addEventListener('click', async () => {
        const inp = document.getElementById('inviteUrl');
        try { await navigator.clipboard.writeText(inp.value); } catch (e) { inp.select(); document.execCommand('copy'); }
        copy.classList.add('copied'); copy.innerHTML = '<i class="bi bi-check2"></i>';
        setTimeout(() => { copy.classList.remove('copied'); copy.innerHTML = '<i class="bi bi-clipboard"></i>'; }, 1800);
    });
    const qrBox = document.getElementById('qrBox');
    if (qrBox && window.qrcode) {
        try {
            const qr = window.qrcode(0, 'M');
            qr.addData(qrBox.dataset.url); qr.make();
            qrBox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true, alt: 'Invite QR code' });
        } catch (e) { qrBox.innerHTML = '<span class="bl-sub text-center">QR unavailable — use the link.</span>'; }
    }
})();
