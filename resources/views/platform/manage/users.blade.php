@extends('platform.layouts.console')
@section('title', 'Users & roles — प्रतीक्षा')

@section('console')
<div class="pf-title fade-up">
    <div>
        <span class="pk-eyebrow"><i class="bi bi-people"></i> Platform</span>
        <h1>Users & roles</h1>
        <p>Search accounts and change roles. Every change is audited; you cannot remove your own admin role.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <span class="pf-badge muted">{{ $counts['user'] ?? 0 }} {{ \Illuminate\Support\Str::plural('user', $counts['user'] ?? 0) }}</span>
        <span class="pf-badge violet">{{ $counts['staff'] ?? 0 }} staff</span>
        <span class="pf-badge ink">{{ $counts['admin'] ?? 0 }} {{ \Illuminate\Support\Str::plural('admin', $counts['admin'] ?? 0) }}</span>
    </div>
</div>

<section class="pk-card pf-panel reveal" aria-label="Users">
    <div class="pf-panel-head">
        <form method="GET" class="pf-filter w-100" role="search">
            <label class="visually-hidden" for="uQ">Search users</label>
            <input id="uQ" class="form-control" style="max-width:280px" type="search" name="q" value="{{ $q }}" placeholder="Name or email">
            <label class="visually-hidden" for="uRole">Role</label>
            <select id="uRole" name="role" class="form-select" style="max-width:160px">
                <option value="">All roles</option>
                @foreach(\App\Models\User::ROLES as $r)<option value="{{ $r }}" @selected($role === $r)>{{ ucfirst($r) }}</option>@endforeach
            </select>
            <button class="btn btn-ghost" data-no-busy><i class="bi bi-search me-1"></i>Search</button>
            @if($q || $role)<a href="{{ route('platform.manage.users') }}" class="small fw-semibold">Clear</a>@endif
        </form>
    </div>
    @if($users->isEmpty())
        <div class="pf-empty"><span class="pk-icon mb-2"><i class="bi bi-person-x"></i></span><h3 class="h6 mb-1">No matching users</h3><p class="pk-muted small mb-0">Try another name or email.</p></div>
    @else
        <div class="table-responsive">
            <table class="table pf-tbl pf-stack">
                <thead><tr><th>User</th><th>Role</th><th>Org admin of</th><th>Visits</th><th>Joined</th><th class="text-end">Change role</th></tr></thead>
                <tbody>
                @foreach($users as $u)
                    @php $self = $u->is(auth()->user()); @endphp
                    <tr>
                        <td>
                            <div class="fw-bold">{{ $u->name }} @if($self)<span class="pf-badge info ms-1">You</span>@endif</div>
                            <div class="sub">{{ $u->email }}</div>
                        </td>
                        <td data-label="Role"><span class="pf-badge {{ ['admin' => 'ink', 'staff' => 'violet'][$u->role] ?? 'muted' }}">{{ ucfirst($u->role) }}</span></td>
                        <td data-label="Org admin of" class="small">{{ $orgAdmins->get($u->id)?->pluck('name')->join(', ') ?: '—' }}</td>
                        <td data-label="Visits">{{ $u->tickets_count }}</td>
                        <td data-label="Joined" class="small pk-muted">{{ $u->created_at?->format('d M Y') }}</td>
                        <td class="text-md-end">
                            @if($self)
                                <span class="small pk-muted"><i class="bi bi-lock me-1"></i>Can't change own role</span>
                            @else
                                <form method="POST" action="{{ route('platform.manage.users.role', $u) }}" class="d-inline-flex gap-2" data-confirm="Change the role of {{ $u->name }}?">
                                    @csrf
                                    <label class="visually-hidden" for="role{{ $u->id }}">Role for {{ $u->name }}</label>
                                    <select class="form-select form-select-sm" id="role{{ $u->id }}" name="role" style="width:auto;min-width:112px">
                                        @foreach(\App\Models\User::ROLES as $r)<option value="{{ $r }}" @selected($u->role === $r)>{{ ucfirst($r) }}</option>@endforeach
                                    </select>
                                    <button class="btn btn-ghost btn-xs">Save</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($users->hasPages())<div class="pf-pager">{{ $users->links('pagination::bootstrap-5') }}</div>@endif
    @endif
</section>
@endsection
