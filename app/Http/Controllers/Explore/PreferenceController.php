<?php

namespace App\Http\Controllers\Explore;

use App\Http\Controllers\Controller;
use App\Http\Requests\Explore\UpdatePreferencesRequest;
use App\Models\ExploreDismissal;
use App\Models\ExplorePreference;
use App\Models\NearbyPlace;
use App\Services\Explore\PlaceCategories;
use Illuminate\Http\Request;

/** Everything here is scoped to the signed-in user — no user ids are accepted from input. */
class PreferenceController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $dismissals = ExploreDismissal::where('user_id', $user->id)->active()->with('place')->latest('id')->get();

        return view('explore.preferences', [
            'prefs' => ExplorePreference::for($user),
            'categories' => collect(PlaceCategories::recommendable())->map(fn ($k) => ['key' => $k, 'label' => PlaceCategories::label($k), 'icon' => PlaceCategories::icon($k), 'color' => PlaceCategories::color($k)]),
            'dismissals' => $dismissals,
            'dismissDays' => (int) config('explore.dismiss_days', 7),
        ]);
    }

    public function update(UpdatePreferencesRequest $request)
    {
        $data = $request->validated();
        $cats = array_values(array_unique($data['categories'] ?? []));
        $all = PlaceCategories::recommendable();

        $prefs = ExplorePreference::where('user_id', $request->user()->id)->first() ?? new ExplorePreference;
        $prefs->user_id = $request->user()->id;
        $enabled = (bool) $data['suggestions_enabled'];
        $prefs->suggestions_enabled = $enabled;
        // Turning suggestions off (category boxes disabled, so not submitted) keeps the saved categories.
        if ($enabled || $request->has('categories')) {
            // Every category ticked = "all" (also includes categories added later).
            $prefs->categories = count(array_diff($all, $cats)) === 0 ? null : $cats;
        }
        $prefs->save();

        $msg = $prefs->suggestions_enabled ? 'Explore preferences saved.' : 'Location-based suggestions are now off.';

        return $request->expectsJson()
            ? response()->json(['message' => $msg, 'data' => ['suggestions_enabled' => $prefs->suggestions_enabled, 'categories' => $prefs->preferredCategories()]])
            : back()->with('ok', $msg);
    }

    public function dismiss(Request $request, NearbyPlace $place)
    {
        $days = (int) config('explore.dismiss_days', 7);
        $d = ExploreDismissal::where('user_id', $request->user()->id)->where('nearby_place_id', $place->id)->first() ?? new ExploreDismissal;
        $d->user_id = $request->user()->id;
        $d->nearby_place_id = $place->id;
        $d->expires_at = now()->addDays($days);
        $d->save();

        $msg = "Hidden “{$place->name}” from your suggestions for {$days} days.";

        return $request->expectsJson()
            ? response()->json(['message' => $msg, 'data' => ['dismissal_id' => $d->id, 'place_id' => $place->id, 'expires_at' => $d->expires_at->toIso8601String()]])
            : back()->with('ok', $msg);
    }

    public function undismiss(Request $request, int $dismissal)
    {
        // Scoped lookup: another user's dismissal id is simply "not found".
        $d = ExploreDismissal::where('user_id', $request->user()->id)->findOrFail($dismissal);
        $d->delete();

        $msg = 'Suggestion restored.';

        return $request->expectsJson()
            ? response()->json(['message' => $msg, 'data' => ['place_id' => $d->nearby_place_id]])
            : back()->with('ok', $msg);
    }
}
