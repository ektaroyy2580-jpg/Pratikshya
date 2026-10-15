/* Rewarded demo player. The countdown here is only for display — the SERVER decides whether enough time
   has passed (it stored the start time when the user pressed play) and whether a reward is granted. */
(() => {
    const form = document.getElementById('rwForm');
    if (!form) return;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const stage = document.getElementById('rwStage');
    const bar = document.getElementById('rwBar');
    const time = document.getElementById('rwTime');
    const startBtn = document.getElementById('rwStart');
    const claimBtn = document.getElementById('rwClaim');
    const msg = document.getElementById('rwMsg');
    const choices = document.getElementById('rwChoices');
    const total = parseInt(form.dataset.seconds, 10) || 15;
    let completeUrl = null;
    let busy = false;

    const say = (text, tone) => { msg.textContent = text || ''; msg.className = 'mt-3 small fw-semibold ' + (tone === 'err' ? 'text-danger' : 'pk-muted'); };
    const fmt = s => '0:' + String(Math.max(0, s)).padStart(2, '0');

    async function post(url, body) {
        const res = await fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(body || {}),
        });
        let json = {};
        try { json = await res.json(); } catch (e) { /* non-JSON error */ }
        return { ok: res.ok, status: res.status, json };
    }

    function play(remaining) {
        stage.classList.remove('idle');
        stage.classList.add('playing');
        const started = performance.now();
        const endAt = started + remaining * 1000;
        const tick = () => {
            const left = Math.max(0, Math.ceil((endAt - performance.now()) / 1000));
            const pct = Math.min(100, ((total - left) / total) * 100);
            bar.style.width = pct + '%';
            time.textContent = fmt(left);
            if (left > 0) { requestAnimationFrame(tick); return; }
            stage.classList.remove('playing');
            stage.classList.add('done');
            claimBtn.disabled = false;
            claimBtn.focus({ preventScroll: true });
            say('Video finished. Claim your reward.');
            claim(); // auto-claim; the button stays available if the network hiccups
        };
        requestAnimationFrame(tick);
    }

    form.addEventListener('submit', async e => {
        e.preventDefault();
        if (busy) return;
        busy = true;
        startBtn.disabled = true;
        say('Starting…');
        const reward = form.querySelector('input[name="reward_type"]:checked')?.value;
        const r = await post(form.dataset.start, { reward_type: reward, placement: form.dataset.placement, campaign_id: parseInt(form.dataset.campaign, 10) });
        busy = false;
        if (!r.ok) { startBtn.disabled = false; say(r.json.message || 'Could not start the video. Please try again.', 'err'); return; }
        completeUrl = r.json.data.complete_url;
        choices.disabled = true;
        startBtn.classList.add('d-none');
        claimBtn.classList.remove('d-none');
        document.getElementById('rwSkip')?.classList.add('d-none');
        say('Playing sponsored demo video…');
        play(r.json.data.remaining_seconds);
    });

    async function claim() {
        if (busy || !completeUrl) return;
        busy = true;
        claimBtn.disabled = true;
        const r = await post(completeUrl);
        busy = false;
        if (r.ok) {
            form.classList.add('d-none');
            document.getElementById('rwDoneText').textContent = r.json.message;
            document.getElementById('rwDone').classList.remove('d-none');
            const ex = document.getElementById('rwExtra');
            if (ex && r.json.data && typeof r.json.data.extra_rounds === 'number') ex.textContent = r.json.data.extra_rounds;
            return;
        }
        if (r.status === 422 && r.json.data && r.json.data.remaining_seconds > 0) {
            // Server says not enough time has passed (e.g. the device clock ran fast) — wait and retry once.
            say('Almost there… verifying in ' + r.json.data.remaining_seconds + 's.');
            setTimeout(claim, r.json.data.remaining_seconds * 1000 + 300);
            return;
        }
        claimBtn.disabled = r.status >= 500 ? false : true;
        say(r.json.message || 'Could not verify the reward.', 'err');
    }
    claimBtn.addEventListener('click', claim);
})();
