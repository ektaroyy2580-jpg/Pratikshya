@extends('layouts.app')
@section('title', 'Settings & privacy — प्रतीक्षा')

@push('head')
<link href="{{ asset('css/modules/platform.css') }}?v={{ filemtime(public_path('css/modules/platform.css')) }}" rel="stylesheet">
<style>
    .st-nav { position: sticky; top: 88px; }
    .st-nav a { display: flex; align-items: center; gap: .6rem; padding: .55rem .75rem; border-radius: 11px; color: var(--pk-ink-2); font-weight: 600; font-size: .9rem; text-decoration: none; transition: background .2s, color .2s; }
    .st-nav a:hover, .st-nav a.active { background: var(--pk-tint); color: var(--pk-blue); }
    .st-sec { scroll-margin-top: 90px; }
    .avatar-lg { width: 56px; height: 56px; border-radius: 18px; display: grid; place-items: center; font-size: 1.5rem; font-weight: 800; color: #fff; background: var(--pk-grad); flex-shrink: 0; }
    .danger-card { border-color: #ffd0dc; }
    @media (max-width: 991.98px) { .st-nav { position: static; display: flex; overflow-x: auto; gap: .35rem; padding-bottom: .25rem; } .st-nav a { white-space: nowrap; background: #fff; border: 1px solid var(--pk-line); } }
</style>
@endpush

@section('content')
@php $ch = $prefs->channels(); @endphp
<header class="pk-page-head pf-head">
    <div class="container fade-up">
        <a href="{{ route('platform.dashboard') }}" class="pk-breadcrumb"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <div class="d-flex align-items-center gap-3 mt-2">
            <span class="avatar-lg" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="min-w-0">
                <h1 class="mb-0">Settings & privacy</h1>
                <div class="pk-muted small text-truncate">{{ $user->email }} · {{ ucfirst($user->role) }} account</div>
            </div>
        </div>
    </div>
</header>

<div class="container py-4">
    <div class="row g-4">
        <div class="col-lg-3">
            <nav class="st-nav" aria-label="Settings sections">
                <a href="#profile"><i class="bi bi-person"></i> Profile</a>
                <a href="#security"><i class="bi bi-key"></i> Password</a>
                <a href="#notifications"><i class="bi bi-bell"></i> Notifications</a>
                <a href="#privacy"><i class="bi bi-shield-lock"></i> Privacy</a>
                <a href="#data"><i class="bi bi-download"></i> Your data</a>
                <a href="#delete" class="text-danger"><i class="bi bi-trash3"></i> Delete account</a>
            </nav>
        </div>
        <div class="col-lg-9 d-grid gap-3">

            {{-- Profile --}}
            <section id="profile" class="st-sec pk-card pf-panel reveal" aria-labelledby="profileH">
                <div class="pf-panel-head"><h2 id="profileH"><i class="bi bi-person text-primary"></i> Profile</h2></div>
                <form method="POST" action="{{ route('platform.settings.profile') }}" class="pf-panel-body pf-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="name">Full name</label>
                            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="80" autocomplete="name">
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Phone <span class="pk-muted fw-normal">(optional)</span></label>
                            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="20" autocomplete="tel" inputmode="tel" placeholder="98XXXXXXXX">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control" id="email" value="{{ $user->email }}" disabled readonly aria-describedby="emailHelp">
                            <div class="form-text" id="emailHelp">Your sign-in email can't be changed here.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="role">Account type</label>
                            <input class="form-control" id="role" value="{{ ucfirst($user->role) }}" disabled readonly aria-describedby="roleHelp">
                            <div class="form-text" id="roleHelp">Roles are assigned by platform administrators only.</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3"><button class="btn btn-pk"><i class="bi bi-check2 me-1"></i> Save profile</button></div>
                </form>
            </section>

            {{-- Password --}}
            <section id="security" class="st-sec pk-card pf-panel reveal" aria-labelledby="secH">
                <div class="pf-panel-head"><h2 id="secH"><i class="bi bi-key text-primary"></i> Change password</h2></div>
                <form method="POST" action="{{ route('platform.settings.password') }}" class="pf-panel-body pf-form">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="current_password">Current password</label>
                            <input class="form-control @error('current_password', 'password') is-invalid @enderror" type="password" id="current_password" name="current_password" required autocomplete="current-password">
                            @error('current_password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_password">New password</label>
                            <input class="form-control @error('password', 'password') is-invalid @enderror" type="password" id="new_password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="pwHelp">
                            @error('password', 'password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="password_confirmation">Confirm new password</label>
                            <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                        </div>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <span class="form-text m-0" id="pwHelp">At least 8 characters with letters and numbers.</span>
                        <button class="btn btn-pk"><i class="bi bi-shield-check me-1"></i> Update password</button>
                    </div>
                </form>
            </section>

            {{-- Notifications + privacy share one form (auto-saved) --}}
            <form method="POST" action="{{ route('platform.settings.preferences') }}" id="prefsForm" class="d-grid gap-3" data-no-busy>
                @csrf
                <section id="notifications" class="st-sec pk-card pf-panel reveal" aria-labelledby="notifH">
                    <div class="pf-panel-head"><h2 id="notifH"><i class="bi bi-bell text-primary"></i> Notification channels</h2><span class="pf-saved" role="status" aria-live="polite"><i class="bi bi-check2"></i> Saved</span></div>
                    <div class="pf-panel-body">
                        <div class="pf-switch">
                            <div><div class="t">In-app notifications</div><div class="d">Queue alerts, counter changes and updates in your notifications centre. Always on so you never miss your turn.</div></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" checked disabled aria-label="In-app notifications (always on)"></div>
                        </div>
                        <div class="pf-switch">
                            <div>
                                <div class="t">Browser notifications</div>
                                <div class="d">Pop-up alerts from this browser while a token page is open. Your browser will ask for permission. <span id="pushState" class="fw-semibold"></span></div>
                            </div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="browser_push" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" id="browser_push" name="browser_push" value="1" aria-label="Browser notifications" @checked($ch['browser_push'])>
                            </div>
                        </div>
                        <div class="pf-switch {{ $mailConfigured ? '' : 'disabled' }}">
                            <div><div class="t">Email</div><div class="d">{{ $mailConfigured ? 'Important updates by email.' : 'Unavailable — email delivery is not configured on this installation.' }}</div></div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="email" value="0">
                                <input class="form-check-input" type="checkbox" role="switch" name="email" value="1" aria-label="Email notifications" @checked($ch['email'] && $mailConfigured) @disabled(! $mailConfigured)>
                            </div>
                        </div>
                        <div class="pf-switch disabled">
                            <div><div class="t">SMS</div><div class="d">Unavailable — no SMS gateway is configured.</div></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" disabled aria-label="SMS notifications (not configured)"></div>
                        </div>
                    </div>
                </section>

                <section id="privacy" class="st-sec pk-card pf-panel reveal" aria-labelledby="privH">
                    <div class="pf-panel-head"><h2 id="privH"><i class="bi bi-shield-lock text-primary"></i> Privacy</h2><span class="pf-saved" role="status" aria-live="polite"><i class="bi bi-check2"></i> Saved</span></div>
                    <div class="pf-panel-body">
                        @foreach([
                            ['location_suggestions', 'Nearby suggestions', 'Recommend cafés, parks and errands near the office you are waiting at. Uses the office location, never your device GPS.', $prefs->location_suggestions],
                            ['personalised_ads', 'Personalised offers', 'Let sponsored offers use your waiting context (service type, wait length). Off: you still may see clearly labelled, non-personalised offers.', $prefs->personalised_ads],
                            ['marketing_opt_in', 'News & partner promotions', 'Occasional messages about partner rewards. Off by default.', $prefs->marketing_opt_in],
                        ] as [$name, $label, $desc, $value])
                            <div class="pf-switch">
                                <div><div class="t">{{ $label }}</div><div class="d">{{ $desc }}</div></div>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="{{ $name }}" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" aria-label="{{ $label }}" @checked($value)>
                                </div>
                            </div>
                        @endforeach
                        <noscript><div class="d-flex justify-content-end mt-2"><button class="btn btn-pk btn-sm">Save preferences</button></div></noscript>
                    </div>
                </section>
            </form>

            {{-- Data --}}
            <section id="data" class="st-sec pk-card pf-panel reveal" aria-labelledby="dataH">
                <div class="pf-panel-head"><h2 id="dataH"><i class="bi bi-download text-primary"></i> Your data</h2></div>
                <div class="pf-panel-body">
                    <p class="small mb-3">Download a JSON file with everything linked to your account: profile, preferences, queue tokens, notifications, points and redemptions. Nobody else's data is included.</p>
                    <a href="{{ route('platform.settings.export') }}" class="btn btn-ghost"><i class="bi bi-filetype-json me-1"></i> Download my data</a>
                    <details class="mt-3 small">
                        <summary class="fw-semibold">Data retention policy</summary>
                        <ul class="mt-2 mb-0 pk-muted ps-3 d-grid gap-1">
                            <li>Profile and preferences are kept while your account exists.</li>
                            <li>Queue records (tokens, timings, staff actions) are kept for service audits, wait-time statistics and fraud prevention. After account deletion they stay de-identified (“Deleted user”).</li>
                            <li>Notifications are deleted with your account. The points ledger is kept de-identified for accounting integrity.</li>
                            <li>This is a local demo installation — no data leaves this machine.</li>
                        </ul>
                    </details>
                </div>
            </section>

            {{-- Delete --}}
            <section id="delete" class="st-sec pk-card pf-panel danger-card reveal" aria-labelledby="delH">
                <div class="pf-panel-head"><h2 id="delH"><i class="bi bi-trash3 text-danger"></i> Delete account</h2></div>
                <div class="pf-panel-body">
                    <p class="small mb-3">Your name, email, phone, password, notifications and preferences are removed immediately and you are signed out. Queue records stay, de-identified, for the reasons above. This cannot be undone.</p>
                    @if($user->isAdmin())
                        <div class="alert alert-warning small mb-0"><i class="bi bi-info-circle me-1"></i>Platform admin accounts can't be deleted here. Ask another admin to change your role first.</div>
                    @else
                        <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#delForm" aria-expanded="{{ $errors->delete->any() ? 'true' : 'false' }}" aria-controls="delForm">I want to delete my account</button>
                        <form method="POST" action="{{ route('platform.settings.delete') }}" id="delForm" class="collapse {{ $errors->delete->any() ? 'show' : '' }} mt-3 pf-form" data-confirm="Permanently delete your account?">
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="del_pw">Your password</label>
                                    <input class="form-control @error('password', 'delete') is-invalid @enderror" type="password" id="del_pw" name="password" required autocomplete="current-password">
                                    @error('password', 'delete')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="del_confirm">Type <strong>DELETE</strong> to confirm</label>
                                    <input class="form-control @error('confirm', 'delete') is-invalid @enderror" id="del_confirm" name="confirm" required autocomplete="off" pattern="DELETE">
                                    @error('confirm', 'delete')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <button class="btn btn-danger btn-sm mt-3"><i class="bi bi-trash3 me-1"></i> Delete my account</button>
                        </form>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/modules/platform-console.js') }}?v={{ filemtime(public_path('js/modules/platform-console.js')) }}"></script>
<script src="{{ asset('js/modules/platform-notify.js') }}?v={{ filemtime(public_path('js/modules/platform-notify.js')) }}"></script>
<script>
(() => {
    const form = document.getElementById('prefsForm');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const push = document.getElementById('browser_push');
    const pushState = document.getElementById('pushState');

    const describe = () => {
        if (!('Notification' in window)) { pushState.textContent = 'This browser does not support notifications.'; push.disabled = true; return; }
        const p = Notification.permission;
        pushState.textContent = p === 'granted' ? 'Permission: allowed.' : p === 'denied' ? 'Permission: blocked in browser settings.' : '';
    };
    describe();

    const save = async (input) => {
        const badge = input.closest('section').querySelector('.pf-saved');
        try {
            const res = await fetch(form.action, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: new FormData(form) });
            if (!res.ok) throw new Error(res.status);
            badge.innerHTML = '<i class="bi bi-check2"></i> Saved';
            badge.style.color = '';
        } catch (e) {
            badge.innerHTML = '<i class="bi bi-exclamation-triangle"></i> Not saved — try again';
            badge.style.color = 'var(--pk-danger)';
            input.checked = !input.checked;
        }
        badge.classList.add('show');
        clearTimeout(badge._t); badge._t = setTimeout(() => badge.classList.remove('show'), 2200);
    };

    form.addEventListener('change', async (e) => {
        const input = e.target;
        if (input === push && push.checked && window.pkNotify) {
            // Ask only because the user just clicked this switch.
            const result = await window.pkNotify.requestPermission();
            describe();
            if (result !== 'granted') { push.checked = false; }
            else { window.pkNotify('Notifications are on', 'You\'ll get an alert here when your turn is near.'); }
        }
        save(input);
    });

    // Highlight current section in the side nav.
    const links = [...document.querySelectorAll('.st-nav a')];
    const io = new IntersectionObserver(entries => entries.forEach(en => {
        if (en.isIntersecting) links.forEach(l => l.classList.toggle('active', l.getAttribute('href') === '#' + en.target.id));
    }), { rootMargin: '-30% 0px -60% 0px' });
    document.querySelectorAll('.st-sec').forEach(s => io.observe(s));
})();
</script>
@endpush
