<?php

namespace App\Services\Preorder;

use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Contracts\Session\Session;

/**
 * Session cart — one restaurant at a time. Only ids/quantities/notes live in the
 * session; prices and availability are always re-read from the database.
 */
class Cart
{
    private const KEY = 'preorder.cart';

    public function __construct(private Session $session)
    {
    }

    public function raw(): array
    {
        $c = $this->session->get(self::KEY);

        return is_array($c) ? $c + ['restaurant_id' => null, 'items' => [], 'notes' => [], 'instructions' => ''] : ['restaurant_id' => null, 'items' => [], 'notes' => [], 'instructions' => ''];
    }

    private function save(array $c): void
    {
        if (empty($c['items'])) {
            $c = ['restaurant_id' => null, 'items' => [], 'notes' => [], 'instructions' => $c['instructions'] ?? ''];
        }
        $this->session->put(self::KEY, $c);
    }

    public function restaurant(): ?Restaurant
    {
        $id = $this->raw()['restaurant_id'];

        return $id ? Restaurant::find($id) : null;
    }

    public function count(): int
    {
        return (int) array_sum($this->raw()['items']);
    }

    /** Set an item's quantity (0 removes). Throws if the cart belongs to another café and $replace is false. */
    public function set(MenuItem $item, int $qty, ?string $note = null, bool $replace = false): void
    {
        $c = $this->raw();
        if ($c['restaurant_id'] && (int) $c['restaurant_id'] !== (int) $item->restaurant_id && $c['items']) {
            if (! $replace) {
                $other = Restaurant::find($c['restaurant_id'])?->name ?? 'another café';
                throw new PreorderException("Your cart has items from {$other}. Start a new cart to order from here.", 'cart_conflict');
            }
            $c = ['restaurant_id' => null, 'items' => [], 'notes' => [], 'instructions' => ''];
        }
        $max = (int) config('preorder.max_qty_per_item', 20);
        $qty = max(0, min($max, $qty));
        if ($qty > 0) {
            $total = array_sum($c['items']) - ($c['items'][$item->id] ?? 0) + $qty;
            if ($total > (int) config('preorder.max_items_per_order', 40)) {
                throw new PreorderException('That is more than one preorder can hold. Please reduce quantities.', 'qty');
            }
        }
        $c['restaurant_id'] = $item->restaurant_id;
        if ($qty === 0) {
            unset($c['items'][$item->id], $c['notes'][$item->id]);
        } else {
            $c['items'][$item->id] = $qty;
            if ($note !== null) {
                $note = trim(mb_substr($note, 0, 200));
                if ($note === '') {
                    unset($c['notes'][$item->id]);
                } else {
                    $c['notes'][$item->id] = $note;
                }
            }
        }
        $this->save($c);
    }

    public function setInstructions(?string $text): void
    {
        $c = $this->raw();
        $c['instructions'] = trim(mb_substr((string) $text, 0, 500));
        $this->session->put(self::KEY, $c);
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * Resolved lines with fresh DB prices. Items that vanished from the menu are dropped.
     *
     * @return array<int, array{item:MenuItem, qty:int, note:?string, unit_paisa:int, line_paisa:int, prep_minutes:int, available:bool}>
     */
    public function lines(): array
    {
        $c = $this->raw();
        if (! $c['restaurant_id'] || ! $c['items']) {
            return [];
        }
        $items = MenuItem::where('restaurant_id', $c['restaurant_id'])->whereIn('id', array_keys($c['items']))->get()->keyBy('id');
        $lines = [];
        foreach ($c['items'] as $id => $qty) {
            $item = $items->get($id);
            if (! $item) {
                continue;
            }
            $unit = $item->pricePaisa();
            $lines[] = [
                'item' => $item,
                'qty' => (int) $qty,
                'note' => $c['notes'][$id] ?? null,
                'unit_paisa' => $unit,
                'line_paisa' => $unit * (int) $qty,
                'prep_minutes' => (int) $item->prep_minutes,
                'available' => (bool) $item->available,
            ];
        }

        return $lines;
    }

    /** JSON-safe summary for the sticky cart bar. */
    public function summary(): array
    {
        $lines = $this->lines();
        $quote = app(OrderPricing::class)->quote(array_map(fn ($l) => ['unit_paisa' => $l['unit_paisa'], 'qty' => $l['qty']], $lines));

        return [
            'restaurant_id' => $this->raw()['restaurant_id'],
            'count' => array_sum(array_column($lines, 'qty')),
            'items' => collect($lines)->mapWithKeys(fn ($l) => [$l['item']->id => $l['qty']])->all(),
            'subtotal' => Money::fromPaisa($quote['subtotal']),
            'subtotal_label' => Money::format($quote['subtotal'], true),
            'total_label' => Money::format($quote['total'], true),
        ];
    }
}
