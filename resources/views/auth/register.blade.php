@extends('layouts.auth')

@section('title', 'Create an account')

@section('content')
<div class="pk-card auth-card fade-up">
    <span class="pk-eyebrow mb-2"><i class="bi bi-stars" aria-hidden="true"></i> Join in a minute</span>
    <h1 class="auth-title">Create your account</h1>
    <p class="auth-lead">Save your tokens, get turn alerts and make every wait count.</p>

    @if ($errors->any())
        <div class="alert alert-danger d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-circle-fill mt-1" aria-hidden="true"></i>
            <div>Please fix the highlighted fields below.</div>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" data-loading-form>
        @csrf

        <div class="mb-3">
            <label for="f-name" class="form-label">Full name</label>
            <input type="text" id="f-name" name="name" value="{{ old('name') }}"
                   class="form-control @error('name') is-invalid @enderror"
                   autocomplete="name" required maxlength="255" autofocus
                   placeholder="e.g. Sita Sharma"
                   @error('name') aria-invalid="true" aria-describedby="f-name-error" @enderror>
            @error('name')
                <div class="invalid-feedback" id="f-name-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-7">
                <label for="f-email" class="form-label">Email</label>
                <input type="email" id="f-email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror"
                       autocomplete="email" inputmode="email" required maxlength="255"
                       placeholder="you@example.com"
                       @error('email') aria-invalid="true" aria-describedby="f-email-error" @enderror>
                @error('email')
                    <div class="invalid-feedback" id="f-email-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-sm-5">
                <label for="f-phone" class="form-label">Phone <span class="pk-muted fw-normal">(optional)</span></label>
                <input type="tel" id="f-phone" name="phone" value="{{ old('phone') }}"
                       class="form-control @error('phone') is-invalid @enderror"
                       autocomplete="tel-national" inputmode="numeric"
                       placeholder="98XXXXXXXX" pattern="(\+?977[\- ]?)?9[678][0-9]{8}" maxlength="15"
                       title="10-digit Nepali mobile number starting with 96, 97 or 98"
                       aria-describedby="f-phone-help @error('phone') f-phone-error @enderror"
                       @error('phone') aria-invalid="true" @enderror>
                @error('phone')
                    <div class="invalid-feedback" id="f-phone-error">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12 mt-1">
                <div class="form-text mt-0" id="f-phone-help">Nepali mobile (96/97/98…), used for turn alerts only.</div>
            </div>
        </div>

        @include('auth.partials.password', [
            'name' => 'password',
            'label' => 'Password',
            'autocomplete' => 'new-password',
            'strength' => true,
        ])

        @include('auth.partials.password', [
            'name' => 'password_confirmation',
            'label' => 'Confirm password',
            'autocomplete' => 'new-password',
            'matches' => 'f-password',
        ])

        <div class="form-check mb-4 mt-1">
            <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" id="f-terms" value="1" required
                   @checked(old('terms'))
                   @error('terms') aria-invalid="true" aria-describedby="f-terms-error" @enderror>
            <label class="form-check-label small" for="f-terms">I agree to the Terms and Privacy Policy</label>
            @error('terms')
                <div class="invalid-feedback" id="f-terms-error">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-pk w-100" data-loading-text="Creating account…">
            Create account <i class="bi bi-arrow-right ms-1" aria-hidden="true"></i>
        </button>
    </form>

    <p class="text-center mt-4 mb-0 small">
        <span class="pk-muted">Already have an account?</span>
        <a href="{{ route('login') }}" class="fw-bold text-decoration-none">Log in</a>
    </p>
</div>
@endsection
