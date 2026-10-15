@extends('layouts.auth')

@section('title', 'Staff & Admin Portal')
@section('variant', 'staff')

@push('head')
<style>
    .portal-badge { display: inline-flex; align-items: center; gap: .45rem; padding: .38rem .8rem; border-radius: 999px;
        background: var(--pk-ink); color: #fff; font-weight: 700; font-size: .76rem; letter-spacing: .02em; }
    .portal-badge i { color: #b9c8ff; }
    .staff-note { background: var(--pk-bg); border: 1px solid var(--pk-line); color: var(--pk-ink-2); border-radius: var(--pk-radius-sm); }
    .staff-note i { color: var(--pk-blue); }
</style>
@endpush

@section('content')
<div class="pk-card auth-card fade-up">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <span class="portal-badge"><i class="bi bi-shield-check" aria-hidden="true"></i> Staff &amp; Admin portal</span>
        <span class="pk-icon violet" aria-hidden="true"><i class="bi bi-person-badge"></i></span>
    </div>
    <h1 class="auth-title">Staff sign in</h1>
    <p class="auth-lead mb-3">Call tokens, verify documents and manage your counter.</p>

    <div class="staff-note small px-3 py-2 mb-4 d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle-fill mt-1" aria-hidden="true"></i>
        <span>Access requires an approved staff role and an assigned location.</span>
    </div>

    @if (session('status'))
        <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
            <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->has('email'))
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-circle-fill mt-1" aria-hidden="true"></i>
            <div>We couldn't sign you in to the staff portal. Please check the details below.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('staff.login.post') }}" data-loading-form>
        @csrf

        <div class="mb-3">
            <label for="f-email" class="form-label">Work email</label>
            <input type="email" id="f-email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   autocomplete="username" inputmode="email" required autofocus
                   placeholder="staff@example.com"
                   @error('email') aria-invalid="true" aria-describedby="f-email-error" @enderror>
            @error('email')
                <div class="invalid-feedback" id="f-email-error">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.password', ['name' => 'password', 'label' => 'Password', 'autocomplete' => 'current-password'])

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="f-remember" value="1" @checked(old('remember'))>
            <label class="form-check-label" for="f-remember">Remember me on this device</label>
        </div>

        <button type="submit" class="btn btn-pk w-100" data-loading-text="Verifying…">
            Sign in to portal <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </button>
    </form>

    <div class="divider my-4">not staff?</div>

    <a href="{{ route('login') }}" class="alt-link">
        <i class="bi bi-person" aria-hidden="true"></i> Go to user login
    </a>
</div>
@endsection
