<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        $completed = Ticket::where('status', 'completed')->whereNotNull('completed_at');
        $stats = [
            'users' => User::count(),
            'active_queues' => Service::where('is_active', true)->count(),
            'waiting' => Service::all()->sum(fn ($s) => $s->waitingCount()),
            'live_entries' => Ticket::whereIn('status', Ticket::ACTIVE)->count(),
            'completed' => (clone $completed)->count(),
            'avg_service' => round((clone $completed)->whereNotNull('called_at')->get()
                ->avg(fn ($t) => ($t->serving_at ?? $t->called_at)->diffInSeconds($t->completed_at) / 60) ?? 0, 1),
        ];
        // Portable across SQLite/MySQL: bucket by hour in PHP.
        $peak = Ticket::where('created_at', '>=', now()->subDays(30))->pluck('created_at')
            ->countBy(fn ($d) => $d->format('H'))->sortDesc()->map(fn ($c, $h) => ['hour' => $h, 'c' => $c])->first();
        $users = User::with('staffAssignments.location')->orderByRaw("case role when 'admin' then 0 when 'staff' then 1 else 2 end")->orderBy('name')->get();
        $locations = Location::orderBy('name')->get();
        $audit = AuditLog::with('actor')->latest('id')->take(15)->get();

        return view('pages.admin', compact('stats', 'peak', 'users', 'locations', 'audit'));
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(User::ROLES)]]);
        abort_if($user->is($request->user()) && $data['role'] !== 'admin', 422, 'You cannot remove your own admin role.');
        $old = $user->role;
        $user->forceFill(['role' => $data['role']])->save();
        AuditLog::record('role_changed', $user, ['from' => $old, 'to' => $data['role']]);

        return back()->with('ok', "{$user->name} is now {$data['role']}.");
    }

    public function assign(Request $request, User $user)
    {
        $data = $request->validate(['location_id' => ['required', 'exists:locations,id']]);
        abort_unless($user->isStaff(), 422, 'Only staff accounts can be assigned to a location.');
        StaffAssignment::updateOrCreate(['user_id' => $user->id, 'location_id' => $data['location_id']], ['is_approved' => true]);
        AuditLog::record('staff_assigned', $user, ['location_id' => (int) $data['location_id']]);

        return back()->with('ok', "{$user->name} assigned.");
    }

    public function unassign(Request $request, StaffAssignment $assignment)
    {
        AuditLog::record('staff_unassigned', $assignment->user, ['location_id' => $assignment->location_id]);
        $assignment->delete();

        return back()->with('ok', 'Assignment removed.');
    }
}
