@extends('platform.layouts.console')
@section('title', 'Staff assignments — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-person-badge"></i> Operations</span>
        <h1>Staff assignments</h1>
        <p>Staff can only operate queues at locations they are approved for.</p>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-4 order-xl-2">
        <form method="POST" action="{{ route('platform.manage.staff.assign') }}" class="pk-card pf-panel pf-form reveal">
            @csrf
            <div class="pf-panel-head"><h2><i class="bi bi-person-plus text-primary"></i> Assign staff</h2></div>
            <div class="pf-panel-body">
                @if($locations->isEmpty())
                    <p class="small pk-muted mb-0">You don't manage any location yet.</p>
                @else
                    <div class="mb-3">
                        <label class="form-label" for="st_email">Staff account email</label>
                        <input class="form-control @error('email') is-invalid @enderror" id="st_email" type="email" name="email" value="{{ old('email') }}" placeholder="staff@example.com" required>
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="st_loc">Location</label>
                        <select class="form-select" id="st_loc" name="location_id" required>
                            @foreach($locations as $l)<option value="{{ $l->id }}" @selected((int) old('location_id') === $l->id)>{{ $l->name }}</option>@endforeach
                        </select>
                    </div>
                    <button class="btn btn-pk w-100"><i class="bi bi-check2 me-1"></i> Assign & approve</button>
                    <p class="form-text mt-2 mb-0">Only accounts that already have the staff role can be assigned. Role changes are made by platform admins.</p>
                @endif
            </div>
        </form>
    </div>
    <div class="col-xl-8 order-xl-1">
        <section class="pk-card pf-panel reveal" aria-label="Assignments">
            <div class="pf-panel-head">
                <div class="pf-filter" role="tablist" aria-label="Filter by status">
                    @foreach(['' => 'All', 'pending' => 'Pending', 'approved' => 'Approved'] as $k => $lbl)
                        <a href="{{ route('platform.manage.staff', array_filter(['status' => $k])) }}" class="btn btn-xs {{ (string) $status === (string) $k ? 'btn-pk' : 'btn-ghost' }}" @if((string) $status === (string) $k) aria-current="page" @endif>{{ $lbl }}</a>
                    @endforeach
                </div>
                <span class="small pk-muted">{{ $assignments->total() }} total</span>
            </div>
            @if($assignments->isEmpty())
                <div class="pf-empty"><span class="pk-icon mb-2"><i class="bi bi-person-badge"></i></span><h3 class="h6 mb-1">No assignments</h3><p class="pk-muted small mb-0">Assign a staff account using the form.</p></div>
            @else
                <div class="table-responsive">
                    <table class="table pf-tbl pf-stack">
                        <thead><tr><th>Staff member</th><th>Location</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        @foreach($assignments as $a)
                            <tr>
                                <td><div class="fw-bold">{{ $a->user?->name ?? 'Unknown' }}</div><div class="sub">{{ $a->user?->email }}</div></td>
                                <td data-label="Location">{{ $a->location?->name }}</td>
                                <td data-label="Status">@if($a->is_approved)<span class="pf-badge ok"><i class="bi bi-check2"></i> Approved</span>@else<span class="pf-badge warn"><i class="bi bi-hourglass-split"></i> Pending</span>@endif</td>
                                <td class="text-md-end">
                                    <div class="d-inline-flex gap-2">
                                        <form method="POST" action="{{ route('platform.manage.staff.approve', $a) }}">@csrf<button class="btn btn-xs {{ $a->is_approved ? 'btn-ghost' : 'btn-pk' }}">{{ $a->is_approved ? 'Suspend' : 'Approve' }}</button></form>
                                        <form method="POST" action="{{ route('platform.manage.staff.remove', $a) }}" data-confirm="Remove this assignment?">@csrf<button class="btn btn-ghost btn-xs" aria-label="Remove assignment"><i class="bi bi-trash3"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($assignments->hasPages())<div class="pf-pager">{{ $assignments->links('pagination::bootstrap-5') }}</div>@endif
            @endif
        </section>
    </div>
</div>
@endsection
