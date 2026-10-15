<?php

namespace App\Services\Cafes;

use App\Models\BillParticipant;
use App\Models\ExploreDismissal;
use App\Models\FoodOrder;
use App\Models\GroupBill;
use App\Models\Location;
use App\Models\NearbyPlace;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\Explore\ExploreLinks;
use App\Services\Explore\RecommendationService;
use App\Services\Explore\TravelEstimator;
use App\Services\Preorder\Cart;
use App\Services\Preorder\Money;
use App\Services\Preorder\OrderPricing;
use App\Services\Preorder\TimingCheck;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/**
 * Everything the café page shows: photos, "fits your wait", the preorder menu + cart,
 * the user's orders here and the user's group bills split here. All user data is
 * scoped to the signed-in user.
 */
class CafeHub
{
    public function __construct(
        private RecommendationService $recs,
        private TravelEstimator $travel,
        private TimingCheck $timing,
        private Cart $cart,
        private OrderPricing $pricing,
    ) {}

    /** Participating (or listed) preorder restaurant for a place. */
    public static function restaurantFor(NearbyPlace $place): ?Restaurant
    {
        if (! class_exists(Restaurant::class) || ! Schema::hasTable('restaurants')) {
            return null;
        }

        return Restaurant::where('nearby_place_id', $place->id)->orderByDesc('accepts_preorders')->orderBy('id')->first();
    }

    /** Canonical café page URL (null when the café route is missing). */
    public static function url(NearbyPlace|int $place, ?string $section = null): ?string
    {
        if (! Route::has('cafes.show')) {
            return null;
        }

        return route('cafes.show', $place).($section ? '#'.$section : '');
    }

    /** Café page for a restaurant when it is linked to a nearby place, else its preorder menu. */
    public static function restaurantUrl(Restaurant $restaurant, ?string $section = 'menu'): string
    {
        return $restaurant->nearby_place_id && Route::has('cafes.show')
            ? route('cafes.show', $restaurant->nearby_place_id).($section ? '#'.$section : '')
            : route('preorder.restaurant', $restaurant);
    }

    public function build(User $user, NearbyPlace $place): array
    {
        $location = Location::findOrFail($place->location_id);
        $ticket = $this->recs->activeTicket($user);
        $queuedHere = $ticket && $ticket->service->location_id === $location->id;
        $remaining = $this->recs->remainingFor($queuedHere ? $ticket : null);
        $item = $this->recs->present($place, $location, null, $remaining['minutes']);
        $estimate = $this->travel->estimate((float) $location->lat, (float) $location->lng, (float) $place->lat, (float) $place->lng);

        $restaurant = self::restaurantFor($place);
        $participating = (bool) $restaurant?->accepts_preorders;

        $sections = collect();
        $hint = $quick = null;
        $cart = $this->cart->summary();
        $cartRestaurant = $this->cart->restaurant();
        $cartHere = $participating && $cartRestaurant && $cartRestaurant->id === $restaurant->id && ($cart['count'] ?? 0) > 0;
        $cartLines = [];
        $cartQuote = null;
        $cartTiming = null;

        if ($participating) {
            $menu = $restaurant->menus()->where('is_active', true)->with('categories.items')->first();
            $sections = ($menu?->categories ?? collect())->filter(fn ($c) => $c->items->isNotEmpty())->values();
            $uncategorised = $restaurant->items()->whereNull('menu_category_id')->orderBy('sort')->get();
            if ($uncategorised->isNotEmpty()) {
                $sections->push((object) ['id' => 'more', 'name' => 'More', 'items' => $uncategorised]);
            }
            $ctx = $this->timing->contextTicket($user);
            $quick = $restaurant->quickestItem();
            $hint = $quick ? $this->timing->evaluate($restaurant, [['prep_minutes' => $quick->prep_minutes, 'qty' => 1]], $ctx) : null;
            if ($cartHere) {
                $cartLines = $this->cart->lines();
                $cartQuote = $this->pricing->quote(array_map(fn ($l) => ['unit_paisa' => $l['unit_paisa'], 'qty' => $l['qty']], $cartLines));
                $cartTiming = $this->timing->evaluate($restaurant, $cartLines, $ctx);
            }
        }

        // ---- the signed-in user's orders here ----
        $openOrders = collect();
        $pastOrders = collect();
        $billOrder = null;
        if ($restaurant) {
            $mine = FoodOrder::where('user_id', $user->id)->where('restaurant_id', $restaurant->id)->with('items')->latest('id')->take(12)->get();
            $openOrders = $mine->filter(fn ($o) => $o->isOpen())->values();
            $pastOrders = $mine->reject(fn ($o) => $o->isOpen())->take(3)->values();
            $billOrder = $mine->first(fn ($o) => ! in_array($o->status, ['cancelled', 'rejected'], true));
        }

        // ---- the signed-in user's bills split here ----
        $bills = collect();
        if (Schema::hasColumn('group_bills', 'nearby_place_id')) {
            $billIds = BillParticipant::where('user_id', $user->id)->where('status', 'joined')->pluck('group_bill_id');
            $bills = GroupBill::whereIn('id', $billIds)->where('nearby_place_id', $place->id)
                ->with(['owner:id,name', 'joinedParticipants'])
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'settling' THEN 1 WHEN 'settled' THEN 2 ELSE 3 END")
                ->latest('id')->take(6)->get();
        }

        $billCreateUrl = Route::has('bills.create')
            ? route('bills.create', array_filter(['place' => $place->id, 'order' => $billOrder?->id]))
            : null;

        return [
            'place' => $place,
            'item' => $item,
            'location' => $location,
            'ticket' => $queuedHere ? $ticket : null,
            'otherTicket' => $ticket && ! $queuedHere ? $ticket : null,
            'remaining' => $remaining,
            'estimate' => $estimate,
            'gallery' => CafeImages::galleryFor($place),
            'theme' => CafeImages::themeFor($place->name, $place->category),
            'restaurant' => $restaurant,
            'participating' => $participating,
            'sections' => $sections,
            'hint' => $hint,
            'quick' => $quick,
            'cart' => $cart,
            'cartRestaurant' => $cartRestaurant,
            'cartHere' => $cartHere,
            'cartLines' => $cartLines,
            'cartQuote' => $cartQuote,
            'cartTiming' => $cartTiming,
            'openOrders' => $openOrders,
            'pastOrders' => $pastOrders,
            'billOrder' => $billOrder,
            'bills' => $bills,
            'billCreateUrl' => $billCreateUrl,
            'rideUrl' => ExploreLinks::ride($place),
            'dismissed' => ExploreDismissal::where('user_id', $user->id)->where('nearby_place_id', $place->id)->active()->exists(),
            'buffer' => (int) config('explore.safety_buffer_minutes', 8),
            'money' => fn ($paisa) => Money::format($paisa, true),
        ];
    }
}
