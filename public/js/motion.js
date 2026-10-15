/* Pratikshya motion: small, purposeful interactions. Everything is one-shot and
 * disabled for users who prefer reduced motion. Content is never left hidden. */
(function () {
    'use strict';
    const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Navbar gains a soft shadow once the page scrolls.
    const nav = document.querySelector('.pk-nav');
    if (nav) {
        const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 8);
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }
    if (reduce) return;

    // Ripple from the pointer position on buttons.
    document.addEventListener('pointerdown', e => {
        const btn = e.target.closest('.btn');
        if (!btn || btn.disabled) return;
        const r = btn.getBoundingClientRect(), size = Math.max(r.width, r.height);
        const dot = document.createElement('span');
        dot.className = 'pk-ripple';
        dot.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX - r.left - size / 2}px;top:${e.clientY - r.top - size / 2}px`;
        btn.appendChild(dot);
        setTimeout(() => dot.remove(), 650);
    }, { passive: true });

    // Cards below the fold slide in as they scroll into view (cards already visible are left alone).
    if ('IntersectionObserver' in window) {
        const fold = window.innerHeight;
        const cards = [...document.querySelectorAll('main .pk-card, main .card-ww')]
            .filter(el => !el.closest('.reveal, .fade-up, .modal, .overlay, .dropdown-menu') && !el.classList.contains('reveal') && el.getBoundingClientRect().top > fold);
        const io = new IntersectionObserver(entries => entries.forEach(en => {
            if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
        }), { rootMargin: '0px 0px -6% 0px' });
        cards.forEach((el, i) => { el.classList.add('pk-auto-reveal'); el.style.transitionDelay = (i % 4) * 0.06 + 's'; io.observe(el); });
        setTimeout(() => cards.forEach(el => el.classList.add('in')), 3000); // safety net

        // Count-up for numbers marked data-count (runs once when visible).
        const counters = document.querySelectorAll('[data-count]');
        const cio = new IntersectionObserver(entries => entries.forEach(en => {
            if (!en.isIntersecting) return;
            cio.unobserve(en.target);
            const el = en.target, target = parseFloat(el.dataset.count);
            if (!isFinite(target)) return;
            const start = performance.now(), dur = 900;
            el.classList.add('pk-count');
            const tick = now => {
                const p = Math.min(1, (now - start) / dur), eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(target * eased).toLocaleString();
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        }));
        counters.forEach(el => cio.observe(el));
    }
})();
