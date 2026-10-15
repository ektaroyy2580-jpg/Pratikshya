/* Renders QR codes client-side (vendor/qrcode, MIT) for elements with data-qr="<public url>". */
(() => {
    if (typeof qrcode !== 'function') return;
    document.querySelectorAll('[data-qr]').forEach(el => {
        try {
            const qr = qrcode(0, el.dataset.ecc || 'M');
            qr.addData(el.dataset.qr);
            qr.make();
            el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: Number(el.dataset.margin ?? 2), scalable: true, title: el.dataset.label || 'QR code' });
            el.classList.add('qr-ready');
        } catch (e) {
            el.innerHTML = '<span class="small text-danger">QR unavailable</span>';
        }
    });
    document.querySelectorAll('.js-print').forEach(b => b.addEventListener('click', () => window.print()));
    document.querySelectorAll('.js-copy').forEach(b => b.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(b.dataset.copy); const o = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i> Copied'; setTimeout(() => { b.innerHTML = o; }, 1500); } catch (e) { /* clipboard blocked */ }
    }));
    document.querySelectorAll('form.js-confirm').forEach(f => f.addEventListener('submit', e => {
        if (!window.confirm(f.dataset.confirm || 'Are you sure?')) e.preventDefault();
    }));
})();
