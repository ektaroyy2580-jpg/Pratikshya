/*
 * Pratikshya bills — client-side mirror of App\Services\Bills\BillCalculator.
 * Used ONLY for live previews while editing; the server recalculates everything and is the source of truth.
 * Integer paisa with BigInt (no floats for money), largest-remainder rounding, identical calculation order.
 */
(function (global) {
    'use strict';

    const B = (v) => BigInt(v);
    const int = (v) => { const n = Math.trunc(Number(v)); return Number.isFinite(n) ? n : 0; };

    function toPaisa(v) {
        const s = String(v ?? '0').replace(/[,\s]/g, '');
        const m = /^(\d+)(?:\.(\d+))?$/.exec(s);
        if (!m) return 0;
        const frac = ((m[2] || '') + '00').slice(0, 2);
        return Number(B(m[1]) * 100n + B(frac));
    }

    function pct(amount, bp) {
        return Number((B(amount) * B(bp) + 5000n) / 10000n);
    }

    /** weights: array of [key, weight]; returns Map key -> paisa summing exactly to total. */
    function largestRemainder(total, weights) {
        const out = new Map();
        if (!weights.length) return out;
        let W = weights.reduce((s, [, w]) => s + Math.max(0, int(w)), 0);
        let ws = weights.map(([k, w]) => [k, Math.max(0, int(w))]);
        if (W === 0) { ws = ws.map(([k]) => [k, 1]); W = ws.length; }
        const T = B(total), BW = B(W);
        const rem = [];
        let sum = 0;
        ws.forEach(([k, w], i) => {
            const prod = T * B(w);
            const q = Number(prod / BW);
            out.set(k, q); sum += q;
            rem.push([k, prod % BW, i]);
        });
        let left = total - sum;
        rem.sort((a, b) => (a[1] === b[1] ? a[2] - b[2] : (b[1] > a[1] ? 1 : -1)));
        for (let i = 0; left > 0; i = (i + 1) % rem.length, left--) out.set(rem[i][0], out.get(rem[i][0]) + 1);
        return out;
    }

    function money(p) {
        const neg = p < 0; p = Math.abs(p);
        const whole = Math.floor(p / 100).toLocaleString('en-US');
        return (neg ? '-' : '') + whole + '.' + String(p % 100).padStart(2, '0');
    }

    /** Same input shape as BillCalculator::calculate() (camel or snake keys). */
    function calculate(input) {
        const split = input.split_mode || 'equal';
        const tipMode = input.tip_allocation || 'equal';
        const people = input.participants || [];
        const pids = people.map(p => String(p.id));
        const errors = [], warnings = [];

        const items = (input.items || []).map(it => ({ ...it, total: Math.max(0, int(it.unit)) * Math.max(0, int(it.qty)) }));
        const subtotal = items.reduce((s, i) => s + i.total, 0);
        let discount = 0;
        if (input.discount_type === 'amount') discount = Math.min(Math.max(0, int(input.discount_value)), subtotal);
        else if (input.discount_type === 'percent') discount = Math.min(pct(subtotal, Math.min(10000, Math.max(0, int(input.discount_value)))), subtotal);
        const net = subtotal - discount;
        const service = pct(net, Math.max(0, int(input.service_bp)));
        const vatOnService = input.vat_on_service !== false;
        const vatBase = net + (vatOnService ? service : 0);
        const vat = pct(vatBase, Math.max(0, int(input.vat_bp)));
        const preTip = net + service + vat;
        const tip = Math.max(0, int(input.tip));
        const grand = preTip + tip;
        const totals = { subtotal, discount, net, service, vat, tip, vat_base: vatBase, pre_tip: preTip, grand_total: grand };

        const res = { totals, items: {}, people: {}, split_mode: split, tip_allocation: tipMode };
        const rows = {};
        people.forEach(p => { rows[p.id] = { id: p.id, name: p.name, items: 0, discount: 0, service: 0, vat: 0, tip: 0, total: 0, lines: [] }; });

        if (!pids.length) {
            errors.push('No participants have joined yet.');
            res.verification = verify(totals, rows, errors, warnings, []);
            return res;
        }
        const ones = pids.map(k => [k, 1]);
        const base = Object.fromEntries(pids.map(k => [k, 0]));
        let unassigned = 0;
        items.forEach(it => {
            const alloc = Object.entries(it.alloc || {}).filter(([k, w]) => pids.includes(String(k)) && w > 0);
            const ordered = pids.filter(k => alloc.some(([a]) => String(a) === k)).map(k => [k, alloc.find(([a]) => String(a) === k)[1] | 0]);
            const weights = split === 'itemized' && ordered.length ? ordered : ones;
            if (split === 'itemized' && !ordered.length && it.total > 0) unassigned++;
            if (split === 'itemized' && !it.shared && ordered.length > 1) warnings.push('"' + it.name + '" is not marked shared but has ' + ordered.length + ' people assigned.');
            const shares = split === 'itemized' ? largestRemainder(it.total, weights) : new Map();
            const W = weights.reduce((s, [, w]) => s + w, 0);
            shares.forEach((s, k) => {
                base[k] += s;
                if (s > 0) rows[k].lines.push({ item_id: it.id, name: it.name, amount: s, weight: (weights.find(([a]) => a === k) || [0, 0])[1], of: W });
            });
            res.items[it.id] = { total: it.total, assigned: split !== 'itemized' || ordered.length > 0, shares: Object.fromEntries(shares) };
        });
        if (split !== 'itemized') largestRemainder(subtotal, ones).forEach((v, k) => { base[k] = v; });
        if (unassigned) warnings.push(unassigned + (unassigned > 1 ? ' items are' : ' item is') + ' not assigned to anyone yet — split equally until assigned.');

        if (split === 'custom') {
            let sum = 0, missing = 0;
            people.forEach(p => {
                let amt = p.custom;
                if (amt === null || amt === undefined) { missing++; amt = 0; }
                rows[p.id].items = base[String(p.id)];
                rows[p.id].total = Math.max(0, int(amt));
                sum += Math.max(0, int(amt));
            });
            if (missing) warnings.push(missing + (missing > 1 ? ' participants have' : ' participant has') + ' no custom amount yet (counted as 0).');
            const diff = grand - sum;
            if (diff !== 0) errors.push('Custom amounts must add up to exactly NPR ' + money(grand) + ' (difference NPR ' + money(Math.abs(diff)) + ').');
            res.people = rows;
            res.verification = verify(totals, rows, errors, warnings, [{ label: 'Custom amounts add up to the bill total', ok: diff === 0, detail: diff === 0 ? 'Exact match' : (diff > 0 ? money(diff) + ' still unassigned' : money(-diff) + ' over the total') }]);
            return res;
        }

        const baseSum = pids.reduce((s, k) => s + base[k], 0);
        const w = split === 'equal' ? ones : (baseSum > 0 ? pids.map(k => [k, base[k]]) : ones);
        const tipInChain = split === 'equal' ? tipMode !== 'custom' : tipMode === 'proportional';
        const a1 = largestRemainder(net, w), a2 = largestRemainder(net + service, w), a3 = largestRemainder(preTip, w);
        const a4 = tipInChain ? largestRemainder(grand, w) : null;
        let tipShares = new Map(pids.map(k => [k, 0]));
        if (!tipInChain) {
            if (tipMode === 'custom') {
                let st = 0;
                people.forEach(p => { const v = Math.max(0, int(p.custom_tip)); tipShares.set(String(p.id), v); st += v; });
                if (st !== tip) errors.push('Custom tip shares must add up to exactly NPR ' + money(tip) + ' (currently NPR ' + money(st) + ').');
            } else tipShares = largestRemainder(tip, ones);
        }
        pids.forEach(k => {
            const r = rows[k];
            r.items = base[k];
            r.discount = base[k] - a1.get(k);
            r.service = a2.get(k) - a1.get(k);
            r.vat = a3.get(k) - a2.get(k);
            r.tip = tipInChain ? a4.get(k) - a3.get(k) : tipShares.get(k);
            r.total = a3.get(k) + r.tip;
        });
        res.people = rows;
        const col = key => Object.values(rows).reduce((s, r) => s + r[key], 0);
        res.verification = verify(totals, rows, errors, warnings, [
            { label: 'Item shares add up to the subtotal', ok: col('items') === subtotal, detail: money(col('items')) + ' of ' + money(subtotal) },
            { label: 'Discount fully allocated', ok: col('discount') === discount, detail: money(col('discount')) + ' of ' + money(discount) },
            { label: 'Service charge fully allocated', ok: col('service') === service, detail: money(col('service')) + ' of ' + money(service) },
            { label: 'VAT fully allocated', ok: col('vat') === vat, detail: money(col('vat')) + ' of ' + money(vat) },
            { label: 'Tip fully allocated', ok: col('tip') === tip, detail: money(col('tip')) + ' of ' + money(tip) },
        ]);
        return res;
    }

    function verify(totals, rows, errors, warnings, checks) {
        const list = Object.values(rows);
        const sum = list.reduce((s, r) => s + r.total, 0);
        const diff = totals.grand_total - sum;
        checks.unshift({ label: "Everyone's shares add up to the bill total", ok: list.length > 0 && diff === 0, detail: money(sum) + ' of ' + money(totals.grand_total) });
        return { ok: !errors.length && checks.every(c => c.ok), sum, total: totals.grand_total, difference: diff, checks, errors: [...new Set(errors)], warnings: [...new Set(warnings)] };
    }

    /** Smoothly ticks a number element from its current value to `paisa` (one-shot, ~450ms). */
    function tick(el, paisa, prefix) {
        if (!el) return;
        const from = Number(el.dataset.paisa || 0);
        el.dataset.paisa = paisa;
        const pre = prefix ?? (el.dataset.prefix || '');
        if (from === paisa || matchMedia('(prefers-reduced-motion: reduce)').matches) { el.textContent = pre + money(paisa); return; }
        const t0 = performance.now(), dur = 450;
        el.classList.remove('bl-bump'); void el.offsetWidth; el.classList.add('bl-bump');
        const step = (t) => {
            const k = Math.min(1, (t - t0) / dur), e = 1 - Math.pow(1 - k, 3);
            el.textContent = pre + money(Math.round(from + (paisa - from) * e));
            if (k < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

    global.BillCalc = { calculate, largestRemainder, toPaisa, pct, money, tick };
})(window);
