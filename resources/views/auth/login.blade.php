@extends('layouts.auth')

@section('title', 'Log in')

@section('content')
<div class="pk-card auth-card fade-up">
    <span class="pk-eyebrow mb-2"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Welcome back</span>
    <h1 class="auth-title">Log in to प्रतीक्षा</h1>
    <p class="auth-lead">Track your tokens and pick up where you left off.</p>

    @if (session('status'))
        <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
            <i class="bi bi-check-circle-fill mt-1" aria-hidden="true"></i>
            <div>{{ session('status') }}</div>
        </div>
    @endif

    @if ($errors->has('email'))
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-circle-fill mt-1" aria-hidden="true"></i>
            <div>We couldn't sign you in. Please check the details below and try again.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}" data-loading-form>
        @csrf

        <div class="mb-3">
            <label for="f-email" class="form-label">Email</label>
            <input type="email" id="f-email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   autocomplete="username" inputmode="email" required autofocus
                   placeholder="you@example.com"
                   @error('email') aria-invalid="true" aria-describedby="f-email-error" @enderror>
            @error('email')
                <div class="invalid-feedback" id="f-email-error">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'Password',
            'autocomplete' => 'current-password',
            'aside' => '<a href="'.e(route('password.request')).'">Forgot password?</a>',
        ])

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="remember" id="f-remember" value="1" @checked(old('remember'))>
            <label class="form-check-label" for="f-remember">Keep me signed in</label>
        </div>

        <button type="submit" class="btn btn-pk w-100" data-loading-text="Signing in…">
            Log in <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </button>
    </form>

    <p class="text-center mt-4 mb-0 small">
        <span class="pk-muted">New here?</span>
        <a href="{{ route('register.show') }}" class="fw-bold text-decoration-none">Create an account</a>
    </p>

    <div class="divider my-4">or</div>

    <a href="{{ route('staff.login') }}" class="alt-link">
        <i class="bi bi-person-badge" aria-hidden="true"></i> Staff &amp; Admin portal
    </a>
</div>

<div class="demo-box mt-3 fade-up" style="animation-delay: .1s;">
    <button class="demo-toggle d-flex align-items-center gap-2 w-100" type="button"
            data-bs-toggle="collapse" data-bs-target="#demo-accounts" aria-expanded="false" aria-controls="demo-accounts">
        <i class="bi bi-key" aria-hidden="true"></i> Demo accounts <span class="pk-muted fw-semibold">(local only)</span>
        <i class="bi bi-chevron-down ms-auto" aria-hidden="true"></i>
    </button>
    <div class="collapse" id="demo-accounts">
        <div class="px-3 pb-3">
            <ul class="list-unstyled small mb-0 d-grid gap-2">
                <li class="d-flex flex-wrap gap-1 align-items-center"><span class="demo-role">User</span> <code>demo@waitwise.np</code> / <code>demo</code></li>
                <li class="d-flex flex-wrap gap-1 align-items-center"><span class="demo-role">Staff</span> <code>staff@waitwise.np</code> / <code>Staff@1234</code></li>
                <li class="d-flex flex-wrap gap-1 align-items-center"><span class="demo-role">Admin</span> <code>admin@waitwise.np</code> / <code>Admin@1234</code></li>
            </ul>
            <p class="small pk-muted mt-2 mb-0">Staff and admin accounts sign in through the <a href="{{ route('staff.login') }}">staff portal</a>.</p>
        </div>
    </div>
</div>
@endsection
