/* Speech-to-text engines for English practice (module: english).
 *
 *  - "ondevice": Vosk (Kaldi compiled to WebAssembly, vendor/vosk) with a small US-English model.
 *    Runs entirely in this browser tab: microphone samples go from an AudioWorklet straight into the
 *    recogniser's Web Worker. Audio is never stored, uploaded or sent anywhere.
 *  - "browser": the Web Speech API (SpeechRecognition). Chrome/Edge send audio to their maker's
 *    speech service; Brave exposes the API but has no speech backend, so it fails there
 *    (network / not-allowed / silent end) and the room falls back to "ondevice".
 *
 * Both engines share one microphone capture (getUserMedia) that also drives the level meter and the
 * silence auto-stop. Only the final, user-editable text ever leaves the page.
 */
(() => {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition || null;
    const AC = window.AudioContext || window.webkitAudioContext || null;
    const hasMic = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    const support = {
        native: !!SR,
        ondevice: !!(window.WebAssembly && AC && hasMic && window.Worker && window.Blob && window.URL),
        mic: hasMic,
        secure: window.isSecureContext !== false,
    };

    async function isBrave() {
        try { return !!(navigator.brave && await navigator.brave.isBrave()); } catch (e) { return false; }
    }

    async function micPermission() {
        try { return (await navigator.permissions.query({ name: 'microphone' })).state; } catch (e) { return 'unknown'; }
    }

    /** Map getUserMedia failures to codes the UI understands. */
    function micError(err) {
        const n = err && err.name;
        if (n === 'NotAllowedError' || n === 'PermissionDeniedError' || n === 'SecurityError') return 'denied';
        if (n === 'NotFoundError' || n === 'DevicesNotFoundError' || n === 'OverconstrainedError') return 'no-mic';
        if (n === 'NotReadableError' || n === 'TrackStartError' || n === 'AbortError') return 'busy';
        return 'mic-failed';
    }

    function speechError(code, detail) {
        const e = new Error(detail || code);
        e.code = code;
        return e;
    }

    // ------------------------------------------------------------------ microphone capture
    const TAP_WORKLET = `
        class PkTap extends AudioWorkletProcessor {
            constructor() { super(); this.buf = new Float32Array(2048); this.n = 0; }
            process(inputs) {
                const ch = inputs[0] && inputs[0][0];
                if (ch) {
                    for (let i = 0; i < ch.length; i++) {
                        this.buf[this.n++] = ch[i];
                        if (this.n === this.buf.length) { const out = this.buf; this.port.postMessage(out, [out.buffer]); this.buf = new Float32Array(2048); this.n = 0; }
                    }
                }
                return true;
            }
        }
        registerProcessor('pk-tap', PkTap);`;
    let workletUrl = null;

    /**
     * One live microphone session. onChunk(Float32Array, sampleRate) receives raw mono samples
     * (kept in memory only for the instant they are handed to the recogniser).
     */
    class Capture {
        constructor(onChunk) { this.onChunk = onChunk; this.level = 0; }

        async open() {
            if (!support.mic) throw speechError('no-mic');
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true, autoGainControl: true, channelCount: 1 }, video: false });
            } catch (e) {
                throw speechError(micError(e), e && e.message);
            }
            this.ctx = new AC();
            if (this.ctx.state === 'suspended') { try { await this.ctx.resume(); } catch (e) {} }
            this.sampleRate = this.ctx.sampleRate;
            this.src = this.ctx.createMediaStreamSource(this.stream);
            this.sink = this.ctx.createGain(); this.sink.gain.value = 0; // keeps the graph pulled; outputs silence
            this.sink.connect(this.ctx.destination);
            const handle = data => {
                let sum = 0;
                for (let i = 0; i < data.length; i++) sum += data[i] * data[i];
                this.level = Math.sqrt(sum / data.length);
                this.onChunk(data, this.sampleRate, this.level);
            };
            let usedWorklet = false;
            if (this.ctx.audioWorklet && window.AudioWorkletNode) {
                try {
                    workletUrl = workletUrl || URL.createObjectURL(new Blob([TAP_WORKLET], { type: 'application/javascript' }));
                    await this.ctx.audioWorklet.addModule(workletUrl);
                    this.node = new AudioWorkletNode(this.ctx, 'pk-tap', { numberOfInputs: 1, numberOfOutputs: 1, channelCount: 1 });
                    this.node.port.onmessage = e => handle(e.data);
                    usedWorklet = true;
                } catch (e) { usedWorklet = false; }
            }
            if (!usedWorklet) {
                this.node = this.ctx.createScriptProcessor(4096, 1, 1);
                this.node.onaudioprocess = e => handle(new Float32Array(e.inputBuffer.getChannelData(0)));
            }
            this.src.connect(this.node);
            this.node.connect(this.sink);
        }

        close() {
            try { this.src && this.src.disconnect(); } catch (e) {}
            try { if (this.node) { this.node.port && (this.node.port.onmessage = null); this.node.onaudioprocess = null; this.node.disconnect(); } } catch (e) {}
            try { this.stream && this.stream.getTracks().forEach(t => t.stop()); } catch (e) {}
            try { this.ctx && this.ctx.state !== 'closed' && this.ctx.close(); } catch (e) {}
            this.src = this.node = this.stream = this.ctx = null;
            this.level = 0;
        }
    }

    // ------------------------------------------------------------------ silence detector
    /** Tracks speech vs silence from RMS levels; adapts to the room's noise floor. */
    class Silence {
        constructor(opts) {
            this.silenceMs = opts.silenceMs || 1800;
            this.noSpeechMs = opts.noSpeechMs || 9000;
            this.maxMs = opts.maxMs || 60000;
            this.start = Date.now();
            this.floor = 0.004; this.heard = false; this.lastVoice = Date.now();
        }
        push(level) {
            const now = Date.now();
            const thr = Math.max(0.012, this.floor * 3.2);
            if (level > thr) { this.heard = true; this.lastVoice = now; } else { this.floor = this.floor * 0.95 + level * 0.05; }
        }
        activity() { this.heard = true; this.lastVoice = Date.now(); }
        verdict() {
            const now = Date.now();
            if (now - this.start > this.maxMs) return 'max';
            if (this.heard && now - this.lastVoice > this.silenceMs) return 'silence';
            if (!this.heard && now - this.start > this.noSpeechMs) return 'no-speech';
            return null;
        }
    }

    // ------------------------------------------------------------------ Vosk model loader
    let voskScript = null, modelPromise = null, model = null;
    const progressListeners = new Set();
    const notify = p => progressListeners.forEach(fn => { try { fn(p); } catch (e) {} });

    function loadScript(src) {
        if (window.Vosk) return Promise.resolve();
        if (voskScript) return voskScript;
        voskScript = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = src; s.async = true;
            s.onload = () => (window.Vosk ? resolve() : reject(speechError('engine-load', 'Vosk global missing')));
            s.onerror = () => { voskScript = null; reject(speechError('engine-load', 'Could not load ' + src)); };
            document.head.appendChild(s);
        });
        return voskScript;
    }

    /* Runs inside the recogniser's Web Worker before vosk-browser's own code: reports model
     * download progress back to the page. It only observes the model archive request. */
    function workerPrelude() {
        // vosk-browser prints every unpacked file name; keep the console for warnings and errors.
        self.console.log = self.console.info = self.console.debug = () => {};
        const origFetch = self.fetch.bind(self);
        let last = 0;
        self.fetch = async (input, init) => {
            const res = await origFetch(input, init);
            const url = typeof input === 'string' ? input : (input && input.url) || '';
            if (!/\.tar\.gz(\?|#|$)/.test(url) || !res.ok || !res.body) return res;
            const total = +res.headers.get('Content-Length') || 0;
            let loaded = 0;
            const reader = res.body.getReader();
            const body = new ReadableStream({
                async pull(ctrl) {
                    const r = await reader.read();
                    if (r.done) { self.postMessage({ event: 'pk-progress', loaded, total, done: true }); ctrl.close(); return; }
                    loaded += r.value.byteLength;
                    const now = Date.now();
                    if (now - last > 150) { last = now; self.postMessage({ event: 'pk-progress', loaded, total }); }
                    ctrl.enqueue(r.value);
                },
                cancel(reason) { reader.cancel(reason); },
            });
            return new Response(body, { status: res.status, statusText: res.statusText, headers: res.headers });
        };
    }

    /**
     * Load (once per page) the offline model. vosk-browser stores the unpacked model in this
     * browser's IndexedDB, so only the first visit downloads it; later loads read it locally.
     */
    function loadModel(cfg, onProgress) {
        if (onProgress) progressListeners.add(onProgress);
        if (model) { notify({ phase: 'ready' }); return Promise.resolve(model); }
        if (modelPromise) return modelPromise;
        modelPromise = (async () => {
            notify({ phase: 'engine' });
            try { navigator.storage && navigator.storage.persist && navigator.storage.persist(); } catch (e) {}
            await loadScript(cfg.scriptUrl);
            notify({ phase: 'prepare' });
            const RealWorker = window.Worker;
            window.Worker = function (url, opts) {
                const src = '(' + workerPrelude.toString() + ')();\nimportScripts(' + JSON.stringify(String(url)) + ');';
                return new RealWorker(URL.createObjectURL(new Blob([src], { type: 'application/javascript' })), opts);
            };
            let m;
            try { m = new window.Vosk.Model(new URL(cfg.modelUrl, location.href).href, -1); } finally { window.Worker = RealWorker; }
            m.addEventListener('pk-progress', e => {
                const d = e.detail || {};
                notify(d.done ? { phase: 'unpack' } : { phase: 'download', loaded: d.loaded, total: d.total || cfg.modelBytes || 0 });
            });
            await new Promise((resolve, reject) => {
                const timer = setTimeout(() => reject(speechError('model-timeout')), 180000);
                m.on('load', msg => { clearTimeout(timer); msg.result ? resolve() : reject(speechError('model-load')); });
                m.on('error', msg => { clearTimeout(timer); reject(speechError('model-load', msg && msg.error)); });
            }).catch(e => { try { m.terminate(); } catch (x) {} throw e; });
            model = m;
            notify({ phase: 'ready' });
            return m;
        })();
        modelPromise.catch(() => { modelPromise = null; });
        modelPromise.finally(() => progressListeners.clear()).catch(() => {});
        return modelPromise;
    }

    // ------------------------------------------------------------------ engines
    /**
     * Common session contract:
     *   const s = Engine.create(cfg, handlers); await s.start(); s.stop(); s.abort();
     *   handlers: onState(state), onText(text, isFinal), onLevel(0..1), onAutoStop(reason), onError(err)
     *   states: loading → requesting → listening → processing → done
     */
    function ondevice(cfg, h) {
        let cap = null, rec = null, silence = null, timer = null, finals = [], partial = '', stopped = false, settled = false;
        let finalWaiter = null;
        // Vosk returns lower-case words without punctuation; tidy the obvious bits for the editable box.
        const text = () => (finals.join(' ') + ' ' + partial).replace(/\s+/g, ' ').trim()
            .replace(/\bi\b/g, 'I').replace(/^./, ch => ch.toUpperCase());

        async function start() {
            h.onState('loading');
            const m = await loadModel(cfg, p => h.onProgress && h.onProgress(p));
            if (stopped) return;
            h.onState('requesting');
            cap = new Capture((data, rate, level) => {
                if (!rec || stopped) return;
                try { rec.acceptWaveformFloat(data, rate); } catch (e) { /* recogniser gone */ }
                silence.push(level);
                h.onLevel && h.onLevel(level);
            });
            await cap.open();
            if (stopped) { cap.close(); return; }
            rec = new m.KaldiRecognizer(cap.sampleRate);
            rec.on('partialresult', msg => {
                const p = (msg && msg.result && msg.result.partial) || '';
                if (p && p !== partial) silence.activity();
                partial = p; h.onText(text(), false);
            });
            rec.on('result', msg => {
                const t = (msg && msg.result && msg.result.text) || '';
                if (t) finals.push(t);
                partial = '';
                h.onText(text(), false);
                if (finalWaiter) finalWaiter();
            });
            rec.on('error', msg => h.onError && h.onError(speechError('recogniser', msg && msg.error)));
            silence = new Silence(cfg);
            h.onState('listening');
            timer = setInterval(() => { const v = silence.verdict(); if (v) { h.onAutoStop && h.onAutoStop(v); stop(); } }, 200);
        }

        async function stop() {
            if (settled) return;
            stopped = true; clearInterval(timer);
            if (cap) cap.close();
            if (!rec) { settled = true; h.onState('idle'); return; }
            h.onState('processing');
            await new Promise(resolve => {
                const t = setTimeout(resolve, 8000);
                finalWaiter = () => { clearTimeout(t); resolve(); };
                try { rec.retrieveFinalResult(); } catch (e) { resolve(); }
            });
            finalWaiter = null;
            try { rec.remove(); } catch (e) {}
            rec = null; settled = true;
            const out = text();
            h.onText(out, true);
            h.onState('done');
        }

        function abort() {
            stopped = true; settled = true; clearInterval(timer);
            if (cap) cap.close();
            try { rec && rec.remove(); } catch (e) {}
            rec = null;
        }
        return { engine: 'ondevice', start, stop, abort };
    }

    const FALLBACK_ERRORS = ['network', 'service-not-allowed', 'language-not-supported', 'bad-grammar'];

    function native(cfg, h) {
        let r = null, cap = null, silence = null, timer = null, finalT = '', interim = '', gotResult = false;
        let stopped = false, settled = false, startedAt = 0, failure = null, userStop = false;
        const text = () => (finalT + ' ' + interim).replace(/\s+/g, ' ').trim();

        async function start() {
            h.onState('requesting');
            // Our own capture: explicit permission prompt, live level meter and silence detection.
            cap = new Capture((data, rate, level) => { if (!stopped) { silence.push(level); h.onLevel && h.onLevel(level); } });
            await cap.open();
            if (stopped) { cap.close(); return; }
            silence = new Silence(cfg);
            r = new SR();
            r.lang = cfg.lang || 'en-US';
            r.interimResults = true; r.continuous = true; r.maxAlternatives = 1;
            await new Promise((resolve, reject) => {
                r.onstart = () => { startedAt = Date.now(); h.onState('listening'); resolve(); };
                r.onresult = e => {
                    gotResult = true; silence.activity();
                    let f = '', i2 = '';
                    for (let i = 0; i < e.results.length; i++) e.results[i].isFinal ? (f += e.results[i][0].transcript + ' ') : (i2 += e.results[i][0].transcript);
                    finalT = f; interim = i2; h.onText(text(), false);
                };
                r.onerror = e => { failure = e.error || 'unknown'; resolve(); };
                r.onend = () => finish();
                try { r.start(); } catch (e) { failure = 'start-failed'; resolve(); }
            });
            timer = setInterval(() => { const v = silence.verdict(); if (v && !stopped) { h.onAutoStop && h.onAutoStop(v); stop(); } }, 200);
            if (failure && !settled) finish();
        }

        async function finish() {
            if (settled) return;
            settled = true; stopped = true; clearInterval(timer);
            if (cap) cap.close();
            try { r && r.abort(); } catch (e) {}
            const quick = startedAt && Date.now() - startedAt < 1500;
            let err = null;
            if (failure && FALLBACK_ERRORS.includes(failure)) err = speechError('native-unavailable', failure);
            else if (failure === 'not-allowed') {
                // The page already holds the microphone, so "not-allowed" means the browser blocks its speech service (Brave).
                err = (await micPermission()) === 'denied' ? speechError('denied') : speechError('native-unavailable', failure);
            } else if (failure === 'audio-capture') err = speechError('no-mic');
            else if (failure === 'no-speech' && !gotResult) err = speechError('no-speech');
            else if (failure === 'start-failed') err = speechError('native-unavailable', failure);
            else if (!gotResult && !userStop && (quick || !startedAt)) err = speechError('native-unavailable', 'ended-silently');
            if (err) { h.onError(err); return; }
            h.onText(text(), true);
            h.onState('done');
        }

        function stop() {
            if (settled || stopped) return;
            userStop = true; stopped = true; clearInterval(timer);
            h.onState('processing');
            if (cap) cap.close();
            try { r ? r.stop() : finish(); } catch (e) { finish(); }
            setTimeout(finish, 2500); // some browsers never fire onend after stop()
        }

        function abort() { settled = true; stopped = true; clearInterval(timer); if (cap) cap.close(); try { r && r.abort(); } catch (e) {} }
        return { engine: 'browser', start, stop, abort };
    }

    window.PkSpeech = {
        support, isBrave, micPermission, loadModel,
        modelReady: () => !!model,
        create: (engine, cfg, handlers) => (engine === 'browser' ? native(cfg, handlers) : ondevice(cfg, handlers)),
    };
})();
