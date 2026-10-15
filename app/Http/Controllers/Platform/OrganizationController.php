<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ScopesToOrganization;
use App\Http\Requests\Platform\OrganizationRequest;
use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\NearbyPlace;
use App\Models\Organization;
use App\Models\Service;
use App\Models\StaffAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationController extends Controller
{
    use ScopesToOrganization;

    /** Console home: scoped counts + shortcuts. */
    public function overview(Request $request)
    {
        $locIds = $this->scopedLocations()->pluck('id');
        $stats = [
            'organizations' => $this->scopedOrganizations()->count(),
            'locations' => $locIds->count(),
            'services' => Service::whereIn('location_id', $locIds)->count(),
            'paused' => Service::whereIn('location_id', $locIds)->where('is_paused', true)->count(),
            'counters_open' => Counter::whereIn('location_id', $locIds)->where('is_open', true)->count(),
            'counters' => Counter::whereIn('location_id', $locIds)->count(),
            'staff' => StaffAssignment::whereIn('location_id', $locIds)->count(),
            'pending' => StaffAssignment::whereIn('location_id', $locIds)->where('is_approved', false)->count(),
            'places' => NearbyPlace::whereIn('location_id', $locIds)->count(),
        ];
        $organizations = $this->scopedOrganizations()->loadCount('locations');
        $isPlatformAdmin = $this->access()->isPlatformAdmin($request->user());

        return view('platform.manage.overview', compact('stats', 'organizations', 'isPlatformAdmin'));
    }

    public function index(Request $request)
    {
        $organizations = $this->scopedOrganizations()->loadCount(['locations', 'members']);

        return view('platform.manage.organizations', [
            'organizations' => $organizations,
            'canCreate' => $request->user()->can('create', Organization::class),
        ]);
    }

    public function show(Request $request, Organization $organization)
    {
        $this->authorize('view', $organization);
        $organization->load(['locations' => fn ($q) => $q->withCount('services')->orderBy('name'), 'members']);

        return view('platform.manage.organization', [
            'org' => $organization,
            'canManageMembers' => $request->user()->can('manageMembers', $organization),
            'canDelete' => $request->user()->can('delete', $organization),
        ]);
    }

    public function store(OrganizationRequest $request)
    {
        $this->authorize('create', Organization::class);
        $data = $request->validated();
        $org = Organization::create([
            'name' => $data['name'], 'type' => $data['type'],
            'slug' => $this->uniqueSlug($data['name']),
            'contact_email' => $data['contact_email'] ?? null, 'contact_phone' => $data['contact_phone'] ?? null, 'website' => $data['website'] ?? null,
            'settings' => ['allow_queue_exchange' => $request->boolean('allow_queue_exchange', true)],
            'is_demo' => true,
        ]);
        AuditLog::record('organization_created', $org, ['name' => $org->name]);

        return redirect()->route('platform.manage.organizations.show', $org)->with('ok', 'Organization created.');
    }

    public function update(OrganizationRequest $request, Organization $organization)
    {
        $this->authorize('update', $organization);
        $data = $request->validated();
        $organization->fill([
            'name' => $data['name'], 'type' => $data['type'],
            'contact_email' => $data['contact_email'] ?? null, 'contact_phone' => $data['contact_phone'] ?? null, 'website' => $data['website'] ?? null,
            'settings' => array_merge($organization->settings ?? [], ['allow_queue_exchange' => $request->boolean('allow_queue_exchange')]),
        ]);
        $changes = array_keys($organization->getDirty());
        $organization->save();
        AuditLog::record('organization_updated', $organization, ['fields' => $changes]);

        return back()->with('ok', 'Organization saved.');
    }

    public function destroy(Organization $organization)
    {
        $this->authorize('delete', $organization);
        abort_if($organization->locations()->exists(), 422, 'Move or reassign this organization\'s locations before deleting it.');
        AuditLog::record('organization_deleted', $organization, ['name' => $organization->name]);
        $organization->delete();

        return redirect()->route('platform.manage.organizations')->with('ok', 'Organization deleted.');
    }

    public function addMember(Request $request, Organization $organization)
    {
        $this->authorize('manageMembers', $organization);
        $data = $request->validate(['email' => ['required', 'email', 'max:120']]);
        $user = User::where('email', $data['email'])->first();
        if (! $user) {
            return back()->withErrors(['email' => 'No account uses that email.'])->withInput();
        }
        DB::table('organization_user')->updateOrInsert(
            ['organization_id' => $organization->id, 'user_id' => $user->id],
            ['role' => 'org_admin', 'created_at' => now(), 'updated_at' => now()]
        );
        AuditLog::record('org_admin_added', $user, ['organization_id' => $organization->id]);

        return back()->with('ok', "{$user->name} is now an organization admin.");
    }

    public function removeMember(Organization $organization, User $user)
    {
        $this->authorize('manageMembers', $organization);
        DB::table('organization_user')->where('organization_id', $organization->id)->where('user_id', $user->id)->delete();
        AuditLog::record('org_admin_removed', $user, ['organization_id' => $organization->id]);

        return back()->with('ok', 'Organization admin removed.');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'organization';
        $slug = $base;
        for ($i = 2; Organization::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
