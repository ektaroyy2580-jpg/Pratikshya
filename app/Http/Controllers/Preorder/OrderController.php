<?php

namespace App\Http\Controllers\Preorder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preorder\PlaceOrderRequest;
use App\Models\FoodOrder;
use App\Services\Preorder\Cart;
use App\Services\Preorder\OrderPricing;
use App\Services\Preorder\PreorderException;
use App\Services\Preorder\PreorderService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(private Cart $cart, private PreorderService $orders)
    {
    }

    public function checkout(Request $request, OrderPricing $pricing)
    {
        $restaurant = $this->cart->restaurant();
        $raw = $this->cart->raw();
        if (! $restaurant || ! $raw['items']) {
            return redirect()->route('preorder.cart');
        }
        try {
            $lines = $this->orders->resolveLines($restaurant, $raw['items'], $raw['notes']);
        } catch (PreorderException $e) {
            return redirect()->route('preorder.cart')->withErrors(['preorder' => $e->getMessage()]);
        }
        $quote = $pricing->quote(array_map(fn ($l) => ['unit_paisa' => $l['unit_paisa'], 'qty' => $l['qty']], $lines));
        $timing = $this->orders->preview($request->user(), $restaurant, $lines);

        return view('preorder.checkout', [
            'restaurant' => $restaurant->load('location', 'nearbyPlace'),
            'lines' => $lines,
            'quote' => $quote,
            'timing' => $timing,
            'instructions' => $raw['instructions'],
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function place(PlaceOrderRequest $request)
    {
        // Browser retry / double submit: return the order already created with this key.
        $key = $request->validated('idempotency_key');
        if ($key && ($existing = FoodOrder::where('user_id', $request->user()->id)->where('idempotency_key', $key)->first())) {
            return redirect()->route('preorder.order', $existing);
        }
        $restaurant = $this->cart->restaurant();
        $raw = $this->cart->raw();
        if (! $restaurant || ! $raw['items']) {
            return redirect()->route('preorder.cart')->withErrors(['preorder' => 'Your cart is empty.']);
        }
        $this->cart->setInstructions($request->validated('special_instructions'));

        try {
            $order = $this->orders->place(
                $request->user(),
                $restaurant,
                $raw['items'],
                $raw['notes'],
                $request->validated('special_instructions'),
                $request->validated('payment_mode'),
                $request->boolean('acknowledge_conflict'),
                $request->validated('idempotency_key'),
            );
        } catch (PreorderException $e) {
            return back()->withErrors([$e->field => $e->getMessage()])->withInput();
        }
        $this->cart->clear();

        return redirect()->route('preorder.order', $order)->with('ok', "Order {$order->reference()} placed");
    }

    public function index(Request $request)
    {
        $orders = FoodOrder::where('user_id', $request->user()->id)
            ->with(['restaurant', 'items'])
            ->latest()
            ->paginate(10);

        return view('preorder.orders', compact('orders'));
    }

    public function show(FoodOrder $order)
    {
        $this->authorize('view', $order);
        $order->load(['restaurant.location', 'restaurant.nearbyPlace', 'items', 'ticket.service']);

        return view('preorder.order', compact('order'));
    }

    public function state(Request $request, FoodOrder $order)
    {
        $this->authorize('view', $order);
        $data = $order->stateArray();
        if ((int) $order->user_id !== (int) $request->user()->id) {
            $data['pickup_code'] = null; // only the customer sees the code
        }

        return response()->json(['message' => 'Order status retrieved successfully', 'data' => $data]);
    }

    public function cancel(Request $request, FoodOrder $order)
    {
        $this->authorize('view', $order);
        try {
            $this->orders->cancel($order, $request->user());
        } catch (PreorderException $e) {
            return back()->withErrors(['preorder' => $e->getMessage()]);
        }

        return redirect()->route('preorder.order', $order)->with('ok', 'Order cancelled — no charge');
    }
}
