@extends('platform.layouts.console')
@section('title', 'Organizations — प्रतीक्षा')

@section('console')
@php $typeIcon = ['government' => 'bi-bank', 'bank' => 'bi-piggy-bank', 'hospital' => 'bi-hospital', 'ward' => 'bi-house-door', 'private' => 'bi-shop']; @endphp
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-buildings"></i> Operations</span>
        <h1>Organizations</h1>
        <p>Each organization owns its locations, services and counters. Organization admins only see their own.</p>
    </div>
    @if($canCreate)
        <button class="btn btn-pk" type="button" data-bs-toggle="collapse" data-bs-target="#newOrg" aria-expanded="{{ $errors->any() ? 'true' : 'false' }}" aria-controls="newOrg"><i class="bi bi-plus-lg me-1"></i> New organization</button>
    @endif
</div>

@if($canCreate)
    <div class="collapse {{ $errors->any() ? 'show' : '' }} mb-3" id="newOrg">
        <form method="POST" action="{{ route('platform.manage.organizations.store') }}" class="pk-card pf-panel pf-form">
            @csrf
            <div class="pf-panel-head"><h2><i class="bi bi-plus-circle text-primary"></i> New organization</h2><span class="pf-demo">Saved as demo</span></div>
            <div class="pf-panel-body">
                @include('platform.manage.partials.org-fields', ['org' => new \App\Models\Organization(['type' => 'government', 'settings' => ['allow_queue_exchange' => true]])])
                <div class="d-flex justify-content-end mt-3"><button class="btn btn-pk"><i class="bi bi-check2 me-1"></i> Create organization</button></div>
            </div>
        </form>
    </div>
@endif

<section class="pk-card pf-panel reveal" aria-label="Organizations">
    @if($organizations->isEmpty())
        <div class="pf-empty"><span class="pk-icon mb-2"><i class="bi bi-buildings"></i></span><h3 class="h6 mb-1">No organizations</h3><p class="pk-muted small mb-0">{{ $canCreate ? 'Create the first one above.' : 'Ask a platform admin to add you to an organization.' }}</p></div>
    @else
        <div class="table-responsive">
            <table class="table pf-tbl pf-stack">
                <thead><tr><th>Organization</th><th>Type</th><th>Locations</th><th>Admins</th><th>Queue exchange</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @foreach($organizations as $org)
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <span class="pk-icon" style="width:36px;height:36px;font-size:.95rem"><i class="bi {{ $typeIcon[$org->type] ?? 'bi-building' }}"></i></span>
                                <div class="min-w-0"><div class="fw-bold">{{ $org->name }}</div><div class="sub">{{ $org->contact_email ?: 'No contact email' }}</div></div>
                            </div>
                        </td>
                        <td data-label="Type"><span class="pf-badge info">{{ ucfirst($org->type) }}</span></td>
                        <td data-label="Locations">{{ $org->locations_count }}</td>
                        <td data-label="Admins">{{ $org->members_count }}</td>
                        <td data-label="Queue exchange">
                            @if($org->setting('allow_queue_exchange'))<span class="pf-badge ok"><i class="bi bi-check2"></i> Allowed</span>@else<span class="pf-badge muted"><i class="bi bi-slash-circle"></i> Off</span>@endif
                        </td>
                        <td class="text-md-end"><a href="{{ route('platform.manage.organizations.show', $org) }}" class="btn btn-ghost btn-xs">Manage <i class="bi bi-chevron-right"></i></a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
@endsection
