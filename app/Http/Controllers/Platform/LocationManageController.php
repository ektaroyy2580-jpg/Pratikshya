<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ScopesToOrganization;
use App\Http\Requests\Platform\LocationRequest;
use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocationManageController extends Controller
{
    use ScopesToOrganization;

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $orgId = $request->integer('organization') ?: null;
        $locations = $this->scopedLocations()
            ->withCount(['services', 'counters', 'places'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('area', 'like', "%{$q}%")))
            ->when($orgId, fn ($w) => $w->where('organization_id', $orgId))
            ->orderBy('name')->paginate(20)->withQueryString();
        $organizations = $this->scopedOrganizations();
        $orgNames = $organizations->pluck('name', 'id');

        return view('platform.manage.locations', compact('locations', 'organizations', 'orgNames', 'q', 'orgId'));
    }

    public function create()
    {
        $organizations = $this->scopedOrganizations();
        abort_if($organizations->isEmpty(), 403, 'You do not manage any organization yet.');

        return view('platform.manage.location-form', ['location' => new Location(['lat' => 27.7000, 'lng' => 85.3200, 'icon' => 'bi-building-fill']), 'organizations' => $organizations, 'org' => null]);
    }

    public function store(LocationRequest $request)
    {
        $data = $request->validated();
        $this->authorizeOrganization((int) $data['organization_id']);
        $location = new Location;
        $location->forceFill($data)->save();
        AuditLog::record('location_created', $location, ['organization_id' => (int) $data['organization_id']]);

        return redirect()->route('platform.manage.locations.edit', $location)->with('ok', 'Location created. Add its services and counters below.');
    }

    public function edit(Location $location)
    {
        $this->authorizeLocation($location->id);
        $location->load(['services' => fn ($q) => $q->orderBy('name'), 'counters' => fn ($q) => $q->orderBy('number'), 'counters.service']);

        return view('platform.manage.location-form', [
            'location' => $location,
            'organizations' => $this->scopedOrganizations(),
            'org' => $location->organization_id ? Organization::find($location->organization_id) : null,
        ]);
    }

    public function update(LocationRequest $request, Location $location)
    {
        $this->authorizeLocation($location->id);
        $data = $request->validated();
        $this->authorizeOrganization((int) $data['organization_id']);
        $location->forceFill($data);
        $changes = array_keys($location->getDirty());
        $location->save();
        AuditLog::record('location_updated', $location, ['fields' => $changes]);

        return back()->with('ok', 'Location saved.');
    }

    public function storeCounter(Request $request, Location $location)
    {
        $this->authorizeLocation($location->id);
        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:99', Rule::unique('counters')->where('location_id', $location->id)],
            'label' => ['nullable', 'string', 'max:60'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('location_id', $location->id)],
        ], ['number.unique' => 'That counter number already exists at this location.']);
        $counter = Counter::create([
            'location_id' => $location->id, 'number' => $data['number'],
            'label' => $data['label'] ?: 'Counter '.$data['number'],
            'service_id' => $data['service_id'] ?? null, 'is_open' => true,
        ]);
        AuditLog::record('counter_created', $counter, ['location_id' => $location->id]);

        return back()->with('ok', "Counter {$counter->number} added.");
    }

    public function updateCounter(Request $request, Counter $counter)
    {
        $this->authorizeLocation($counter->location_id);
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'service_id' => ['nullable', 'integer', Rule::exists('services', 'id')->where('location_id', $counter->location_id)],
        ]);
        $counter->update(['label' => $data['label'] ?: 'Counter '.$counter->number, 'service_id' => $data['service_id'] ?? null]);
        AuditLog::record('counter_updated', $counter, ['service_id' => $counter->service_id]);

        return back()->with('ok', "Counter {$counter->number} saved.");
    }

    public function toggleCounter(Counter $counter)
    {
        $this->authorizeLocation($counter->location_id);
        $counter->update(['is_open' => ! $counter->is_open]);
        AuditLog::record($counter->is_open ? 'counter_opened' : 'counter_closed', $counter, ['location_id' => $counter->location_id]);

        return back()->with('ok', "Counter {$counter->number} is now ".($counter->is_open ? 'open' : 'closed').'.');
    }

    private function authorizeOrganization(int $orgId): void
    {
        abort_unless($this->scopedOrganizations()->contains('id', $orgId), 403, 'You cannot assign locations to that organization.');
    }
}
