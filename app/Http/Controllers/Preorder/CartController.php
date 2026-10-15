<?php

namespace App\Http\Controllers\Preorder;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preorder\CartItemRequest;
use App\Models\MenuItem;
use App\Services\Preorder\Cart;
use App\Services\Preorder\Money;
use App\Services\Preorder\OrderPricing;
use App\Services\Preorder\PreorderException;
use App\Services\Preorder\TimingCheck;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(private Cart $cart)
    {
    }

    public function show(Request $request, OrderPricing $pricing, TimingCheck $timing)
    {
        $lines = $this->cart->lines();
        $restaurant = $this->cart->restaurant();
        $quote = $pricing->quote(array_map(fn ($l) => ['unit_paisa' => $l['unit_paisa'], 'qty' => $l['qty']], $lines));
        $unavailable = collect($lines)->where('available', false)->pluck('item.name')->all();
        $preview = $lines && $restaurant
            ? $timing->evaluate($restaurant, $lines, $timing->contextTicket($request->user()))
            : null;

        return view('preorder.cart', [
            'lines' => $lines,
            'restaurant' => $restaurant,
            'quote' => $quote,
            'unavailable' => $unavailable,
            'instructions' => $this->cart->raw()['instructions'],
            'timing' => $preview,
        ]);
    }

    public function set(CartItemRequest $request, MenuItem $menuItem)
    {
        $menuItem->load('restaurant');
        $qty = (int) $request->validated('qty');
        try {
            if (! $menuItem->restaurant?->accepts_preorders) {
                throw new PreorderException('This café is not taking preorders right now.');
            }
            // Unavailable items can be reduced/removed, never increased.
            $current = (int) ($this->cart->raw()['items'][$menuItem->id] ?? 0);
            if ($qty > $current && ! $menuItem->available) {
                throw new PreorderException("{$menuItem->name} is unavailable right now.");
            }
            $this->cart->set($menuItem, $qty, $request->validated('note'), (bool) $request->boolean('replace'));
        } catch (PreorderException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'data' => ['code' => $e->field] + $this->cart->summary()], 422);
            }

            return back()->withErrors(['preorder' => $e->getMessage()]);
        }

        $summary = $this->cart->summary();
        $msg = $qty > 0 ? "{$menuItem->name} × {$qty} in your cart" : "{$menuItem->name} removed";
        if ($request->expectsJson()) {
            return response()->json(['message' => $msg, 'data' => $summary]);
        }

        return back()->with('ok', $msg);
    }

    public function instructions(Request $request)
    {
        $data = $request->validate(['special_instructions' => ['nullable', 'string', 'max:500']]);
        $this->cart->setInstructions($data['special_instructions'] ?? '');

        return $request->boolean('to_checkout')
            ? redirect()->route('preorder.checkout')
            : back()->with('ok', 'Instructions saved');
    }

    public function clear(Request $request)
    {
        $this->cart->clear();

        return $request->expectsJson()
            ? response()->json(['message' => 'Cart cleared', 'data' => $this->cart->summary()])
            : redirect()->route('preorder.index')->with('ok', 'Cart cleared');
    }
}
