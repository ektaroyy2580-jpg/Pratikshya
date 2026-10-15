<?php

namespace App\Http\Controllers\Preorder;

use App\Http\Controllers\Cafes\CafeController;
use App\Http\Controllers\Controller;
use App\Models\FoodOrder;
use App\Models\Restaurant;
use App\Services\Preorder\Cart;
use App\Services\Preorder\TimingCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class RestaurantController extends Controller
{
    public function __construct(private TimingCheck $timing, private Cart $cart)
    {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $ticket = $this->timing->contextTicket($user);

        $restaurants = Restaurant::participating()
            ->with(['location', 'nearbyPlace'])
            ->withCount(['items as available_count' => fn ($q) => $q->where('available', true)])
            ->orderBy('name')
            ->get();

        // "Fits your wait?" hint using each café's quickest available item.
        $hints = [];
        foreach ($restaurants as $r) {
            $quick = $r->quickestItem();
            $hints[$r->id] = $quick
                ? $this->timing->evaluate($r, [['prep_minutes' => $quick->prep_minutes, 'qty' => 1]], $ticket) + ['quick_item' => $quick->name]
                : null;
        }

        // Cafés near the user's current queue first.
        $hereId = $ticket?->service?->location_id;
        $restaurants = $restaurants->sortBy(fn ($r) => [(int) ($r->location_id !== $hereId), $r->name])->values();

        $activeOrders = FoodOrder::where('user_id', $user->id)->whereIn('status', FoodOrder::OPEN)->with('restaurant')->latest()->take(3)->get();
        $managesAny = Restaurant::managedBy($user)->exists();

        return view('preorder.index', [
            'restaurants' => $restaurants,
            'hints' => $hints,
            'ticket' => $ticket,
            'hereId' => $hereId,
            'activeOrders' => $activeOrders,
            'managesAny' => $managesAny,
            'cartSummary' => $this->cart->summary(),
            'cartRestaurant' => $this->cart->restaurant(),
        ]);
    }

    public function show(Request $request, Restaurant $restaurant)
    {
        // Non-participating businesses have no preorder page.
        abort_unless($restaurant->accepts_preorders, 404);

        // Cafés linked to an Explore place are ordered from inside the café page (same menu, cart and checkout).
        if ($restaurant->nearby_place_id && ($place = $restaurant->nearbyPlace) && \App\Services\Cafes\CafeImages::isCafe($place) && Route::has('cafes.show')) {
            return app(CafeController::class)->show($request, $place);
        }

        $restaurant->load(['location', 'nearbyPlace']);
        $menu = $restaurant->menus()->where('is_active', true)->with('categories.items')->first();
        $categories = $menu?->categories ?? collect();
        $uncategorised = $restaurant->items()->whereNull('menu_category_id')->orderBy('sort')->get();

        $ticket = $this->timing->contextTicket($request->user());
        $quick = $restaurant->quickestItem();
        $hint = $quick ? $this->timing->evaluate($restaurant, [['prep_minutes' => $quick->prep_minutes, 'qty' => 1]], $ticket) : null;

        $cart = $this->cart->summary();
        $cartRestaurant = $this->cart->restaurant();

        return view('preorder.restaurant', compact('restaurant', 'categories', 'uncategorised', 'ticket', 'hint', 'cart', 'cartRestaurant', 'quick'));
    }
}
