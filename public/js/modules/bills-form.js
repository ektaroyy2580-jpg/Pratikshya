/* Bill create/edit page: items editor + live preview (mirrors the server engine via BillCalc). */
(function () {
    'use strict';
    const C = window.BillCalc;
    const form = document.getElementById('billForm');
    if (!form || !C) return;

    const rowsEl = document.getElementById('itemRows');
    const tpl = document.getElementById('itemTpl');
    const $ = (id) => document.getElementById(id);
    let idx = rowsEl.querySelectorAll('[data-row]').length + 100;
    let people = Math.max(1, parseInt($('pplCount').textContent, 10) || 1);

    const val = (name) => (form.querySelector(`[name="${name}"]:checked`) || form.querySelector(`[name="${name}"]`) || {}).value;

    function addRow(name = '', price = '', focus = true) {
        const html = tpl.innerHTML.replaceAll('__i__', String(idx++));
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        const row = wrap.firstElementChild;
        rowsEl.appendChild(row);
        if (name) row.querySelector('.name').value = name;
        if (price !== '') row.querySelector('[name$="[unit_price]"]').value = price;
        if (focus) (name ? row.querySelector('.qty') : row.querySelector('.name')).focus();
        recalc();
    }

    rowsEl.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-remove]');
        if (!btn) return;
        const row = btn.closest('[data-row]');
        if (rowsEl.querySelectorAll('[data-row]').length === 1) {
            row.querySelectorAll('input:not([type=hidden]):not([type=checkbox])').forEach(i => { i.value = i.classList.contains('qty') ? 1 : ''; });
            recalc();
            return;
        }
        row.classList.add('removing');
        setTimeout(() => { row.remove(); recalc(); }, 230);
    });
    $('addItem').addEventListener('click', () => addRow());
    document.querySelectorAll('[data-quick]').forEach(b => b.addEventListener('click', () => {
        // Fill an empty row first, otherwise append.
        const empty = [...rowsEl.querySelectorAll('[data-row]')].find(r => !r.querySelector('.name').value.trim());
        if (empty) {
            empty.querySelector('.name').value = b.dataset.quick;
            empty.querySelector('[name$="[unit_price]"]').value = b.dataset.price;
            recalc();
        } else addRow(b.dataset.quick, b.dataset.price, false);
    }));
    $('pplMinus').addEventListener('click', () => { people = Math.max(1, people - 1); $('pplCount').textContent = people; recalc(); });
    $('pplPlus').addEventListener('click', () => { people = Math.min(30, people + 1); $('pplCount').textContent = people; recalc(); });

    const helps = {
        equal: 'Everyone pays the same. Any leftover paisa goes to the first people so shares add up exactly.',
        itemized: 'After friends join, tick who had what. Shared items can be split by weight (e.g. 2 portions vs 1).',
        custom: 'You type each person\'s amount. The bill can only be settled when they add up to the total exactly.',
    };

    function recalc() {
        const rows = [...rowsEl.querySelectorAll('[data-row]')];
        $('itemCount').textContent = rows.filter(r => r.querySelector('.name').value.trim()).length;
        const items = rows.map((r, i) => ({
            id: i + 1, name: r.querySelector('.name').value || 'Item',
            unit: C.toPaisa(r.querySelector('[name$="[unit_price]"]').value),
            qty: Math.max(0, Math.min(99, parseInt(r.querySelector('.qty').value, 10) || 0)),
            shared: r.querySelector('input[type=checkbox]').checked, alloc: {},
        }));
        const dType = val('discount_type') || 'none';
        const split = val('split_mode') || 'equal';
        const tipMode = val('tip_allocation') || 'equal';
        $('discSuffix').textContent = dType === 'percent' ? '%' : 'NPR';
        $('discount_value').disabled = dType === 'none';
        const participants = Array.from({ length: people }, (_, i) => ({ id: i + 1, name: 'P' + (i + 1), custom: null, custom_tip: null }));
        const r = C.calculate({
            split_mode: 'equal', // preview: equal split (assignments come after people join)
            tip_allocation: tipMode === 'custom' ? 'equal' : tipMode,
            discount_type: dType,
            discount_value: C.toPaisa($('discount_value').value), // paisa for amount; basis points for percent (same ×100)
            service_bp: C.toPaisa($('service_charge_percent').value),
            vat_bp: C.toPaisa($('vat_percent').value),
            vat_on_service: $('vat_on_service_charge').checked,
            tip: C.toPaisa($('tip_amount').value),
            participants, items,
        });
        const t = r.totals;
        C.tick($('pvGrand'), t.grand_total);
        C.tick($('pvGrand2'), t.grand_total);
        C.tick($('pvSubtotal'), t.subtotal);
        C.tick($('pvDiscount'), t.discount, t.discount ? '−' : '');
        C.tick($('pvService'), t.service);
        C.tick($('pvVat'), t.vat);
        C.tick($('pvTip'), t.tip);
        $('pvScPct').textContent = '(' + ($('service_charge_percent').value || 0) + '%)';
        $('pvVatPct').textContent = '(' + ($('vat_percent').value || 0) + '%' + ($('vat_on_service_charge').checked ? ' incl. SC' : '') + ')';
        $('pvDiscRow').style.display = t.discount ? '' : 'none';
        const totals = Object.values(r.people).map(p => p.total);
        const max = Math.max(...totals), min = Math.min(...totals);
        C.tick($('pvEach'), min);
        $('pvSpread').textContent = max !== min
            ? `${totals.filter(x => x === max).length} of ${people} pay NPR ${C.money(max)} so the total is exact.`
            : (people > 1 ? 'Splits perfectly — no rounding needed.' : 'Add people to preview the split.');
        $('splitHelp').textContent = helps[split] || '';
    }

    form.addEventListener('input', recalc);
    form.addEventListener('change', recalc);
    form.addEventListener('submit', () => { const b = $('saveBtn'); b.disabled = true; b.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…'; });
    recalc();
})();
