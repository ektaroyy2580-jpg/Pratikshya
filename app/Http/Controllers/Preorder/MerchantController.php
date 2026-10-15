<?php

namespace App\Http\Controllers\Preorder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preorder\MerchantStatusRequest;
use App\Models\AuditLog;
use App\Models\FoodOrder;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Services\Preorder\PreorderException;
use App\Services\Preorder\PreorderService;
use Illuminate\Http\Request;

class MerchantController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $restaurants = Restaurant::managedBy($user)->orderBy('name')->get();
        abort_if($restaurants->isEmpty(), 403, 'Your account is not linked to a participating café.');

        $restaurant = $restaurants->firstWhere('slug', (string) $request->query('restaurant')) ?? $restaurants->first();
        $open = $restaurant->orders()->whereIn('status', FoodOrder::OPEN)->with(['items', 'user:id,name'])->oldest()->get();
        $recent = $restaurant->orders()->whereNotIn('status', FoodOrder::OPEN)->with(['items', 'user:id,name'])->latest('updated_at')->take(8)->get();
        $items = $restaurant->items()->with('category')->orderBy('menu_category_id')->orderBy('sort')->get();
        $today = $restaurant->orders()->whereDate('created_at', today())->get(['status', 'total']);

        return view('preorder.merchant', [
            'restaurants' => $restaurants,
            'restaurant' => $restaurant,
            'open' => $open,
            'recent' => $recent,
            'items' => $items,
            'stats' => [
                'new' => $open->where('status', 'placed')->count(),
                'kitchen' => $open->whereIn('status', ['accepted', 'preparing'])->count(),
                'ready' => $open->where('status', 'ready')->count(),
                'today' => $today->count(),
            ],
            'signature' => $this->signature($restaurant),
        ]);
    }

    /** Lightweight polling: the page reloads only when the open-order signature changes. */
    public function feed(Restaurant $restaurant)
    {
        $this->authorize('manage', $restaurant);
        $open = $restaurant->orders()->whereIn('status', FoodOrder::OPEN)->get(['id', 'status']);

        return response()->json(['message' => 'Orders retrieved successfully', 'data' => [
            'signature' => $this->signature($restaurant),
            'new' => $open->where('status', 'placed')->count(),
            'open' => $open->count(),
        ]]);
    }

    public function status(MerchantStatusRequest $request, FoodOrder $order, PreorderService $orders)
    {
        $this->authorize('manage', $order);
        try {
            $orders->transition($order, $request->validated('status'), $request->user(), $request->validated('reason'), $request->validated('pickup_code'));
        } catch (PreorderException $e) {
            return back()->withErrors(['preorder' => $e->getMessage()]);
        }

        return redirect()->route('preorder.merchant', ['restaurant' => $order->restaurant->slug])
            ->with('ok', "{$order->reference()} → ".$order->fresh()->statusLabel());
    }

    public function toggleItem(Restaurant $restaurant, MenuItem $menuItem)
    {
        $this->authorize('manage', $restaurant);
        abort_unless((int) $menuItem->restaurant_id === (int) $restaurant->id, 404);
        $menuItem->forceFill(['available' => ! $menuItem->available])->save();
        AuditLog::record('preorder.item_availability', $menuItem, ['available' => $menuItem->available]);

        return back()->with('ok', $menuItem->name.($menuItem->available ? ' is available again' : ' marked unavailable'));
    }

    public function toggleRestaurant(Restaurant $restaurant)
    {
        $this->authorize('manage', $restaurant);
        $restaurant->forceFill(['accepts_preorders' => ! $restaurant->accepts_preorders])->save();
        AuditLog::record('preorder.restaurant_toggle', $restaurant, ['accepts_preorders' => $restaurant->accepts_preorders]);

        return redirect()->route('preorder.merchant', ['restaurant' => $restaurant->slug])
            ->with('ok', $restaurant->accepts_preorders ? 'Preorders resumed' : 'Preorders paused — existing orders are unaffected');
    }

    private function signature(Restaurant $restaurant): string
    {
        $rows = $restaurant->orders()->whereIn('status', FoodOrder::OPEN)->orderBy('id')->get(['id', 'status', 'updated_at']);

        return md5($rows->map(fn ($o) => $o->id.':'.$o->status.':'.$o->updated_at?->timestamp)->implode('|'));
    }
}
