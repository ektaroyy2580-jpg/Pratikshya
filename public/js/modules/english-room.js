/* English practice room (module: english).
 * Speech → text happens in the browser (english-speech.js): on-device Vosk (offline) or the
 * browser's Web Speech API. Only the final, editable transcript text is sent.
 * Nothing here records, stores or uploads audio. */
(() => {
    const B = window.EN_ROOM;
    if (!B) return;
    const $ = id => document.getElementById(id);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const thread = $('thread'), form = $('composer'), answer = $('answer'), sendBtn = $('sendBtn');
    const micBtn = $('micBtn'), micStatus = $('micStatus'), errBox = $('answerError');
    const Speech = window.PkSpeech;
    const synth = 'speechSynthesis' in window ? window.speechSynthesis : null;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let mode = B.mode, consent = B.consent, answered = B.answered, planned = B.planned, next = B.next;
    let promptShownAt = Date.now(), voiceStart = null, voiceEnd = null, usedVoice = false, usedEngine = null, busy = false;
    let session = null, micState = 'idle', baseText = '';
    const ENGINE_LABEL = { ondevice: 'On-device transcript', browser: 'Browser transcript' };

    // ---------- tiny DOM helpers (textContent only → no HTML injection) ----------
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
    const icon = name => el('i', 'bi ' + name);
    const scrollDown = () => {
        thread.scrollTo({ top: thread.scrollHeight, behavior: reduceMotion ? 'auto' : 'smooth' });
        if (window.innerWidth < 992) form.scrollIntoView({ block: 'end', behavior: reduceMotion ? 'auto' : 'smooth' });
    };

    // ---------- messages ----------
    function tutorMsg(p) {
        const m = el('div', 'msg tutor');
        const av = el('div', 'av'); av.append(icon('bi-person-workspace')); av.setAttribute('aria-hidden', 'true');
        const b = el('div', 'bubble');
        b.append(el('span', null, p.prompt));
        if (synth) {
            const sp = el('button', 'speak-btn ms-1'); sp.type = 'button'; sp.setAttribute('aria-label', 'Read this prompt aloud');
            sp.append(icon('bi-volume-up')); sp.addEventListener('click', () => speak(p.target || p.prompt));
            b.append(sp);
        }
        if (p.target) {
            const t = el('span', 'target'); t.append(icon('bi-quote'), document.createTextNode(' ' + p.target)); b.append(t);
        }
        if (p.hint) { const h = el('span', 'hint'); h.append(icon('bi-lightbulb'), document.createTextNode(' ' + p.hint)); b.append(h); }
        m.append(av, b);
        thread.append(m);
        return m;
    }

    function meMsg(text, inputMode, engine) {
        const m = el('div', 'msg me');
        const av = el('div', 'av'); av.append(icon(inputMode === 'voice' ? 'bi-mic' : 'bi-person')); av.setAttribute('aria-hidden', 'true');
        const b = el('div', 'bubble');
        b.append(el('span', null, text));
        b.append(el('span', 'meta', inputMode === 'voice' ? (ENGINE_LABEL[engine] || 'Speech transcript') : 'Typed'));
        m.append(av, b);
        thread.append(m);
        return m;
    }

    function typing() {
        const m = el('div', 'msg tutor'); m.id = 'typing';
        const av = el('div', 'av'); av.append(icon('bi-person-workspace'));
        const b = el('div', 'bubble typing'); b.setAttribute('aria-label', 'Coach is preparing feedback');
        b.append(el('span'), el('span'), el('span'));
        m.append(av, b); thread.append(m); scrollDown();
        return m;
    }

    function list(title, items, render) {
        if (!items || !items.length) return null;
        const w = el('div'); w.append(el('h6', null, title));
        items.forEach(i => w.append(render(i)));
        return w;
    }

    function fbCard(fb, open) {
        if (!fb) return;
        const hasCorr = (fb.corrections || []).length > 0;
        const card = el('div', 'fb-card');
        const top = el('div', 'fb-top');
        const ic = el('span', 'pk-icon ' + (fb.pronunciation ? 'violet' : hasCorr ? 'amber' : 'green'));
        ic.append(icon(fb.pronunciation ? 'bi-soundwave' : hasCorr ? 'bi-pencil' : 'bi-check2'));
        const mid = el('div', 'flex-grow-1 min-w-0');
        mid.append(el('div', 'fb-src', fb.source === 'ai' ? 'Tutor feedback · AI provider' : 'Coach feedback · rule-based'));
        mid.append(el('div', 'fb-sum', fb.summary || ''));
        const tg = el('button', 'fb-toggle mt-1'); tg.type = 'button';
        mid.append(tg);
        top.append(ic, mid);

        const body = el('div', 'fb-body');
        const p = fb.pronunciation;
        if (p) {
            const w = el('div');
            w.append(el('h6', null, 'The speech recogniser heard'));
            const heard = el('div', 'heard');
            const targetSet = new Set((p.target || '').toLowerCase().replace(/[^a-z' ]+/g, ' ').split(/\s+/).filter(Boolean));
            (p.heard || '').split(/\s+/).forEach((word, i) => {
                const clean = word.toLowerCase().replace(/[^a-z']+/g, '');
                if (i) heard.append(document.createTextNode(' '));
                heard.append(targetSet.has(clean) ? el('span', 'w-ok', word) : document.createTextNode(word));
            });
            w.append(heard);
            const row = el('div', 'd-flex align-items-center gap-2 mt-2');
            const bar = el('div', 'progress match-bar flex-grow-1'); const fill = el('div', 'progress-bar' + (p.match_pct === 100 ? ' bg-success' : ''));
            fill.style.width = p.match_pct + '%'; bar.append(fill);
            row.append(bar, el('span', 'small fw-bold text-nowrap', p.matched_words + '/' + p.total_words + ' words matched'));
            w.append(row);
            w.append(el('div', 'small mt-2', p.hint));
            if (p.missed && p.missed.length) {
                const mw = el('div', 'd-flex flex-wrap gap-1 mt-2'); mw.append(el('span', 'small pk-muted me-1', 'Not recognised:'));
                p.missed.forEach(x => mw.append(el('span', 'w-miss small fw-bold', x))); w.append(mw);
            }
            (p.tips || []).forEach(t => { const d = el('div', 'small mt-2'); d.append(icon('bi-lightbulb text-warning'), document.createTextNode(' ' + t)); w.append(d); });
            w.append(el('div', 'small pk-muted mt-2 fst-italic', p.disclaimer));
            body.append(w);
        }
        const corr = list('Corrections', fb.corrections, c => {
            const d = el('div', 'corr');
            d.append(el('span', 'from', c.original), document.createTextNode('  →  '), el('span', 'to', c.suggestion));
            d.append(el('span', 'why', c.explanation));
            return d;
        });
        if (corr) body.append(corr);
        const voc = list('Vocabulary', fb.vocabulary, v => { const d = el('div', 'mb-1'); d.append(icon('bi-journal-text me-1 text-primary'), el('b', null, v.suggestion)); if (v.note) d.append(document.createTextNode(' — ' + v.note)); return d; });
        if (voc) body.append(voc);
        const flu = list('Fluency', fb.fluency, f => { const d = el('div', 'mb-1'); d.append(icon('bi-wind me-1 text-primary'), document.createTextNode(' ' + f)); return d; });
        if (flu) body.append(flu);

        const m = fb.metrics || {};
        const mw = el('div');
        mw.append(el('h6', null, 'Indicators (heuristic, not grades)'));
        const row = el('div', 'metric-row');
        const chip = (ic2, text) => { const c = el('span', 'metric'); c.append(icon(ic2), document.createTextNode(' ' + text)); row.append(c); };
        chip('bi-fonts', (m.word_count || 0) + ' words');
        chip('bi-chat-left-dots', (m.filler_count || 0) + ' filler word' + (m.filler_count === 1 ? '' : 's'));
        if (m.speaking_pace_wpm) chip('bi-speedometer2', 'Speaking pace ~' + m.speaking_pace_wpm + ' wpm');
        if (m.type_token_ratio != null) chip('bi-shuffle', 'Word variety ' + Math.round(m.type_token_ratio * 100) + '%');
        if (m.punctuation_available) chip('bi-text-paragraph', '~' + m.avg_sentence_length + ' words/sentence');
        if ((m.target_vocab_used || []).length) chip('bi-bullseye', 'Target words: ' + m.target_vocab_used.join(', '));
        mw.append(row);
        body.append(mw);

        if (fb.follow_up) {
            const f = el('div', 'followup'); f.append(icon('bi-arrow-return-right me-1'), document.createTextNode(' Extra practice: ' + fb.follow_up)); body.append(f);
        }

        const setOpen = o => { body.hidden = !o; tg.textContent = o ? 'Hide details' : 'Show details'; tg.setAttribute('aria-expanded', o); };
        tg.addEventListener('click', () => setOpen(body.hidden));
        setOpen(!!open);
        card.append(top, body);
        thread.append(card);
        markVocab(m.target_vocab_used || []);
        return card;
    }

    function doneCard() {
        const d = el('div', 'done-card');
        d.append(el('div', 'h5 mb-1', 'Great work — all prompts answered!'));
        d.append(el('p', 'small pk-muted mb-3', 'Finish to see your summary, strengths and what to practise next.'));
        const b = el('button', 'btn btn-pk'); b.type = 'button'; b.append(icon('bi-flag-fill me-1'), document.createTextNode(' Finish & see summary'));
        b.addEventListener('click', () => $('finishForm').requestSubmit());
        d.append(b);
        thread.append(d);
        setComposer(false, 'All prompts answered — finish to see your summary.');
    }

    function markVocab(used) {
        used.forEach(w => document.querySelectorAll('.vocab-chip').forEach(c => { if (c.dataset.word === String(w).toLowerCase()) c.classList.add('used'); }));
    }

    function setProgress() {
        const pct = planned ? Math.round(answered / planned * 100) : 0;
        $('progBar').style.width = pct + '%';
        $('progText').textContent = answered + ' / ' + planned;
        $('progWrap').setAttribute('aria-valuenow', answered);
    }

    function setComposer(enabled, placeholder) {
        if (!enabled && session) cancelListening(true);
        answer.disabled = !enabled; sendBtn.disabled = !enabled; micBtn.disabled = !enabled;
        if (placeholder) answer.placeholder = placeholder;
    }

    // ---------- initial render ----------
    B.turns.forEach((t, i) => {
        tutorMsg({ prompt: t.prompt });
        meMsg(t.user_text, t.input_mode, t.stt_engine);
        fbCard(t.feedback, i === B.turns.length - 1);
    });
    if (next) {
        tutorMsg(next);
    } else {
        doneCard();
    }
    setProgress();
    requestAnimationFrame(() => { thread.scrollTop = thread.scrollHeight; });

    // ---------- read aloud (speechSynthesis) ----------
    const readToggle = $('readAloud');
    let readAloud = false;
    try { readAloud = localStorage.getItem('en.readAloud') === '1'; } catch (e) {}
    if (!synth) {
        readToggle.disabled = true;
        readToggle.nextElementSibling.append(document.createTextNode(' (not supported)'));
    } else {
        readToggle.checked = readAloud;
        readToggle.addEventListener('change', () => {
            readAloud = readToggle.checked;
            try { localStorage.setItem('en.readAloud', readAloud ? '1' : '0'); } catch (e) {}
            if (readAloud && next) speak(next.target || next.prompt); else synth.cancel();
        });
    }
    function speak(text) {
        if (!synth || !text) return;
        synth.cancel();
        const u = new SpeechSynthesisUtterance(text);
        u.lang = 'en-US'; u.rate = 0.95;
        synth.speak(u);
    }

    // ---------- mode switching ----------
    const modeBtns = { text: $('modeText'), voice: $('modeVoice') };
    const consentModalEl = $('consentModal');
    const consentModal = window.bootstrap ? new bootstrap.Modal(consentModalEl) : null;
    const sup = Speech ? Speech.support : { native: false, ondevice: false, mic: false };
    const canVoice = sup.ondevice || sup.native;
    if (!canVoice) {
        $('compatBanner').classList.remove('d-none'); $('compatBanner').classList.add('d-flex');
        modeBtns.voice.disabled = true; modeBtns.voice.title = 'Speech recognition is not supported in this browser';
        if (mode === 'voice') mode = 'text';
    }
    function paintMode() {
        Object.entries(modeBtns).forEach(([k, b]) => { b.classList.toggle('active', k === mode); b.setAttribute('aria-pressed', k === mode); });
        micBtn.classList.toggle('d-none', mode !== 'voice');
        $('voiceBar').classList.toggle('d-none', mode !== 'voice');
        answer.placeholder = mode === 'voice' ? 'Speak, or type your answer…' : 'Type your answer in English…';
        if (mode !== 'voice') { cancelListening(true); setMicStatus(''); hideHelp(); return; }
        if (!session) setState('idle', idleHint());
        preloadModel();
    }
    async function saveMode(m) {
        try {
            const r = await fetch(B.urls.mode, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ mode: m, consent_audio: m === 'voice' ? true : undefined }) });
            if (!r.ok) throw new Error();
            const j = await r.json();
            mode = j.data.mode; consent = j.data.consent;
        } catch (e) {
            showError('Could not switch mode — check your connection.');
        }
        paintMode();
    }
    modeBtns.text.addEventListener('click', () => { if (mode !== 'text') saveMode('text'); });
    modeBtns.voice.addEventListener('click', () => {
        if (!canVoice || mode === 'voice') return;
        if (!consent) { $('consentCheck').checked = false; $('consentOk').disabled = true; consentModal?.show(); return; }
        saveMode('voice');
    });
    $('consentCheck').addEventListener('change', e => { $('consentOk').disabled = !e.target.checked; });
    $('consentOk').addEventListener('click', () => { consentModal?.hide(); saveMode('voice'); });

    // ---------- speech engine choice ----------
    const store = {
        get: k => { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: (k, v) => { try { localStorage.setItem(k, v); } catch (e) {} },
    };
    const ENGINE_KEY = 'en.stt.engine', BROKEN_KEY = 'en.stt.nativeBroken';
    const engBtns = { ondevice: $('engOnDevice'), browser: $('engBrowser') };
    let engine = store.get(ENGINE_KEY);
    if (!(engine === 'ondevice' && sup.ondevice) && !(engine === 'browser' && sup.native)) {
        // Prefer the browser's own engine where it really works (Chrome/Edge/Safari); Brave and
        // Firefox have no working speech service, so they start on-device.
        engine = sup.native && !navigator.brave && store.get(BROKEN_KEY) !== '1' ? 'browser' : (sup.ondevice ? 'ondevice' : 'browser');
    }
    if (!sup.native) { engBtns.browser.disabled = true; engBtns.browser.title = 'This browser has no built-in speech recognition'; }
    if (!sup.ondevice) { engBtns.ondevice.disabled = true; engBtns.ondevice.title = 'This browser cannot run the offline recogniser'; }
    function paintEngine() {
        Object.entries(engBtns).forEach(([k, b]) => { b.classList.toggle('active', k === engine); b.setAttribute('aria-pressed', k === engine); });
        $('engineNote').textContent = engine === 'ondevice'
            ? 'Recognised on your device; audio isn’t stored or uploaded.'
            : 'Your browser’s speech service converts the audio (Chrome and Edge use their maker’s servers). Pratikshya never receives audio.';
    }
    Object.entries(engBtns).forEach(([k, b]) => b.addEventListener('click', () => {
        if (session || engine === k) return;
        engine = k; store.set(ENGINE_KEY, k);
        if (k === 'browser') store.set(BROKEN_KEY, '0');
        paintEngine(); hideHelp();
        setState('idle', idleHint());
        preloadModel();
    }));
    paintEngine();

    // ---------- speech recognition (state machine) ----------
    // idle → loading (offline model) → requesting (mic permission) → listening → processing → done | error
    const speechCfg = {
        scriptUrl: B.speech.script, modelUrl: B.speech.model, modelBytes: B.speech.model_bytes,
        lang: 'en-US', silenceMs: 1800, noSpeechMs: 9000, maxMs: 60000,
    };
    const meter = $('levelMeter'), bars = meter ? [...meter.children] : [];
    let sessionEngine = null, autoReason = null, levelFrame = 0, pendingLevel = 0;

    function idleHint() {
        return engine === 'ondevice' && !Speech?.modelReady()
            ? 'Hold the mic to talk, or tap to start and stop. The offline model downloads once (~' + Math.round((B.speech.model_bytes || 0) / 1048576) + ' MB).'
            : 'Hold the mic to talk, or tap to start and stop. It stops by itself when you pause.';
    }
    function setMicStatus(text, recording) {
        micStatus.textContent = '';
        if (recording) micStatus.append(el('span', 'rec-dot'));
        if (text) micStatus.append(document.createTextNode(text));
    }
    function setState(s, text) {
        micState = s;
        micBtn.dataset.state = s;
        const ic = { idle: 'bi-mic-fill', loading: 'bi-cloud-arrow-down', requesting: 'bi-mic-fill', listening: 'bi-stop-fill', processing: 'bi-hourglass-split', done: 'bi-mic-fill', error: 'bi-mic-mute-fill' }[s] || 'bi-mic-fill';
        micBtn.firstElementChild.className = 'bi ' + ic;
        micBtn.setAttribute('aria-label', s === 'listening' ? 'Stop and keep this transcript' : (s === 'loading' || s === 'requesting') ? 'Cancel speaking' : s === 'processing' ? 'Finishing transcript' : 'Start speaking');
        micBtn.setAttribute('aria-pressed', s === 'listening' ? 'true' : 'false');
        if (meter) meter.hidden = s !== 'listening';
        if (s !== 'listening') paintLevel(0);
        const live = s === 'listening' || s === 'processing';
        answer.readOnly = live;
        answer.classList.toggle('is-live', live);
        sendBtn.disabled = s === 'processing' || answer.disabled;
        Object.values(engBtns).forEach(b => b.classList.toggle('locked', !['idle', 'done', 'error'].includes(s)));
        if (s !== 'loading') hideProgress();
        if (text != null) setMicStatus(text, s === 'listening');
    }
    function paintLevel(lv) {
        pendingLevel = lv;
        if (levelFrame) return;
        levelFrame = requestAnimationFrame(() => {
            levelFrame = 0;
            const v = Math.min(1, Math.sqrt(pendingLevel) * 3.2);
            micBtn.style.setProperty('--lvl', v.toFixed(3));
            bars.forEach((b, i) => { const k = [0.55, 0.85, 1, 0.8, 0.6][i] || 1; b.style.transform = 'scaleY(' + Math.max(0.18, Math.min(1, v * k)).toFixed(3) + ')'; });
        });
    }

    // Offline model progress (first download only; afterwards it is read from this browser's storage).
    function hideProgress() { $('modelProgress').classList.add('d-none'); }
    function paintProgress(p) {
        if (!p || p.phase === 'ready') { hideProgress(); return; }
        const wrap = $('modelProgress');
        wrap.classList.remove('d-none');
        const mb = n => (n / 1048576).toFixed(1);
        let pct = 4, label = 'Loading the offline speech engine…';
        if (p.phase === 'prepare') { pct = 8; label = 'Opening the offline speech model…'; }
        if (p.phase === 'download') {
            pct = p.total ? Math.max(8, Math.min(97, Math.round(p.loaded / p.total * 100))) : 50;
            label = 'Downloading the offline speech model (one time)' + (p.total ? ' · ' + mb(p.loaded) + ' / ' + mb(p.total) + ' MB' : '');
        }
        if (p.phase === 'unpack') { pct = 99; label = 'Unpacking the model on your device…'; }
        $('modelProgressLabel').textContent = label;
        $('modelProgressPct').textContent = pct + '%';
        $('modelProgressBar').style.width = pct + '%';
        $('modelProgressWrap').setAttribute('aria-valuenow', pct);
    }
    let preloading = false;
    function preloadModel() {
        if (mode !== 'voice' || engine !== 'ondevice' || !sup.ondevice || preloading || Speech.modelReady()) return;
        preloading = true;
        Speech.loadModel(speechCfg, p => { if (micState === 'idle' || micState === 'loading') paintProgress(p); })
            .then(() => { if (micState === 'idle' && mode === 'voice') setState('idle', 'Offline speech recogniser ready. Hold the mic to talk, or tap to start and stop.'); })
            .catch(err => { if (micState === 'idle' && mode === 'voice') showModelError(err); })
            .finally(() => { preloading = false; hideProgress(); });
    }

    // Help panel (permission steps, fallbacks) — built with textContent only.
    function hideHelp() { const h = $('micHelp'); h.classList.add('d-none'); h.textContent = ''; }
    function showHelp(kind, title, lines, action) {
        const h = $('micHelp');
        h.textContent = '';
        h.className = 'mic-help ' + kind;
        const t = el('div', 'fw-bold mb-1'); t.append(icon(kind === 'info' ? 'bi-info-circle-fill me-1' : 'bi-exclamation-triangle-fill me-1'), document.createTextNode(' ' + title)); h.append(t);
        if (lines && lines.length > 1) { const ol = el('ol', 'mb-2'); lines.forEach(l => ol.append(el('li', null, l))); h.append(ol); }
        else if (lines && lines.length) h.append(el('div', 'mb-2', lines[0]));
        if (action) { const b = el('button', 'btn btn-sm btn-ghost'); b.type = 'button'; b.append(icon(action.icon + ' me-1'), document.createTextNode(' ' + action.label)); b.addEventListener('click', action.run); h.append(b); }
    }
    function showModelError() {
        setState('error', 'The offline speech model could not be loaded.');
        showHelp('warn', 'Offline speech model didn’t load', [
            'Check that this page can still reach the Pratikshya server, then try again. If storage is full or blocked for this site, the model can’t be kept.' + (sup.native ? ' You can also switch to “Browser”.' : '') + ' Typing always works.',
        ], { icon: 'bi-arrow-clockwise', label: 'Try again', run: () => { hideHelp(); startListening(engine); } });
    }

    async function startListening(eng) {
        if (!canVoice || session || busy || !next || micBtn.disabled) return;
        synth?.cancel(); hideHelp(); errBox.textContent = '';
        baseText = answer.value.trim() ? answer.value.trim() + ' ' : '';
        autoReason = null;
        const s = Speech.create(eng, speechCfg, {
            onState: st => {
                if (session !== s) return;
                if (st === 'loading') {
                    if (Speech.modelReady()) setState('loading', 'Starting the on-device recogniser…');
                    else { setState('loading', 'Getting the offline speech recogniser ready…'); paintProgress({ phase: 'engine' }); }
                } else if (st === 'requesting') setState('requesting', 'Waiting for microphone permission… choose “Allow” if your browser asks.');
                else if (st === 'listening') { voiceStart = voiceStart || Date.now(); setState('listening', pttHeld ? 'Listening… release when you finish.' : 'Listening… speak naturally. Tap stop or just pause when you finish.'); }
                else if (st === 'processing') setState('processing', 'Finishing your transcript…');
                else if (st === 'idle') { session = null; setState('idle', idleHint()); }
                else if (st === 'done') finished(s);
            },
            onProgress: p => { if (session === s) paintProgress(p); },
            onText: (t, isFinal) => {
                if (session !== s) return;
                answer.value = (baseText + t).replace(/\s+/g, ' ').trimStart();
                autoGrow();
                if (isFinal) lastFinal = t;
            },
            onLevel: lv => { if (session === s) paintLevel(lv); },
            onAutoStop: reason => { autoReason = reason; },
            onError: err => fail(s, err),
        });
        session = s; sessionEngine = eng; lastFinal = '';
        try { await s.start(); } catch (err) { fail(s, err); }
    }
    let lastFinal = '';
    function finished(s) {
        if (session !== s) return;
        session = null; voiceEnd = Date.now();
        if (lastFinal.trim()) {
            usedVoice = true; usedEngine = sessionEngine;
            setState('done', 'Got it — check the text, fix anything misheard, then Send.');
            answer.focus({ preventScroll: true });
            const end = answer.value.length; try { answer.setSelectionRange(end, end); } catch (e) {}
        } else {
            setState('idle', autoReason === 'no-speech'
                ? 'I didn’t hear anything — check the right microphone is selected and try again a little closer.'
                : 'I didn’t catch any words — hold the mic and try again, or type your answer.');
        }
    }
    function fail(s, err) {
        if (session !== s) return;
        try { s.abort(); } catch (e) {}
        session = null;
        const code = (err && err.code) || 'unknown';
        if (code === 'native-unavailable' && sup.ondevice) {
            store.set(BROKEN_KEY, '1'); engine = 'ondevice'; store.set(ENGINE_KEY, 'ondevice'); paintEngine();
            setState('idle', '');
            startListening('ondevice'); // clears the help panel synchronously, so show the notice after
            showHelp('info', 'Switched to on-device recognition — please say that again', [
                'This browser’s built-in speech service isn’t available (Brave, for example, doesn’t include Google’s). The offline recogniser works without it, and your audio stays on this device.',
            ]);
            return;
        }
        const brave = !!navigator.brave;
        if (code === 'denied') {
            setState('error', 'Microphone access is blocked for this site.');
            showHelp('warn', 'Allow the microphone to speak your answers', brave ? [
                'Click the site-settings icon at the left of Brave’s address bar.',
                'Set Microphone to “Allow” (or open Site settings → Microphone).',
                'Reload this page and tap the mic again.',
            ] : [
                'Click the lock / site-settings icon at the left of the address bar.',
                'Set Microphone to “Allow”, then reload this page.',
            ], { icon: 'bi-arrow-clockwise', label: 'Try again', run: () => { hideHelp(); startListening(engine); } });
        } else if (code === 'no-mic') {
            setState('error', 'No microphone found.');
            showHelp('warn', 'No microphone was found', ['Plug in or enable a microphone (and check Windows sound settings → Input), then try again. You can keep typing.'],
                { icon: 'bi-arrow-clockwise', label: 'Try again', run: () => { hideHelp(); startListening(engine); } });
        } else if (code === 'busy') {
            setState('error', 'The microphone is busy.');
            showHelp('warn', 'Your microphone is in use', ['Another app or tab may be using it. Close that, then try again. You can keep typing.'],
                { icon: 'bi-arrow-clockwise', label: 'Try again', run: () => { hideHelp(); startListening(engine); } });
        } else if (code === 'no-speech') {
            setState('idle', 'I didn’t hear anything — try again a little closer to the mic.');
        } else if (code === 'engine-load' || code === 'model-load' || code === 'model-timeout') {
            showModelError(err);
        } else if (code === 'native-unavailable') {
            setState('error', 'Speech recognition isn’t available in this browser. You can keep typing.');
        } else {
            setState('error', 'Speech recognition stopped unexpectedly. Tap the mic to try again, or keep typing.');
        }
    }
    function stopListening() { if (session && micState === 'listening') session.stop(); }
    function cancelListening(silent) {
        if (!session) return;
        try { session.abort(); } catch (e) {}
        session = null;
        if (!silent) setState('idle', 'Cancelled. ' + idleHint());
    }

    // Push-to-talk (hold) and tap-to-toggle on the same button. Keyboard: Enter/Space toggles.
    let pressStarted = false, pttHeld = false, downAt = 0;
    micBtn.addEventListener('pointerdown', e => {
        if (micBtn.disabled || (e.pointerType === 'mouse' && e.button !== 0)) return;
        e.preventDefault();
        downAt = Date.now();
        if (micState === 'listening') { pressStarted = false; stopListening(); return; }
        if (micState === 'loading' || micState === 'requesting') { pressStarted = false; cancelListening(); return; }
        if (micState === 'processing') return;
        pressStarted = true; pttHeld = true;
        try { micBtn.setPointerCapture(e.pointerId); } catch (x) {}
        micBtn.focus({ preventScroll: true });
        startListening(engine);
    });
    const release = () => {
        if (!pressStarted) return;
        pressStarted = false; pttHeld = false;
        if (micState === 'listening' && Date.now() - downAt > 450) stopListening();
        else if (micState === 'listening') setMicStatus('Listening… speak naturally. Tap stop or just pause when you finish.', true);
    };
    micBtn.addEventListener('pointerup', release);
    micBtn.addEventListener('pointercancel', release);
    micBtn.addEventListener('contextmenu', e => e.preventDefault());
    micBtn.addEventListener('click', e => {
        if (e.detail !== 0) return; // pointer clicks are handled above; this is the keyboard path
        if (micState === 'listening') stopListening();
        else if (micState === 'loading' || micState === 'requesting') cancelListening();
        else if (micState !== 'processing') { pttHeld = false; startListening(engine); }
    });
    window.addEventListener('pagehide', () => cancelListening(true));
    paintMode();

    // ---------- composer ----------
    function autoGrow() { answer.style.height = 'auto'; answer.style.height = Math.min(answer.scrollHeight, 160) + 'px'; }
    answer.addEventListener('input', () => { autoGrow(); errBox.textContent = ''; });
    answer.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); } });
    function showError(msg) { errBox.textContent = msg; }

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (busy || !next) return;
        const text = answer.value.trim();
        if (!text) { showError(mode === 'voice' ? 'Tap the mic and speak, or type an answer first.' : 'Type an answer first.'); answer.focus(); return; }
        if (!/[A-Za-z]/.test(text)) { showError('Please answer in English words.'); return; }
        if (session) {
            // Sending while still listening: keep what was recognised so far.
            const live = answer.value.trim() !== baseText.trim();
            cancelListening(true); voiceEnd = Date.now();
            if (live) { usedVoice = true; usedEngine = sessionEngine; }
            setState('idle', idleHint());
        }

        const inputMode = mode === 'voice' && usedVoice ? 'voice' : 'text';
        const engineUsed = inputMode === 'voice' ? usedEngine : null;
        const ms = inputMode === 'voice' ? ((voiceEnd || Date.now()) - (voiceStart || Date.now())) : (Date.now() - promptShownAt);
        const elapsed = Math.max(0, Math.min(900, Math.round(ms / 1000)));

        busy = true; setComposer(false); errBox.textContent = '';
        const mine = meMsg(text, inputMode, engineUsed);
        answer.value = ''; autoGrow();
        const t = typing();
        const started = Date.now();
        try {
            const r = await fetch(B.urls.turn, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(Object.assign({ user_text: text, input_mode: inputMode, elapsed_seconds: elapsed }, engineUsed ? { stt_engine: engineUsed } : {})),
            });
            const j = await r.json().catch(() => ({}));
            // A short pause keeps the typing indicator readable instead of flashing.
            await new Promise(res => setTimeout(res, Math.max(0, 450 - (Date.now() - started))));
            t.remove();
            if (!r.ok) {
                mine.remove(); answer.value = text; autoGrow();
                const first = j.errors ? Object.values(j.errors)[0][0] : null;
                showError(first || j.message || 'Something went wrong — please try again.');
                if (r.status === 409 && !first) setComposer(false); else setComposer(true);
                busy = false; return;
            }
            const d = j.data;
            thread.querySelectorAll('.fb-card .fb-body').forEach(b => { if (!b.hidden) b.previousElementSibling.querySelector('.fb-toggle')?.click(); });
            fbCard(d.turn.feedback, true);
            answered = d.answered; planned = d.planned; next = d.next; setProgress();
            scrollDown();
            voiceStart = voiceEnd = null; usedVoice = false; usedEngine = null;
            setTimeout(() => {
                if (next) {
                    tutorMsg(next); promptShownAt = Date.now();
                    if (readAloud) speak(next.target || next.prompt);
                    setComposer(true); answer.focus({ preventScroll: true });
                    paintMode();
                } else {
                    doneCard();
                }
                scrollDown(); busy = false;
            }, reduceMotion ? 0 : 500);
        } catch (err) {
            t.remove(); mine.remove(); answer.value = text; autoGrow();
            showError('Network problem — your answer was not sent. Please try again.');
            setComposer(true); busy = false;
        }
    });

    // ---------- timer ----------
    const started = B.started_at ? new Date(B.started_at).getTime() : Date.now();
    const target = B.target_minutes * 60;
    let warned = false;
    const tick = () => {
        const s = Math.max(0, Math.floor((Date.now() - started) / 1000));
        $('timerText').textContent = Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
        if (s >= target && !warned) {
            warned = true; $('timer').classList.add('over');
            $('timer').title = 'Target time reached — finish whenever you like';
        }
    };
    tick(); setInterval(tick, 1000);

    $('finishForm').addEventListener('submit', () => { cancelListening(true); synth?.cancel(); });
    if (next && readAloud) setTimeout(() => speak(next.target || next.prompt), 400);
})();
