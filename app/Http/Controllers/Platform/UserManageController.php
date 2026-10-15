<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Platform admins only (route: role:admin). */
class UserManageController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $users = User::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->when(in_array($role, User::ROLES, true), fn ($w) => $w->where('role', $role))
            ->withCount('tickets')
            ->orderByRaw("case role when 'admin' then 0 when 'staff' then 1 else 2 end")->orderBy('name')
            ->paginate(25)->withQueryString();
        $orgAdmins = DB::table('organization_user')->join('organizations', 'organizations.id', '=', 'organization_user.organization_id')
            ->whereIn('organization_user.user_id', $users->pluck('id'))->get(['organization_user.user_id', 'organizations.name'])
            ->groupBy('user_id');
        $counts = User::selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role');

        return view('platform.manage.users', compact('users', 'q', 'role', 'orgAdmins', 'counts'));
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)]]);
        if ($user->is($request->user()) && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'You cannot remove your own admin role.']);
        }
        $old = $user->role;
        if ($old === $data['role']) {
            return back()->with('ok', "{$user->name} is already {$old}.");
        }
        $user->forceFill(['role' => $data['role']])->save();
        AuditLog::record('role_changed', $user, ['from' => $old, 'to' => $data['role'], 'via' => 'platform_console']);

        return back()->with('ok', "{$user->name} is now {$data['role']}.");
    }
}
