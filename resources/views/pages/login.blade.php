@extends('layouts.app')
@section('title', 'Login — प्रतीक्षा')

@section('content')
<div class="container py-5" style="max-width:460px">
    <div class="text-center mb-4 fade-up">
        <span class="brand-mark mb-2" style="width:56px;height:56px;font-size:1.6rem"><i class="bi bi-hourglass-split"></i></span>
        <h2 class="font-display fw-bold mb-1">Your प्रतीक्षा profile</h2>
        <p class="text-muted-ww">Save your WaitPoints and reclaimed minutes across visits.</p>
    </div>
    <div class="card-ww p-4 fade-up">
        <ul class="nav nav-pills nav-fill mb-4" role="tablist">
            <li class="nav-item"><button class="nav-link {{ old('name') ? '' : 'active' }} rounded-4 fw-bold" data-bs-toggle="pill" data-bs-target="#tLogin">Log in</button></li>
            <li class="nav-item"><button class="nav-link {{ old('name') ? 'active' : '' }} rounded-4 fw-bold" data-bs-toggle="pill" data-bs-target="#tReg">Create account</button></li>
        </ul>
        @if(session('url.intended') && ! $errors->any())
            <div class="alert rounded-4 small py-2" style="background:#eeebff;border:0"><i class="bi bi-ticket-perforated-fill text-primary"></i> Please log in or create an account to join a queue.</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger rounded-4 small py-2">{{ $errors->first() }}</div>
        @endif
        <div class="tab-content">
            <form class="tab-pane fade {{ old('name') ? '' : 'show active' }}" id="tLogin" method="POST" action="{{ route('login.post') }}">
                @csrf
                <input name="email" type="email" class="form-control form-control-lg rounded-4 mb-2" placeholder="Email" value="{{ old('email', 'demo@waitwise.np') }}" required>
                <input name="password" type="password" class="form-control form-control-lg rounded-4 mb-3" placeholder="Password" required>
                <button class="btn btn-ww w-100">Log in</button>
                <div class="small text-muted-ww text-center mt-2">Demo: demo@waitwise.np / demo</div>
            </form>
            <form class="tab-pane fade {{ old('name') ? 'show active' : '' }}" id="tReg" method="POST" action="{{ route('register') }}">
                @csrf
                <input name="name" class="form-control form-control-lg rounded-4 mb-2" placeholder="Full name" value="{{ old('name') }}" required>
                <input name="email" type="email" class="form-control form-control-lg rounded-4 mb-2" placeholder="Email" required>
                <input name="password" type="password" class="form-control form-control-lg rounded-4 mb-3" placeholder="Password (min 4)" required>
                <button class="btn btn-lime w-100">Create profile</button>
            </form>
        </div>
    </div>
</div>
@endsection
