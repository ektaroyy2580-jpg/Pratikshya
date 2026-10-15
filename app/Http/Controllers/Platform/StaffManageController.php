<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ScopesToOrganization;
use App\Models\AuditLog;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Staff assignments within the manager's scope. Only existing staff accounts can be
 * assigned (role changes stay with platform admins), so org admins can never elevate anyone.
 */
class StaffManageController extends Controller
{
    use ScopesToOrganization;

    public function index(Request $request)
    {
        $locations = $this->scopedLocations()->orderBy('name')->get(['id', 'name', 'organization_id']);
        $status = $request->query('status');
        $assignments = StaffAssignment::with(['user:id,name,email,role', 'location:id,name'])
            ->whereIn('location_id', $locations->pluck('id'))
            ->when($status === 'pending', fn ($q) => $q->where('is_approved', false))
            ->when($status === 'approved', fn ($q) => $q->where('is_approved', true))
            ->orderBy('is_approved')->latest('id')->paginate(25)->withQueryString();

        return view('platform.manage.staff', compact('locations', 'assignments', 'status'));
    }

    public function assign(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);
        $this->authorizeLocation((int) $data['location_id']);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! $user->isStaff()) {
            return back()->withErrors(['email' => 'No staff account uses that email. A platform admin must give the account the staff role first.'])->withInput();
        }
        $a = StaffAssignment::updateOrCreate(['user_id' => $user->id, 'location_id' => $data['location_id']], ['is_approved' => true]);
        AuditLog::record('staff_assigned', $user, ['location_id' => (int) $data['location_id'], 'via' => 'platform_console']);

        return back()->with('ok', "{$user->name} assigned to {$a->location->name}.");
    }

    public function toggleApproval(StaffAssignment $assignment)
    {
        $this->authorizeLocation($assignment->location_id);
        $assignment->update(['is_approved' => ! $assignment->is_approved]);
        AuditLog::record($assignment->is_approved ? 'staff_approved' : 'staff_suspended', $assignment->user, ['location_id' => $assignment->location_id]);

        return back()->with('ok', $assignment->is_approved ? 'Assignment approved.' : 'Assignment suspended.');
    }

    public function remove(StaffAssignment $assignment)
    {
        $this->authorizeLocation($assignment->location_id);
        AuditLog::record('staff_unassigned', $assignment->user, ['location_id' => $assignment->location_id, 'via' => 'platform_console']);
        $assignment->delete();

        return back()->with('ok', 'Assignment removed.');
    }
}
