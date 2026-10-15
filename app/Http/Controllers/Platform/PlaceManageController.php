<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Platform\Concerns\ScopesToOrganization;
use App\Http\Requests\Platform\NearbyPlaceRequest;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\NearbyPlace;
use Illuminate\Http\Request;

/** Nearby places & business listings around managed locations. */
class PlaceManageController extends Controller
{
    use ScopesToOrganization;

    public function index(Request $request)
    {
        $locations = $this->scopedLocations()->orderBy('name')->get(['id', 'name']);
        $locId = $request->integer('location') ?: null;
        $q = trim((string) $request->query('q', ''));
        $listings = $request->query('type') === 'listings';
        $places = NearbyPlace::whereIn('location_id', $locations->pluck('id'))
            ->when($locId, fn ($w) => $w->where('location_id', $locId))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('category', 'like', "%{$q}%")))
            ->when($listings, fn ($w) => $w->where('is_business_listing', true))
            ->orderBy('location_id')->orderBy('name')->paginate(25)->withQueryString();
        $locNames = $locations->pluck('name', 'id');

        return view('platform.manage.places', compact('locations', 'places', 'locNames', 'locId', 'q', 'listings'));
    }

    public function create(Request $request)
    {
        $locations = $this->scopedLocations()->orderBy('name')->get(['id', 'name', 'lat', 'lng']);
        abort_if($locations->isEmpty(), 403, 'You do not manage any location yet.');
        $loc = $locations->firstWhere('id', $request->integer('location')) ?? $locations->first();
        $place = new NearbyPlace(['location_id' => $loc->id, 'lat' => (float) $loc->lat, 'lng' => (float) $loc->lng, 'emoji' => '☕', 'walk_minutes' => 5, 'visit_minutes' => 15, 'rating' => 4.5]);

        return view('platform.manage.place-form', compact('place', 'locations'));
    }

    public function store(NearbyPlaceRequest $request)
    {
        $data = $this->payload($request);
        $this->authorizeLocation((int) $data['location_id']);
        $place = new NearbyPlace;
        $place->forceFill($data)->save();
        AuditLog::record('place_created', $place, ['location_id' => $place->location_id]);

        return redirect()->route('platform.manage.places', ['location' => $place->location_id])->with('ok', "“{$place->name}” added.");
    }

    public function edit(NearbyPlace $place)
    {
        $this->authorizeLocation($place->location_id);
        $locations = $this->scopedLocations()->orderBy('name')->get(['id', 'name', 'lat', 'lng']);

        return view('platform.manage.place-form', compact('place', 'locations'));
    }

    public function update(NearbyPlaceRequest $request, NearbyPlace $place)
    {
        $this->authorizeLocation($place->location_id);
        $data = $this->payload($request);
        $this->authorizeLocation((int) $data['location_id']);
        $place->forceFill($data);
        $changes = array_keys($place->getDirty());
        $place->save();
        AuditLog::record('place_updated', $place, ['fields' => $changes]);

        return back()->with('ok', 'Place saved.');
    }

    public function destroy(NearbyPlace $place)
    {
        $this->authorizeLocation($place->location_id);
        AuditLog::record('place_deleted', $place, ['name' => $place->name, 'location_id' => $place->location_id]);
        $place->delete();

        return redirect()->route('platform.manage.places')->with('ok', 'Place removed.');
    }

    private function payload(NearbyPlaceRequest $request): array
    {
        return array_merge($request->safe()->except('is_business_listing'), ['is_business_listing' => $request->boolean('is_business_listing')]);
    }
}
