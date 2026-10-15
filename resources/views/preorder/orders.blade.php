@extends('layouts.app')
@section('title', 'My food orders — प्रतीक्षा')

@push('head')
    @include('preorder.partials.head')
@endpush

@section('content')
@use('App\Services\Preorder\Money')
@php $tone = ['placed' => 'waiting', 'accepted' => 'serving', 'preparing' => 'serving', 'ready' => 'called', 'collected' => 'done', 'cancelled' => 'done', 'rejected' => 'done']; @endphp
<header class="pk-page-head">
    <div class="container d-flex flex-wrap align-items-end justify-content-between gap-3">
        <div>
            <a href="{{ route('preorder.index') }}" class="pk-breadcrumb fade-up d-inline-block mb-3"><i class="bi bi-arrow-left me-1"></i>Cafés</a>
            <h1 class="h2 mb-1 fade-up">My food orders</h1>
            <p class="pk-muted mb-0 fade-up">Every preorder you've placed, newest first.</p>
        </div>
        <a href="{{ route('preorder.index') }}" class="btn btn-pk fade-up"><i class="bi bi-plus-lg me-1"></i>New order</a>
    </div>
</header>

<div class="container py-4">
    @forelse($orders as $i => $o)
        <a href="{{ route('preorder.order', $o) }}" class="pk-card lift d-grid align-items-center gap-3 p-3 mb-3 text-decoration-none reveal" style="grid-template-columns:64px 1fr auto;color:inherit;--d:{{ $i * .04 }}s">
            @include('preorder.partials.food-art', ['art' => $o->items->first()?->art ?? $o->restaurant->art, 'class' => 'sq', 'label' => $o->restaurant->name, 'photo' => $o->restaurant->image_path])
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2"><span class="fw-bold">{{ $o->restaurant->name }}</span><span class="small pk-muted">{{ $o->reference() }}</span></div>
                <div class="small pk-muted text-truncate">{{ $o->items->map(fn ($it) => $it->qty.'× '.$it->name)->implode(', ') }}</div>
                <div class="small pk-muted">{{ $o->created_at->format('M j, g:i A') }} · {{ $o->payment_status === 'demo_paid' ? 'Demo paid' : 'Cash on pickup' }}</div>
            </div>
            <div class="text-end">
                <div class="fw-bold mb-1">{{ Money::format($o->total) }}</div>
                <span class="pk-status {{ $tone[$o->status] ?? 'done' }}">{{ ucfirst($o->status) }}</span>
            </div>
        </a>
    @empty
        <div class="pk-card p-5 text-center reveal">
            <span class="pk-icon violet mb-3"><i class="bi bi-receipt"></i></span>
            <div class="fw-bold">No orders yet</div>
            <p class="small pk-muted">Order momo, chiya or a Thakali set from a café near your queue.</p>
            <a href="{{ route('preorder.index') }}" class="btn btn-pk">Browse cafés</a>
        </div>
    @endforelse
    <div class="mt-3">{{ $orders->links('pagination::bootstrap-5') }}</div>
</div>
@endsection
