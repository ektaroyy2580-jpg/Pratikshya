@extends('layouts.app')
@section('title', 'Invite link not valid — प्रतीक्षा')
@include('bills.partials.assets')

@section('content')
<div class="container py-5">
    <div class="mx-auto" style="max-width:440px">
        <div class="pk-card p-4 p-md-5 text-center fade-up">
            <span class="pk-icon rose mb-3" style="width:64px;height:64px;font-size:1.8rem;border-radius:18px"><i class="bi bi-link-45deg"></i></span>
            <h1 class="h4 mb-2">This invite link doesn't work</h1>
            <p class="pk-muted mb-4">It may have expired, been replaced with a new link, or been switched off by the bill owner. Ask them to share a fresh link or invite you by email.</p>
            <a href="{{ route('bills.index') }}" class="btn btn-pk w-100"><i class="bi bi-receipt me-1"></i>Go to my bills</a>
        </div>
    </div>
</div>
@endsection
