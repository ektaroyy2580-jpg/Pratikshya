<?php

namespace App\Http\Controllers\Cafes;

use App\Http\Controllers\Controller;
use App\Models\NearbyPlace;
use App\Services\Cafes\CafeHub;
use App\Services\Cafes\CafeImages;
use Illuminate\Http\Request;

/**
 * The café page — the hub for a café/restaurant near a queue: photos, fits-your-wait,
 * Menu & Pre-order, Split a bill here, Your order here, and Ride there.
 */
class CafeController extends Controller
{
    public function __construct(private CafeHub $hub) {}

    public function show(Request $request, NearbyPlace $place)
    {
        if (! CafeImages::isCafe($place)) {
            return redirect()->route('explore.place', $place);
        }

        return view('cafes.show', $this->hub->build($request->user(), $place));
    }

    /** Photo credits (public: attribution must be reachable without an account). */
    public function credits()
    {
        $images = collect(CafeImages::catalog())->filter(fn ($i) => is_file(public_path($i['path'])));

        return view('cafes.credits', [
            'groups' => [
                'Café & restaurant scenes' => $images->filter(fn ($i) => str_starts_with($i['key'], 'cafes/'))->values(),
                'Dishes' => $images->filter(fn ($i) => str_starts_with($i['key'], 'dishes/'))->values(),
            ],
        ]);
    }
}
