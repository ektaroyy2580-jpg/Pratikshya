/* Pratikshya turn alerts: chime + toast + vibration + system notification when your turn is near.
 * Runs on every page while the user holds an active token (config in window.PK_ALERT).
 * The ticket page shows its own modal; this script supplies the sound there (window.pkChime)
 * and handles alerts on all other pages. Already-shown alerts are remembered per ticket in
 * localStorage (same key the ticket page uses), so nothing repeats after a reload. */
(function () {
    'use strict';
    const C = window.PK_ALERT || null;
    const store = {
        get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
        set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
    };
    const soundOn = () => store.get('pk_sound', true) !== false;

    // ---------- Sound: synthesised bell chime (no audio files) ----------
    let ctx = null;
    function audio() {
        if (!ctx) { const AC = window.AudioContext || window.webkitAudioContext; if (!AC) return null; ctx = new AC(); }
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
    }
    // Browsers only allow sound after a user gesture: unlock on the first click/tap/key anywhere.
    ['pointerdown', 'keydown', 'touchstart'].forEach(ev => window.addEventListener(ev, () => audio(), { once: true, passive: true }));

    function bell(a, freq, at, dur, gain) {
        [[1, gain], [2.76, gain * 0.35], [5.4, gain * 0.12]].forEach(([mult, g]) => {
            const o = a.createOscillator(), v = a.createGain();
            o.type = 'sine'; o.frequency.value = freq * mult;
            v.gain.setValueAtTime(0.0001, at);
            v.gain.exponentialRampToValueAtTime(g, at + 0.012);
            v.gain.exponentialRampToValueAtTime(0.0001, at + dur);
            o.connect(v); v.connect(a.destination); o.start(at); o.stop(at + dur + 0.05);
        });
    }
    const PATTERNS = {
        three: [[659.25, 0], [783.99, 0.18]],                                    // E5 G5 — gentle heads-up
        next: [[523.25, 0], [659.25, 0.16], [783.99, 0.32]],                     // C E G — get ready
        called: [[523.25, 0], [659.25, 0.14], [783.99, 0.28], [1046.5, 0.42],
                 [523.25, 1.1], [659.25, 1.24], [783.99, 1.38], [1046.5, 1.52]], // twice, rising — go now
        test: [[783.99, 0], [1046.5, 0.16]],
    };
    window.pkChime = function (kind) {
        if (!soundOn()) return;
        const a = audio(); if (!a) return;
        const t0 = a.currentTime + 0.03;
        (PATTERNS[kind] || PATTERNS.next).forEach(([f, d]) => bell(a, f, t0 + d, 1.3, kind === 'called' ? 0.32 : 0.24));
        if (navigator.vibrate) navigator.vibrate(kind === 'called' ? [300, 120, 300, 120, 300] : [180, 90, 180]);
    };

    // ---------- Sound toggle button (navbar) ----------
    function paintToggle(btn) {
        const on = soundOn();
        btn.classList.toggle('on', on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on ? 'Turn alerts: sound on' : 'Turn alerts: muted';
        btn.innerHTML = `<i class="bi ${on ? 'bi-bell-fill' : 'bi-bell-slash'}"></i>`;
    }
    document.querySelectorAll('[data-pk-sound]').forEach(btn => {
        paintToggle(btn);
        btn.addEventListener('click', () => {
            store.set('pk_sound', !soundOn());
            paintToggle(btn);
            if (soundOn()) {
                window.pkChime('test');
                if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
            }
        });
    });

    if (!C) return;

    // ---------- Toast ----------
    function stack() {
        let s = document.querySelector('.pk-alert-stack');
        if (!s) { s = document.createElement('div'); s.className = 'pk-alert-stack'; s.setAttribute('aria-live', 'assertive'); document.body.appendChild(s); }
        return s;
    }
    function toast(kind, title, body) {
        const el = document.createElement('div');
        el.className = 'pk-turn-alert ' + kind;
        el.setAttribute('role', 'alert');
        const icon = kind === 'called' ? 'bi-megaphone-fill' : 'bi-bell-fill';
        el.innerHTML = `<span class="bell"><i class="bi ${icon}"></i></span>
            <div class="flex-grow-1"><div class="t"></div><div class="small pk-muted b"></div>
            <div class="d-flex gap-2 mt-2"><a class="btn btn-pk btn-sm" href="${C.ticketUrl}">Open my token</a>
            <button type="button" class="btn btn-ghost btn-sm" data-close>Dismiss</button></div></div>`;
        el.querySelector('.t').textContent = title;
        el.querySelector('.b').textContent = body;
        const close = () => { el.classList.add('leaving'); setTimeout(() => el.remove(), 300); };
        el.querySelector('[data-close]').addEventListener('click', close);
        stack().prepend(el);
        if (kind !== 'called') setTimeout(close, 15000);
    }
    function system(title, body) {
        if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden) return;
        try { const n = new Notification(title, { body, tag: 'pk-turn-' + C.ticketId, icon: '/favicon.svg' }); n.onclick = () => { window.focus(); location.href = C.ticketUrl; }; } catch (e) {}
    }

    // ---------- Polling (skipped on the ticket page, which polls and shows its own modal) ----------
    if (C.onTicketPage) return;
    const KEY = 'pk-notified-' + C.ticketId;
    function check(st) {
        const seen = store.get(KEY, {});
        let fire = null;
        if (st.status === 'called' && !seen.called) { seen.called = seen.next = seen.three = true; fire = ['called', `It's your turn: ${st.token}`, `Please go to Counter ${st.counter} now.`]; }
        else if (st.status === 'waiting' && st.ahead === 0 && !seen.next) { seen.next = seen.three = true; fire = ['next', `You're next: ${st.token}`, `Head back to Counter ${st.counter}. You'll be called in moments.`]; }
        else if (st.status === 'waiting' && st.ahead > 0 && st.ahead <= 3 && !seen.three) { seen.three = true; fire = ['three', `${st.ahead} ${st.ahead === 1 ? 'person' : 'people'} ahead of ${st.token}`, `Start heading back to Counter ${st.counter}.`]; }
        if (!fire) return;
        store.set(KEY, seen);
        const t = window.pkT ? window.pkT : (s => s);
        toast(fire[0], t(fire[1]), t(fire[2]));
        window.pkChime(fire[0]);
        system(fire[1], fire[2]);
    }
    async function poll() {
        try {
            const r = await fetch(C.stateUrl, { headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const st = (await r.json()).data;
            if (!st) return;
            if (!['waiting', 'called', 'serving'].includes(st.status)) { clearInterval(timer); return; }
            check(st);
        } catch (e) {}
    }
    const timer = setInterval(poll, 10000);
    poll();
})();
