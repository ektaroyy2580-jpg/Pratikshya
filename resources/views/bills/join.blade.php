@extends('layouts.app')
@section('title', 'Join '.$bill->title.' — प्रतीक्षा')
@include('bills.partials.assets')

@section('content')
<div class="container py-5">
    <div class="mx-auto" style="max-width:460px">
        <div class="pk-card p-4 p-md-5 text-center fade-up">
            <span class="pk-icon violet mb-3" style="width:64px;height:64px;font-size:1.8rem;border-radius:18px"><i class="bi bi-receipt"></i></span>
            <div class="pk-eyebrow justify-content-center mb-1"><i class="bi bi-envelope-open"></i> Bill invitation</div>
            <h1 class="h3 mb-1">{{ $bill->title }}</h1>
            <p class="pk-muted mb-4">{{ \Illuminate\Support\Str::of($owner->name)->before(' ') }} invited you to split this bill{{ $bill->restaurant_name ? ' from '.$bill->restaurant_name : '' }}.</p>
            <ul class="bl-receipt text-start mb-4">
                <li><span><i class="bi bi-person me-1"></i>Host</span><span>{{ $owner->name }}</span></li>
                <li><span><i class="bi bi-people me-1"></i>People so far</span><span>{{ $people }}</span></li>
                <li><span><i class="bi bi-calendar3 me-1"></i>Created</span><span>{{ $bill->created_at->format('M j, Y') }}</span></li>
            </ul>
            @include('bills.partials.errors')
            <form method="POST" action="{{ route('bills.join.confirm', $token) }}">
                @csrf
                <button class="btn btn-pk w-100" type="submit"><i class="bi bi-check2-circle me-1"></i>Join this bill</button>
            </form>
            <a href="{{ route('bills.index') }}" class="btn btn-ghost w-100 mt-2">Not now</a>
            <p class="bl-sub mt-3 mb-0">Joining shows you everyone's shares on this bill and lets you record your payment. It never affects any queue.</p>
        </div>
    </div>
</div>
@endsection
