<?php

namespace App\Http\Controllers\Explore;

use App\Http\Controllers\Cafes\CafeController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Explore\RecommendationQueryRequest;
use App\Models\ExploreDismissal;
use App\Models\ExplorePreference;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Services\Cafes\CafeImages;
use App\Services\Explore\ExploreLinks;
use App\Services\Explore\PlaceCategories;
use App\Services\Explore\RecommendationService;
use App\Services\Explore\TravelEstimator;
use Illuminate\Support\Facades\Route;

class ExploreController extends Controller
{
    public function __construct(private RecommendationService $recs, private TravelEstimator $travel) {}

    /**
     * Resolve the explore context: active ticket, the location being explored, remaining minutes.
     * Queue time only applies to the location the user is actually queued at.
     */
    private function context(RecommendationQueryRequest $request): array
    {
        $user = $request->user();
        $ticket = $this->recs->activeTicket($user, $request->integer('ticket') ?: null);
        $location = $request->filled('location')
            ? Location::findOrFail($request->integer('location'))
            : ($ticket?->service->location ?? Location::orderBy('id')->first());
        abort_unless($location, 404, 'No locations yet.');

        $queuedHere = $ticket && $ticket->service->location_id === $location->id;
        $manual = $request->filled('minutes') ? $request->integer('minutes') : null;
        $remaining = $this->recs->remainingFor($queuedHere ? $ticket : null, $manual);

        return [$user, $ticket, $location, $queuedHere, $remaining];
    }

    public function index(RecommendationQueryRequest $request)
    {
        [$user, $ticket, $location, $queuedHere, $remaining] = $this->context($request);

        $places = $this->recs->candidates($location)
            ->map(fn (NearbyPlace $p) => $this->recs->present($p, $location, null, $remaining['minutes']) + [
                'ride_url' => ExploreLinks::ride($p),
                'preorder_url' => ExploreLinks::preorder($p),
            ])->values();
        $recommendations = $this->recs->recommend($user, $location, $remaining['minutes']);

        return view('explore.index', [
            'location' => $location,
            'ticket' => $ticket,
            'queuedHere' => $queuedHere,
            'remaining' => $remaining,
            'places' => $places,
            'recommendations' => $recommendations,
            'prefs' => ExplorePreference::for($user),
            'dismissedIds' => ExploreDismissal::where('user_id', $user->id)->active()->pluck('nearby_place_id')->all(),
            'locations' => Location::orderBy('name')->get(['id', 'name', 'area', 'type']),
            'chips' => collect(PlaceCategories::chips())->filter(fn ($c) => $places->contains('category', $c['key']))->values(),
            'isHospital' => HospitalController::isHospital($location),
            'buffer' => (int) config('explore.safety_buffer_minutes', 8),
            'provider' => $this->travel->provider(),
        ]);
    }

    /** JSON: recommendations (+ place distances) recomputed from the user's chosen origin. Origin is never stored. */
    public function recommendations(RecommendationQueryRequest $request)
    {
        [$user, $ticket, $location, $queuedHere, $remaining] = $this->context($request);
        $origin = $request->origin();
        $rec = $this->recs->recommend($user, $location, $remaining['minutes'], $origin);
        $places = $this->recs->candidates($location)
            ->map(fn (NearbyPlace $p) => $this->recs->present($p, $location, $origin, $remaining['minutes']))
            ->map(fn (array $p) => array_intersect_key($p, array_flip(['id', 'distance_m', 'walk_there', 'walk_back', 'total', 'spare', 'fits', 'explanation'])))
            ->values();

        return response()->json([
            'message' => $rec['message'],
            'data' => [
                'location_id' => $location->id,
                'remaining' => $remaining['minutes'],
                'status' => $remaining['status'],
                'buffer' => $rec['buffer'],
                'enabled' => $rec['enabled'],
                'items' => $rec['items'],
                'places' => $places,
            ],
        ]);
    }

    /** JSON: travel estimate to one place. Geometry only when a real routing provider returned a path. */
    public function travel(RecommendationQueryRequest $request, NearbyPlace $place)
    {
        $location = Location::findOrFail($place->location_id);
        [$lat, $lng] = $request->origin() ?? [(float) $location->lat, (float) $location->lng];
        $est = $this->travel->estimate($lat, $lng, (float) $place->lat, (float) $place->lng);

        return response()->json([
            'message' => 'Travel estimate calculated',
            'data' => $est + ['line_label' => $est['geometry'] ? 'walking route (approximate)' : TravelEstimator::LINE_LABEL],
        ]);
    }

    public function place(RecommendationQueryRequest $request, NearbyPlace $place)
    {
        // Cafés and restaurants open the café hub (menu & pre-order, split a bill, your order, directions).
        if (CafeImages::isCafe($place) && Route::has('cafes.show')) {
            return app(CafeController::class)->show($request, $place);
        }

        $user = $request->user();
        $location = Location::findOrFail($place->location_id);
        $ticket = $this->recs->activeTicket($user);
        $queuedHere = $ticket && $ticket->service->location_id === $location->id;
        $remaining = $this->recs->remainingFor($queuedHere ? $ticket : null);
        $item = $this->recs->present($place, $location, null, $remaining['minutes']);
        $est = $this->travel->estimate((float) $location->lat, (float) $location->lng, (float) $place->lat, (float) $place->lng);

        return view('explore.place', [
            'place' => $place,
            'item' => $item,
            'location' => $location,
            'ticket' => $queuedHere ? $ticket : null,
            'remaining' => $remaining,
            'estimate' => $est,
            'rideUrl' => ExploreLinks::ride($place),
            'preorderUrl' => ExploreLinks::preorder($place),
            'dismissed' => ExploreDismissal::where('user_id', $user->id)->where('nearby_place_id', $place->id)->active()->exists(),
            'buffer' => (int) config('explore.safety_buffer_minutes', 8),
        ]);
    }
}
