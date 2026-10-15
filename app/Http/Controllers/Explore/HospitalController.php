<?php

namespace App\Http\Controllers\Explore;

use App\Http\Controllers\Controller;
use App\Models\HospitalProfile;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Services\Explore\ExploreLinks;
use App\Services\Explore\PlaceCategories;
use App\Services\Explore\RecommendationService;
use Illuminate\Http\Request;

/**
 * Hospital assistance: emergency first, then official (demo) hospital information,
 * nearby pharmacies and transport. No diagnoses, no medicine recommendations.
 */
class HospitalController extends Controller
{
    public static function isHospital(?Location $location): bool
    {
        return $location !== null && strcasecmp(trim((string) $location->type), 'hospital') === 0;
    }

    public function show(Request $request, Location $location, RecommendationService $recs)
    {
        abort_unless(self::isHospital($location), 404);

        $places = $recs->candidates($location)
            ->map(fn (NearbyPlace $p) => $recs->present($p, $location));
        $byCat = fn (string $key) => $places->where('category', $key)->sortBy('distance_m')->values();

        $ticket = $recs->activeTicket($request->user());

        return view('explore.hospital', [
            'location' => $location,
            'profile' => HospitalProfile::where('location_id', $location->id)->first(),
            'services' => $location->services()->where('is_active', true)->orderBy('name')->get(),
            'pharmacies' => $byCat('pharmacy'),
            'transport' => $byCat('transport'),
            'emergency' => config('explore.emergency_numbers', []),
            'rideUrl' => ExploreLinks::rideToLocation($location),
            'ticket' => $ticket && $ticket->service->location_id === $location->id ? $ticket : null,
            'pharmacyIcon' => PlaceCategories::icon('pharmacy'),
        ]);
    }
}
