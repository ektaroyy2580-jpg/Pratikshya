@extends('layouts.auth')

@section('title', 'Forgot password')

@section('content')
<div class="pk-card auth-card fade-up">
    <span class="pk-icon mb-3" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
    <h1 class="auth-title">Forgot your password?</h1>
    <p class="auth-lead">Enter the email you signed up with and we'll send you a link to choose a new one.</p>

    @if (session('status'))
        <div class="alert alert-success d-flex gap-2 align-items-start" role="status">
            <i class="bi bi-envelope-check-fill mt-1" aria-hidden="true"></i>
            {{-- Fixed, non-enumerating copy regardless of the broker's status string --}}
            <div>If an account exists for that email, we've sent a reset link.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" data-loading-form>
        @csrf

        <div class="mb-4">
            <label for="f-email" class="form-label">Email</label>
            <input type="email" id="f-email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   autocomplete="email" inputmode="email" required autofocus
                   placeholder="you@example.com"
                   aria-describedby="f-email-help @error('email') f-email-error @enderror"
                   @error('email') aria-invalid="true" @enderror>
            @error('email')
                <div class="invalid-feedback" id="f-email-error">{{ $message }}</div>
            @enderror
            <div class="form-text" id="f-email-help">The link expires after a short time for your security.</div>
        </div>

        <button type="submit" class="btn btn-pk w-100" data-loading-text="Sending link…">
            <i class="bi bi-send me-1" aria-hidden="true"></i> Send reset link
        </button>
    </form>

    <div class="demo-box small px-3 py-2 mt-4 pk-muted d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
        <span>Local demo: reset links are written to <code>storage/logs/laravel.log</code></span>
    </div>

    <p class="text-center mt-4 mb-0 small">
        <a href="{{ route('login') }}" class="fw-bold text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to log in</a>
    </p>
</div>
@endsection
