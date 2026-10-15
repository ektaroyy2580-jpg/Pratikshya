@extends('layouts.auth')

@section('title', 'Reset password')

@section('content')
<div class="pk-card auth-card fade-up">
    <span class="pk-icon violet mb-3" aria-hidden="true"><i class="bi bi-key"></i></span>
    <h1 class="auth-title">Choose a new password</h1>
    <p class="auth-lead">Make it strong and unique. You'll use it to log in from now on.</p>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-circle-fill mt-1" aria-hidden="true"></i>
            <div>Please fix the highlighted fields below.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('password.update') }}" data-loading-form>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div class="mb-3">
            <label for="f-email" class="form-label">Email</label>
            <input type="email" id="f-email" name="email" value="{{ old('email', $email ?? '') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   autocomplete="username" inputmode="email" required
                   @error('email') aria-invalid="true" aria-describedby="f-email-error" @enderror>
            @error('email')
                <div class="invalid-feedback" id="f-email-error">{{ $message }}</div>
            @enderror
        </div>

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'New password',
            'autocomplete' => 'new-password',
            'strength' => true,
        ])

        @include('auth.partials.password', [
            'name' => 'password_confirmation',
            'label' => 'Confirm new password',
            'autocomplete' => 'new-password',
            'matches' => 'f-password',
        ])

        <button type="submit" class="btn btn-pk w-100 mt-2" data-loading-text="Saving…">
            <i class="bi bi-check2-circle me-1" aria-hidden="true"></i> Reset password
        </button>
    </form>

    <p class="text-center mt-4 mb-0 small">
        <a href="{{ route('login') }}" class="fw-bold text-decoration-none"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to log in</a>
    </p>
</div>
@endsection
