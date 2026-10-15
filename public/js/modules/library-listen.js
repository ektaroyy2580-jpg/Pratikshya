// E-Library "Listen" mode: an audiobook player built on the browser's own speechSynthesis.
// Fully local (no audio files, nothing uploaded). Sentences are pre-numbered by the server (<span class="rd-s" data-s>),
// so highlighting, skipping and the saved listening place all share one numbering. Progress is saved through
// window.LibReader (library-reader.js) to the user's account: chapter + sentence index, so "continue listening"
// works in any queue, on any device. Comics: speech bubbles and captions are read in panel order.
(() => {
    const root = document.getElementById('reader');
    const barEl = document.getElementById('lsBar');
    const R = window.LibReader;
    if (!root || !barEl || !R) return;

    const $ = id => document.getElementById(id);
    const synth = 'speechSynthesis' in window ? window.speechSynthesis : null;
    const supported = !!(synth && 'SpeechSynthesisUtterance' in window);
    const isComic = root.dataset.kind === 'comic';
    const lang = root.dataset.lang === 'ne' ? 'ne' : 'en';
    const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const store = { get(k, d) { try { return localStorage.getItem(k) ?? d; } catch (e) { return d; } }, set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
    const RATES = [0.75, 1, 1.25, 1.5, 1.75, 2];
    const fmtRate = r => String(r).replace(/^0\./, '0.') + '×';

    const S = {
        open: false, playing: false, index: 0, items: [], hl: null,
        rate: RATES.includes(+store.get('lib.rate', 1)) ? +store.get('lib.rate', 1) : 1,
        voiceURI: store.get('lib.voice.' + lang, ''), voice: null, voices: [],
        follow: true, gen: 0, uttStarted: false, everStarted: false,
        sleepAt: 0, sleepChapter: false, sleepT: null, sleepTick: null, wd: null,
        startIndex: root.dataset.listenStart !== '' ? parseInt(root.dataset.listenStart, 10) : null,
        internalFlip: false, loading: false,
    };

    // ------------------------------------------------------------------ notes
    const noteEl = $('lsNote');
    const note = (msg, tone = '') => { noteEl.textContent = msg; noteEl.className = 'ls-note ' + tone; noteEl.hidden = !msg; };

    // ------------------------------------------------------------------ items (what gets spoken)
    const buildItems = () => {
        if (isComic) {
            const pg = document.querySelector('.cm-page:not([hidden])');
            const out = [];
            pg?.querySelectorAll('.cm-panel').forEach(panel => {
                const cap = panel.querySelector('.cm-cap');
                if (cap && cap.textContent.trim()) out.push(cap);
                panel.querySelectorAll('.cm-b:not(.sfx)').forEach(b => { if (b.textContent.trim()) out.push(b); });
            });
            S.items = out;
        } else {
            S.items = [...document.querySelectorAll('#rdArticle .rd-s')].sort((a, b) => a.dataset.s - b.dataset.s);
        }
        if (S.index >= S.items.length) S.index = Math.max(0, S.items.length - 1);
    };
    const words = el => (el?.textContent.trim().split(/\s+/).filter(Boolean).length || 0);

    // ------------------------------------------------------------------ voices (async in Chrome; Brave may hide them)
    const rank = v => {
        const l = (v.lang || '').toLowerCase().replace('_', '-');
        if (lang === 'ne') return l.startsWith('ne') ? 3 : (l.startsWith('hi') ? 2 : 0);
        return l.startsWith('en') ? 1 : 0;
    };
    const pickVoice = () => {
        const saved = S.voices.find(v => v.voiceURI === S.voiceURI);
        if (saved) return saved;
        return S.voices.filter(v => rank(v) > 0)
            .sort((a, b) => rank(b) - rank(a) || (b.localService - a.localService) || (b.default - a.default))[0] || null;
    };
    const voiceHint = () => {
        const hint = $('lsVoiceHint');
        const l = (S.voice?.lang || '').toLowerCase();
        let msg = '';
        let bar = '';
        if (!supported) {
            msg = bar = 'Read-aloud is not available in this browser.';
        } else if (!S.voices.length) {
            msg = 'Your browser did not share its list of voices (Brave can hide it as fingerprinting protection). Your device’s default voice is used.';
            if (lang === 'ne') bar = 'This item is in Nepali. If it sounds unclear, add a Nepali or Hindi voice in your system speech settings.';
        } else if (lang === 'ne') {
            if (l.startsWith('ne')) msg = 'Using a Nepali voice.';
            else if (l.startsWith('hi')) { msg = 'No Nepali voice on this device, so a Hindi voice reads the Devanagari text. Some words may sound different.'; bar = 'Reading with a Hindi voice — no Nepali voice is installed on this device.'; }
            else { msg = bar = 'No Nepali or Hindi voice on this device, so the text may not be read clearly. Add one in your system’s speech settings (Windows: Settings → Time & language → Speech).'; }
        }
        hint.textContent = msg;
        hint.hidden = !msg;
        if (S.open && bar && !noteEl.dataset.sticky) { note(bar, 'warn'); noteEl.dataset.sticky = '1'; }
    };
    const renderVoices = () => {
        const sel = $('lsVoice');
        const match = S.voices.filter(v => rank(v) > 0);
        const other = S.voices.filter(v => rank(v) === 0);
        sel.innerHTML = '';
        sel.append(new Option('Device default', ''));
        const group = (label, list) => {
            if (!list.length) return;
            const g = document.createElement('optgroup');
            g.label = label;
            list.forEach(v => g.append(new Option(`${v.name} (${v.lang})`, v.voiceURI)));
            sel.append(g);
        };
        group(lang === 'ne' ? 'Nepali / Hindi' : 'English', match);
        group('Other languages', other);
        S.voice = pickVoice();
        sel.value = S.voice ? S.voice.voiceURI : '';
        voiceHint();
    };
    const loadVoices = () => {
        if (!supported) return;
        const list = synth.getVoices() || [];
        if (list.length && list.length === S.voices.length) return;
        S.voices = list;
        renderVoices();
    };
    if (supported) {
        loadVoices();
        if (synth.addEventListener) synth.addEventListener('voiceschanged', loadVoices);
        else synth.onvoiceschanged = loadVoices;
        [250, 1000, 2500].forEach(ms => setTimeout(loadVoices, ms));
    }

    // ------------------------------------------------------------------ highlighting + follow-along
    const highlight = el => {
        if (S.hl === el) return;
        S.hl?.classList.remove('is-reading');
        S.hl = el || null;
        el?.classList.add('is-reading');
    };
    let autoScrolling = false, asT;
    const follow = (el, force = false) => {
        if (!el) return;
        if (!force && (!S.follow || !$('lsAutoScroll').checked)) return;
        const r = el.getBoundingClientRect();
        const top = (document.querySelector('.rd-bar')?.offsetHeight || 0) + 24;
        const bottom = window.innerHeight - barEl.offsetHeight - 24;
        if (r.top >= top && r.bottom <= bottom && !force) return;
        autoScrolling = true;
        clearTimeout(asT);
        asT = setTimeout(() => { autoScrolling = false; }, 900);
        const target = top + (bottom - top) * 0.3; // keep the spoken sentence in the upper third of the visible area
        window.scrollTo({ top: Math.max(0, window.scrollY + r.top - target), behavior: reduced ? 'auto' : 'smooth' });
    };
    const userScrolled = () => {
        if (!S.playing || autoScrolling || !S.follow) return;
        S.follow = false;
        $('lsFollow').hidden = false;
    };
    ['wheel', 'touchmove'].forEach(ev => window.addEventListener(ev, userScrolled, { passive: true }));
    window.addEventListener('keydown', e => { if (['PageDown', 'PageUp', 'Home', 'End', 'ArrowDown', 'ArrowUp'].includes(e.key) && !e.target.closest('input, select, textarea')) userScrolled(); });
    $('lsFollow').addEventListener('click', () => { S.follow = true; $('lsFollow').hidden = true; follow(S.items[S.index], true); });

    // ------------------------------------------------------------------ UI state
    const playBtn = $('lsPlay');
    const render = () => {
        const n = S.items.length;
        const i = Math.min(S.index, Math.max(0, n - 1));
        playBtn.innerHTML = `<i class="bi ${S.playing ? 'bi-pause-fill' : 'bi-play-fill'}"></i>`;
        playBtn.setAttribute('aria-label', S.playing ? 'Pause' : (S.index > 0 ? 'Resume' : 'Play'));
        barEl.classList.toggle('is-playing', S.playing);
        $('lsState').textContent = S.loading ? 'Loading next chapter…' : (S.playing ? 'Playing' : (S.index > 0 ? 'Paused' : (isComic ? 'Read aloud' : 'Audiobook')));
        const unit = isComic ? `Page ${R.page() + 1} of ${R.pageCount()} · ${n ? `bubble ${i + 1} of ${n}` : 'no speech on this page'}`
            : `Sentence ${n ? i + 1 : 0} of ${n} · ${(root.dataset.chapterHeading || '').replace(/\.$/, '').replace(/^Chapter (\d+)\. /, 'Ch. $1 · ')}`;
        $('lsWhere').textContent = unit;
        $('lsTrack').style.width = (n ? (i + (S.playing ? .5 : 0)) / n * 100 : 0) + '%';
        $('lsRate').textContent = fmtRate(S.rate);
        $('lsRate').setAttribute('aria-label', `Playback speed: ${fmtRate(S.rate)}. Click to change.`);
        document.querySelectorAll('#lsMore [data-rate]').forEach(b => b.setAttribute('aria-checked', +b.dataset.rate === S.rate ? 'true' : 'false'));
        const tb = $('rdListenBtn');
        tb?.setAttribute('aria-pressed', S.open ? 'true' : 'false');
        tb?.classList.toggle('on', S.open);
    };

    // ------------------------------------------------------------------ speaking
    const mkUtter = text => {
        const u = new SpeechSynthesisUtterance(text);
        u.rate = S.rate;
        if (S.voice) { u.voice = S.voice; u.lang = S.voice.lang; } else { u.lang = lang === 'ne' ? 'ne-NP' : 'en-GB'; }
        return u;
    };
    const armWatchdog = my => {
        clearTimeout(S.wd);
        S.wd = setTimeout(() => {
            if (my === S.gen && S.playing && !S.uttStarted && !synth.speaking && !S.everStarted) {
                note('No sound? Check your volume. Your device may not have a text-to-speech voice installed, or the browser blocked audio.', 'warn');
            }
        }, 4500);
    };
    const speakIdx = (i, opts = {}) => {
        if (!supported) { note('Read-aloud is not available in this browser.', 'warn'); return; }
        if (!S.items.length) buildItems();
        if (!S.items.length) { endOfUnit(); return; }
        S.index = Math.max(0, Math.min(i, S.items.length - 1));
        S.playing = true;
        S.uttStarted = false;
        if (/\b(info|ok)\b/.test(noteEl.className)) note(''); // "continue where you left off" / "the end" notes go once playing
        const el = S.items[S.index];
        highlight(el);
        follow(el);
        render();
        R.setListen(S.index, S.items.length);

        const my = ++S.gen;
        const wasBusy = synth.speaking || synth.pending;
        synth.cancel();
        const u = mkUtter(el.textContent.replace(/\s+/g, ' ').trim());
        u.onstart = () => { if (my !== S.gen) return; S.uttStarted = S.everStarted = true; S.errors = 0; if (noteEl.classList.contains('warn') && !noteEl.dataset.sticky) note(''); };
        const t0 = performance.now();
        u.onend = () => {
            if (my !== S.gen || !S.playing) return;
            // An engine without a voice for this language may 'finish' instantly without a sound: stop instead of racing ahead.
            S.silent = performance.now() - t0 < 150 && el.textContent.trim().length > 3 ? (S.silent || 0) + 1 : 0;
            if (S.silent >= 3) {
                S.silent = 0;
                S.index = Math.max(0, S.index - 2);
                pause();
                highlight(S.items[S.index]);
                note(lang === 'ne' ? 'Your device has no voice that can read Nepali, so nothing could be spoken. Add a Nepali or Hindi voice in your system speech settings, then press Play.' : 'Your device’s voice did not produce any speech. Try another voice in the player settings.', 'warn');
                return;
            }
            afterSentence();
        };
        u.onerror = e => {
            if (my !== S.gen) return;
            if (e.error === 'interrupted' || e.error === 'canceled') return;
            if (e.error === 'not-allowed') { pause(false); note('Tap Play to start listening — the browser needs a tap before it can speak.', 'info'); return; }
            // Skip one sentence the engine could not speak, but never race through a chapter of failures.
            S.errors = (S.errors || 0) + 1;
            if (S.errors >= 2) { pause(); note('This device could not speak this text (' + e.error + '). Try another voice in the player settings.', 'warn'); return; }
            if (S.playing) afterSentence();
        };
        S.utter = u; // keep a reference: some engines drop events for garbage-collected utterances
        const go = () => {
            if (my !== S.gen) return;
            if (opts.heading) synth.speak(mkUtter(opts.heading));
            synth.speak(u);
        };
        wasBusy ? setTimeout(go, 40) : go();
        armWatchdog(my);
    };
    const afterSentence = () => {
        if (S.sleepAt && Date.now() >= S.sleepAt) { pause(); sleepDone(); return; }
        if (S.index + 1 < S.items.length) speakIdx(S.index + 1);
        else endOfUnit();
    };
    const pause = (save = true) => {
        S.gen++;
        clearTimeout(S.wd);
        if (supported) synth.cancel();
        S.playing = false;
        render();
        if (save) R.saveNow();
    };
    const play = () => {
        if (!supported) { note('Read-aloud is not available in this browser.', 'warn'); return; }
        if (!S.open) openBar();
        loadVoices();
        speakIdx(S.index);
    };

    const endOfUnit = () => {
        if (S.sleepChapter) { pause(); sleepDone(); return; }
        if (isComic && R.page() + 1 < R.pageCount()) { S.internalFlip = true; R.goToPage(R.page() + 1); return; }
        nextChapter();
    };
    const withParams = (url, params) => { const u = new URL(url, location.href); Object.entries(params).forEach(([k, v]) => v === null ? u.searchParams.delete(k) : u.searchParams.set(k, v)); return u.toString(); };
    const finish = () => {
        S.index = Math.max(0, S.items.length - 1);
        R.setListen(S.index, S.items.length, true);
        pause();
        highlight(null);
        note('The end — you finished listening. It is saved to your history.', 'ok');
        document.getElementById('rdFinish')?.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
    };

    // Continue across chapters without a page load (keeps the browser's permission to speak).
    const nextChapter = async () => {
        const url = root.dataset.nextUrl;
        if (!url) { finish(); return; }
        if (isComic) { R.beacon(); location.href = withParams(url, { listen: 1, autoplay: 1 }); return; }
        S.loading = true;
        render();
        try {
            const res = await fetch(withParams(url, { listen: 1 }), { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const nr = doc.getElementById('reader');
            const art = doc.getElementById('rdArticle');
            if (!nr || !art || nr.dataset.kind !== 'text') throw new Error('unexpected page');
            ['chapterId', 'chapterIndex', 'prevUrl', 'nextUrl', 'chapterHeading'].forEach(k => { root.dataset[k] = nr.dataset[k] || ''; });
            $('rdArticle').innerHTML = art.innerHTML;
            const swap = sel => { const a = document.querySelector(sel), b = doc.querySelector(sel); if (a && b) a.innerHTML = b.innerHTML; };
            swap('.rd-title');
            swap('.rd-end');
            swap('#rdToc .lib-chapters');
            document.title = doc.title;
            history.pushState({}, '', withParams(url, { go: null, listen: 1 }));
            window.scrollTo({ top: Math.max(0, $('rdArticle').getBoundingClientRect().top + window.scrollY - 80), behavior: 'auto' });
            S.loading = false;
            S.index = 0;
            S.hl = null;
            buildItems();
            R.chapterSwapped();
            speakIdx(0, { heading: root.dataset.chapterHeading });
        } catch (e) {
            S.loading = false;
            R.beacon();
            location.href = withParams(url, { listen: 1, autoplay: 1 });
        }
    };

    // Comic page turned (by the player or by the reader): read the new page from its first bubble.
    root.addEventListener('rd:page', () => {
        if (!S.open) return;
        const wasPlaying = S.playing;
        S.internalFlip = false;
        S.index = 0;
        highlight(null);
        buildItems();
        if (wasPlaying) speakIdx(0); else render();
    });

    // ------------------------------------------------------------------ skipping (~15 s, by whole sentences)
    const jump = i => {
        if (!S.items.length) buildItems();
        S.index = Math.max(0, Math.min(i, S.items.length - 1));
        if (S.playing) { speakIdx(S.index); return; }
        highlight(S.items[S.index]);
        follow(S.items[S.index], true);
        R.setListen(S.index, S.items.length);
        render();
    };
    const skip = dir => {
        if (!S.items.length) buildItems();
        const target = 15 * 2.5 * S.rate; // words spoken in ~15 s at this speed
        let i = S.index, acc = 0;
        if (dir < 0) { while (i > 0 && acc < target) { i--; acc += words(S.items[i]); } }
        else {
            while (i < S.items.length - 1 && acc < target) { acc += words(S.items[i]); i++; }
            if (i === S.index && S.playing) { endOfUnit(); return; }
        }
        jump(i);
    };

    // ------------------------------------------------------------------ open / close
    const firstVisible = () => {
        const top = (document.querySelector('.rd-bar')?.offsetHeight || 0) + 8;
        const idx = S.items.findIndex(el => el.getBoundingClientRect().bottom > top);
        return idx < 0 ? 0 : idx;
    };
    const openBar = () => {
        if (S.open) return;
        S.open = true;
        barEl.hidden = false;
        root.classList.add('is-listening');
        requestAnimationFrame(() => barEl.classList.add('in'));
        buildItems();
        if (S.startIndex !== null && S.startIndex < S.items.length) {
            S.index = S.startIndex;
            S.startIndex = null;
            note(isComic ? `Continue listening at bubble ${S.index + 1} on this page.` : `Continue listening where you left off — sentence ${S.index + 1} of ${S.items.length}.`, 'info');
        } else {
            S.index = isComic ? 0 : firstVisible();
        }
        highlight(S.items[S.index]);
        follow(S.items[S.index], true);
        voiceHint();
        if (!supported) { playBtn.disabled = true; }
        render();
        playBtn.focus({ preventScroll: true });
    };
    const closeBar = () => {
        pause(false);
        S.open = false;
        barEl.classList.remove('in');
        barEl.hidden = true;
        root.classList.remove('is-listening');
        highlight(null);
        closeMore();
        R.clearListen();
        render();
        $('rdListenBtn')?.focus({ preventScroll: true });
    };

    // ------------------------------------------------------------------ sleep timer
    const sleepDone = () => {
        clearTimeout(S.sleepT); clearInterval(S.sleepTick);
        S.sleepAt = 0; S.sleepChapter = false;
        $('lsSleep').value = '';
        $('lsSleepLeft').hidden = true;
        R.toast('Sleep timer: paused. Your place is saved.');
    };
    $('lsSleep').addEventListener('change', e => {
        clearTimeout(S.sleepT); clearInterval(S.sleepTick);
        S.sleepAt = 0; S.sleepChapter = false;
        const v = e.target.value;
        const left = $('lsSleepLeft');
        left.hidden = !v;
        if (v === 'chapter') { S.sleepChapter = true; left.textContent = isComic ? ' · stops after this page' : ' · stops after this chapter'; return; }
        if (!v) return;
        S.sleepAt = Date.now() + (+v) * 60000;
        const tick = () => { const m = Math.max(0, Math.ceil((S.sleepAt - Date.now()) / 60000)); left.textContent = ` · sleep in ${m} min`; };
        tick();
        S.sleepTick = setInterval(tick, 15000);
        S.sleepT = setTimeout(() => { if (S.playing) pause(); sleepDone(); }, (+v) * 60000);
    });

    // ------------------------------------------------------------------ controls
    const setRate = r => {
        S.rate = r;
        store.set('lib.rate', r);
        render();
        if (S.playing) speakIdx(S.index);
    };
    $('lsRate').addEventListener('click', () => setRate(RATES[(RATES.indexOf(S.rate) + 1) % RATES.length]));
    document.querySelectorAll('#lsMore [data-rate]').forEach(b => b.addEventListener('click', () => setRate(+b.dataset.rate)));
    $('lsVoice').addEventListener('change', e => {
        S.voiceURI = e.target.value;
        store.set('lib.voice.' + lang, S.voiceURI);
        S.voice = S.voices.find(v => v.voiceURI === S.voiceURI) || null;
        delete noteEl.dataset.sticky;
        note('');
        voiceHint();
        if (S.playing) speakIdx(S.index);
    });
    playBtn.addEventListener('click', () => (S.playing ? pause() : play()));
    $('lsBack').addEventListener('click', () => skip(-1));
    $('lsFwd').addEventListener('click', () => skip(1));
    $('lsClose').addEventListener('click', closeBar);
    $('rdListenBtn')?.addEventListener('click', () => (S.open ? closeBar() : openBar()));

    const more = $('lsMore'), moreBtn = $('lsMoreBtn');
    const closeMore = () => { more.hidden = true; moreBtn.setAttribute('aria-expanded', 'false'); };
    moreBtn.addEventListener('click', e => { e.stopPropagation(); const open = more.hidden; more.hidden = !open; moreBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open) $('lsVoice').focus(); });
    more.addEventListener('click', e => e.stopPropagation());
    document.addEventListener('click', closeMore);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !more.hidden) { closeMore(); moreBtn.focus(); } });

    // Tap a sentence (or a bubble) while the player is open to listen from there.
    root.addEventListener('click', e => {
        if (!S.open) return;
        const el = e.target.closest(isComic ? '.cm-b, .cm-cap' : '.rd-s');
        if (!el) return;
        if (!S.items.length) buildItems();
        const i = S.items.indexOf(el);
        if (i >= 0) { S.follow = true; $('lsFollow').hidden = true; jump(i); }
    });

    window.addEventListener('pagehide', () => { if (supported) synth.cancel(); });

    // ------------------------------------------------------------------ boot
    render();
    const params = new URLSearchParams(location.search);
    if (root.dataset.openListen === '1') {
        openBar();
        if (params.get('autoplay') === '1') {
            // Arrived from the previous chapter: try to keep going (some browsers need a tap first).
            setTimeout(() => speakIdx(S.index, { heading: root.dataset.chapterHeading }), 400);
        }
        if (params.has('autoplay')) history.replaceState({}, '', withParams(location.href, { autoplay: null }));
    }
    if (!supported) {
        const tb = $('rdListenBtn');
        if (tb) tb.title = 'Read-aloud is not available in this browser';
    }

    // Small, read-only surface for QA (and for other scripts on the page).
    window.LibListen = {
        state: () => ({
            supported, open: S.open, playing: S.playing, index: S.index, count: S.items.length, rate: S.rate,
            voice: S.voice ? `${S.voice.name} (${S.voice.lang})` : null, voices: S.voices.length, lang,
            highlighted: S.hl ? (S.hl.dataset.s ?? S.items.indexOf(S.hl)) : null, follow: S.follow,
            chapterId: root.dataset.chapterId, started: S.everStarted,
        }),
        open: openBar, close: closeBar, play, pause: () => pause(), skip, jump,
        // Simulates the end of the current utterance (headless browsers can be silent and never fire onend).
        _advance: () => afterSentence(),
    };
})();
