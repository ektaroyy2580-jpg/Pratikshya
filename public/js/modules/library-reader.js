// E-Library reader: restore exact position, debounced auto-save (fetch) + page-hide beacon (CSRF in form data),
// theme/font-size controls, chapter + page navigation (keyboard arrows, swipe for comics), bookmarks.
// Progress is stored per user + book on the server — never per ticket — so it follows the reader to any queue.
(() => {
    const root = document.getElementById('reader');
    if (!root) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const $ = id => document.getElementById(id);
    const isComic = root.dataset.kind === 'comic';
    const rtl = root.dataset.rtl === '1';
    const saveUrl = root.dataset.saveUrl;
    const store = { get(k, d) { try { return localStorage.getItem(k) ?? d; } catch (e) { return d; } }, set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };

    // ---------------------------------------------------------------- toast + save status
    const toastEl = $('rdToast');
    let toastTimer;
    const toast = msg => { toastEl.textContent = msg; toastEl.hidden = false; clearTimeout(toastTimer); toastTimer = setTimeout(() => { toastEl.hidden = true; }, 2600); };
    const saveEl = $('rdSave');
    const setStatus = (state) => {
        const map = { saving: ['bi-arrow-repeat', 'Saving…'], saved: ['bi-cloud-check', 'Saved'], err: ['bi-cloud-slash', 'Offline — will retry'] };
        const [icon, label] = map[state];
        saveEl.className = 'rd-save ' + (state === 'saved' ? '' : state);
        saveEl.innerHTML = `<i class="bi ${icon}"></i><span class="d-none d-md-inline"> ${label}</span>`;
        saveEl.title = label;
    };

    // ---------------------------------------------------------------- position model
    let position = parseFloat(root.dataset.startPosition) || 0;  // % within a text chapter
    let page = parseInt(root.dataset.startPage, 10) || 0;          // comic page index
    let lastSaved = null;
    // Arriving via next/previous chapter inside the reader counts as reading: save the new chapter right away.
    const viaNav = new URLSearchParams(location.search).has('go');
    let ready = false;
    // Listen (audiobook) mode, set by library-listen.js: { index, count } of the sentence being spoken.
    let listen = null;
    const key = () => root.dataset.chapterId + ':' + (isComic ? 'p' + page : position.toFixed(1)) + (listen ? ':L' + listen.index + (listen.done ? 'd' : '') : '');
    const payload = () => {
        const p = { chapter_id: root.dataset.chapterId, position: isComic ? 0 : Math.round(position * 100) / 100, page_index: isComic ? page : null };
        if (listen) {
            p.mode = 'listen';
            p.listen_sentence_index = listen.index;
            // While listening, the place in the chapter is the sentence, not the scroll offset.
            if (!isComic && listen.count) p.position = listen.done ? 100 : Math.round(Math.min(100, listen.index / listen.count * 100) * 100) / 100;
        }
        return p;
    };

    let saveTimer, inflight = false;
    const save = async () => {
        if (!ready || key() === lastSaved || inflight) return;
        inflight = true;
        const k = key();
        setStatus('saving');
        try {
            const res = await fetch(saveUrl, {
                method: 'POST', keepalive: true,
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload()),
            });
            if (!res.ok) throw new Error(res.status);
            const { data } = await res.json();
            lastSaved = k;
            setStatus('saved');
            if (data.completed && !root.dataset.doneToast) { root.dataset.doneToast = 1; toast('Finished! Saved to your history.'); }
        } catch (e) {
            setStatus('err');
        } finally {
            inflight = false;
            if (key() !== lastSaved && saveEl.classList.contains('err') === false) scheduleSave();
        }
    };
    const scheduleSave = (ms = 1200) => { clearTimeout(saveTimer); saveTimer = setTimeout(save, ms); };
    window.addEventListener('online', () => scheduleSave(200));

    // On tab hide / navigation: a beacon survives the page unloading. CSRF travels as the _token field.
    const beacon = () => {
        if (!ready || key() === lastSaved || !navigator.sendBeacon) return;
        const fd = new FormData();
        fd.append('_token', csrf);
        const p = payload();
        fd.append('chapter_id', p.chapter_id);
        fd.append('position', p.position);
        if (p.page_index !== null) fd.append('page_index', p.page_index);
        if (p.mode) { fd.append('mode', p.mode); fd.append('listen_sentence_index', p.listen_sentence_index); }
        if (navigator.sendBeacon(saveUrl, fd)) lastSaved = key();
    };
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'hidden') beacon(); });
    window.addEventListener('pagehide', beacon);

    const bar = $('rdBar');

    // ---------------------------------------------------------------- text chapters
    const article = $('rdArticle');
    const barH = () => (document.querySelector('.rd-bar')?.offsetHeight || 0);
    const metrics = () => {
        const top = article.getBoundingClientRect().top + window.scrollY - barH();
        const span = article.offsetHeight - (window.innerHeight - barH()) + 40;
        return { top, span };
    };
    const measure = () => {
        const { top, span } = metrics();
        position = span <= 0 ? 100 : Math.min(100, Math.max(0, (window.scrollY - top) / span * 100));
        bar.style.width = position + '%';
    };
    const restore = pct => {
        const { top, span } = metrics();
        if (span > 0) window.scrollTo({ top: Math.max(0, top + span * pct / 100), behavior: 'instant' });
    };

    if (!isComic && article) {
        const start = () => {
            if (position > 0.5) { restore(position); toast('Welcome back — continuing where you left off.'); }
            requestAnimationFrame(() => {
                measure();
                ready = true;
                // Opening a chapter is not reading it: only real scrolling / page turns move the saved place.
                lastSaved = viaNav ? null : key();
                if (viaNav) scheduleSave(800);
            });
        };
        (document.fonts?.ready || Promise.resolve()).then(() => requestAnimationFrame(start));
        let ticking = false;
        window.addEventListener('scroll', () => {
            if (ticking || !ready) return;
            ticking = true;
            requestAnimationFrame(() => { measure(); ticking = false; scheduleSave(); });
        }, { passive: true });
        window.addEventListener('resize', () => { if (ready) restore(position); });
    }

    // ---------------------------------------------------------------- comics
    const pages = [...document.querySelectorAll('.cm-page')];
    const dots = [...document.querySelectorAll('.cm-dots [data-goto]')];
    const countEl = $('cmCount');
    const nextBtn = $('cmNext');
    const prevBtn = $('cmPrev');
    // Read live: the Listen player may swap in the next chapter without a page load.
    const urls = { get next() { return root.dataset.nextUrl; }, get prev() { return root.dataset.prevUrl; } };

    const showPage = (i, dir = 1) => {
        if (i < 0 || i >= pages.length) return;
        pages.forEach((p, j) => { p.hidden = j !== i; p.classList.remove('from-left'); });
        // Visual direction: LTR next slides in from the right; RTL next slides in from the left.
        if ((dir > 0) === rtl) pages[i].classList.add('from-left');
        page = i;
        dots.forEach((d, j) => d.setAttribute('aria-current', j === i ? 'true' : 'false'));
        countEl.textContent = `Page ${i + 1} of ${pages.length}`;
        bar.style.width = ((i + 1) / pages.length * 100) + '%';
        prevBtn.disabled = i === 0 && !urls.prev;
        const last = i === pages.length - 1;
        $('cmNextLbl') && ($('cmNextLbl').textContent = last ? (urls.next ? 'Next chapter' : 'Finish') : 'Next');
        if (ready) scheduleSave(500);
        root.dispatchEvent(new CustomEvent('rd:page', { detail: { page: i } }));
    };
    const next = () => {
        if (page < pages.length - 1) { showPage(page + 1, 1); scrollToStage(); }
        else if (urls.next) { beacon(); location.href = urls.next; }
        else { scheduleSave(0); $('rdFinish')?.scrollIntoView({ behavior: 'smooth', block: 'center' }); toast('The end! Saved to your history.'); }
    };
    const prev = () => {
        if (page > 0) { showPage(page - 1, -1); scrollToStage(); }
        else if (urls.prev) { location.href = urls.prev; }
    };
    const scrollToStage = () => {
        const stage = $('cmStage');
        const y = stage.getBoundingClientRect().top + window.scrollY - barH() - 12;
        if (Math.abs(window.scrollY - y) > 40) window.scrollTo({ top: y, behavior: 'smooth' });
    };

    if (isComic && pages.length) {
        showPage(Math.min(page, pages.length - 1));
        ready = true;
        lastSaved = viaNav ? null : key(); // otherwise only page turns move the saved place
        if (viaNav) scheduleSave(800);
        if (page > 0) toast(`Welcome back — page ${page + 1} of ${pages.length}.`);
        nextBtn.addEventListener('click', next);
        prevBtn.addEventListener('click', prev);
        dots.forEach(d => d.addEventListener('click', () => { const i = +d.dataset.goto; showPage(i, i > page ? 1 : -1); }));

        // Swipe (touch + pen). LTR: swipe left = next. RTL (manga): swipe right = next.
        const stage = $('cmStage');
        let sx = null, sy = null;
        stage.addEventListener('pointerdown', e => { if (e.pointerType !== 'mouse') { sx = e.clientX; sy = e.clientY; } });
        stage.addEventListener('pointerup', e => {
            if (sx === null) return;
            const dx = e.clientX - sx, dy = e.clientY - sy;
            sx = sy = null;
            if (Math.abs(dx) < 50 || Math.abs(dy) > Math.abs(dx)) return;
            (dx < 0) !== rtl ? next() : prev();
        });
        stage.addEventListener('pointercancel', () => { sx = sy = null; });
    }

    // ---------------------------------------------------------------- API for the Listen player
    window.LibReader = {
        toast,
        isComic, rtl,
        page: () => page,
        pageCount: () => pages.length,
        goToPage: i => { if (i >= 0 && i < pages.length) { showPage(i, i > page ? 1 : -1); scrollToStage(); return true; } return false; },
        setListen(index, count, done = false) { listen = { index, count, done }; if (ready) scheduleSave(done ? 0 : 1500); },
        clearListen() { if (listen) { listen = null; if (ready) scheduleSave(800); } },
        saveNow: () => { clearTimeout(saveTimer); save(); },
        beacon,
        // The player loaded the next chapter in place: start tracking it as a fresh chapter (saved immediately).
        chapterSwapped() { position = 0; bar.style.width = '0%'; lastSaved = null; if (!isComic && article) measure(); scheduleSave(300); },
    };

    // ---------------------------------------------------------------- keyboard
    document.addEventListener('keydown', e => {
        if (e.altKey || e.ctrlKey || e.metaKey || e.target.closest('input, textarea, select, [contenteditable]')) return;
        if (document.querySelector('.offcanvas.show')) return;
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        const forward = (e.key === 'ArrowRight') !== (isComic && rtl);
        if (isComic) { e.preventDefault(); forward ? next() : prev(); return; }
        const url = forward ? urls.next : urls.prev;
        if (url) { e.preventDefault(); beacon(); location.href = url; }
    });

    // ---------------------------------------------------------------- popovers (settings + bookmark)
    const pops = [['rdSetBtn', 'rdSetPanel'], ['rdBmBtn', 'rdBmPanel']];
    const closePops = except => pops.forEach(([b, p]) => { if (p !== except) { $(p).hidden = true; $(b).setAttribute('aria-expanded', 'false'); } });
    pops.forEach(([b, p]) => {
        $(b).addEventListener('click', e => {
            e.stopPropagation();
            const open = $(p).hidden;
            closePops(p);
            $(p).hidden = !open;
            $(b).setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) $(p).querySelector('input, button')?.focus();
        });
        $(p).addEventListener('click', e => e.stopPropagation());
    });
    document.addEventListener('click', () => closePops());
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePops(); });
    document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closePops()));

    // Settings: theme / font / size (per-device preferences only).
    const html = document.documentElement;
    const syncSettings = () => {
        document.querySelectorAll('[data-theme]').forEach(b => b.setAttribute('aria-checked', b.dataset.theme === html.dataset.rdTheme ? 'true' : 'false'));
        document.querySelectorAll('[data-font]').forEach(b => b.setAttribute('aria-checked', b.dataset.font === html.dataset.rdFont ? 'true' : 'false'));
        const out = $('rdSizeOut');
        if (out) out.textContent = parseInt(getComputedStyle(html).getPropertyValue('--rd-size'), 10) || 19;
        document.querySelector('meta[name="theme-color"]')?.setAttribute('content', html.dataset.rdTheme === 'dark' ? '#12141c' : html.dataset.rdTheme === 'sepia' ? '#f5eddc' : '#ffffff');
    };
    const keepPlace = fn => { const pct = position; fn(); if (!isComic && ready) requestAnimationFrame(() => restore(pct)); };
    document.querySelectorAll('[data-theme]').forEach(b => b.addEventListener('click', () => { html.dataset.rdTheme = b.dataset.theme; store.set('lib.theme', b.dataset.theme); syncSettings(); }));
    document.querySelectorAll('[data-font]').forEach(b => b.addEventListener('click', () => keepPlace(() => { html.dataset.rdFont = b.dataset.font; store.set('lib.font', b.dataset.font); syncSettings(); })));
    const sizes = [15, 17, 19, 21, 23, 26];
    document.querySelectorAll('[data-size]').forEach(b => b.addEventListener('click', () => keepPlace(() => {
        const cur = parseInt(getComputedStyle(html).getPropertyValue('--rd-size'), 10) || 19;
        let i = sizes.indexOf(cur); if (i < 0) i = 2;
        const n = sizes[Math.min(sizes.length - 1, Math.max(0, i + (+b.dataset.size)))];
        html.style.setProperty('--rd-size', n + 'px');
        store.set('lib.size', n);
        syncSettings();
    })));
    syncSettings();

    // Bookmarks
    const bmList = $('rdBmList');
    const addBmRow = (bm) => {
        const row = document.createElement('div');
        row.className = 'rd-bm';
        row.innerHTML = '<a class="min-w-0 flex-grow-1 text-decoration-none"><span class="d-block fw-bold small"></span><span class="d-block small rd-muted text-truncate"></span></a><button type="button" class="btn btn-sm px-2 rd-bm-del"><i class="bi bi-trash3"></i></button>';
        row.querySelector('a').href = bm.url;
        row.querySelector('a span:first-child').textContent = bm.label;
        row.querySelector('a span:last-child').textContent = bm.note || 'No note';
        const del = row.querySelector('button');
        del.dataset.url = bm.delete_url;
        del.setAttribute('aria-label', 'Delete bookmark ' + bm.label);
        bmList.prepend(row);
        $('rdBmEmpty').classList.add('d-none');
    };
    $('rdBmForm').addEventListener('submit', async e => {
        e.preventDefault();
        if (!isComic) measure();
        const btn = e.submitter || e.target.querySelector('.btn-pk');
        btn.disabled = true;
        try {
            const res = await fetch(root.dataset.bookmarkUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ ...payload(), note: $('rdBmNote').value.trim() || null }),
            });
            if (!res.ok) throw new Error(res.status);
            const { data } = await res.json();
            addBmRow(data);
            $('rdBmNote').value = '';
            closePops();
            $('rdBmBtn').innerHTML = '<i class="bi bi-bookmark-check-fill"></i>';
            setTimeout(() => { $('rdBmBtn').innerHTML = '<i class="bi bi-bookmark-plus"></i>'; }, 1800);
            toast('Bookmark saved · ' + data.label);
        } catch (err) {
            toast('Could not save the bookmark. Check your connection and try again.');
        } finally { btn.disabled = false; }
    });
    bmList.addEventListener('click', async e => {
        const del = e.target.closest('.rd-bm-del');
        if (!del) return;
        del.disabled = true;
        try {
            const res = await fetch(del.dataset.url, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } });
            if (!res.ok) throw new Error(res.status);
            del.closest('.rd-bm').remove();
            if (!bmList.children.length) $('rdBmEmpty').classList.remove('d-none');
            toast('Bookmark removed');
        } catch (err) { del.disabled = false; toast('Could not remove the bookmark.'); }
    });

    // ---------------------------------------------------------------- live queue status (read-only)
    const ticketUrl = root.dataset.ticketUrl;
    if (ticketUrl) {
        const chip = $('rdTicket'), st = $('rdTicketState'), alertEl = $('rdCalled');
        let announced = false;
        const poll = async () => {
            if (document.visibilityState === 'hidden') return;
            try {
                const res = await fetch(ticketUrl, { headers: { Accept: 'application/json' } });
                if (!res.ok) return;
                const { data } = await res.json();
                if (!data) return;
                const called = data.status === 'called' || data.status === 'serving';
                chip.classList.toggle('called', called);
                if (st) st.textContent = called ? 'called — go now' : (data.ahead === 0 ? "you're next" : `${data.ahead} ahead`);
                if (called && !announced) {
                    announced = true;
                    $('rdCalledMsg').textContent = data.counter ? `Please go to Counter ${data.counter}. Your page is saved.` : 'Please go to the counter. Your page is saved.';
                    alertEl.hidden = false;
                    scheduleSave(0);
                }
                if (!['waiting', 'called', 'serving'].includes(data.status)) { clearInterval(timer); chip.remove(); }
            } catch (e) { /* offline: keep reading */ }
        };
        const timer = setInterval(poll, 15000);
        poll();
    }
})();
