<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Bills\Concerns\ScopesBills;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bills\SaveBillRequest;
use App\Models\BillParticipant;
use App\Models\FoodOrder;
use App\Models\GroupBill;
use App\Models\GroupBillItem;
use App\Models\NearbyPlace;
use App\Services\Bills\BillCalculator;
use App\Services\Bills\BillManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillController extends Controller
{
    use ScopesBills;

    public function __construct(private BillManager $bills, private BillCalculator $calc) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $memberships = BillParticipant::where('user_id', $user->id)->where('status', 'joined')
            ->with(['bill.joinedParticipants', 'bill.owner:id,name'])->latest('id')->get()
            ->filter(fn ($p) => $p->bill !== null);

        $cards = $memberships->map(function ($p) {
            $bill = $p->bill;
            $calc = $this->calc->forBill($bill);
            $bal = $this->bills->balances($bill, $calc);

            return ['bill' => $bill, 'me' => $p, 'share' => $calc['people'][$p->id]['total'] ?? 0, 'balance' => $bal['rows'][$p->id] ?? null, 'progress' => $bal['progress'], 'remaining' => $bal['remaining'], 'ok' => $calc['verification']['ok']];
        })->sortBy(fn ($c) => [array_search($c['bill']->status, GroupBill::STATUSES, true), -$c['bill']->updated_at->timestamp])->values();

        $invites = BillParticipant::where('user_id', $user->id)->where('status', 'invited')
            ->with(['bill.owner:id,name', 'bill.joinedParticipants'])->latest('id')->get()->filter(fn ($p) => $p->bill && $p->bill->status === 'open');

        $youOwe = $cards->filter(fn ($c) => ! $c['me']->isOwner() && in_array($c['bill']->status, ['settling', 'open'], true))->sum(fn ($c) => $c['balance']['remaining'] ?? 0);
        $owedToYou = $cards->filter(fn ($c) => $c['me']->isOwner() && in_array($c['bill']->status, ['settling', 'open'], true))->sum('remaining');

        return view('bills.index', compact('cards', 'invites', 'youOwe', 'owedToYou'));
    }

    public function create(Request $request)
    {
        $bill = new GroupBill([
            'service_charge_percent' => config('bills.default_service_charge_percent'),
            'vat_percent' => config('bills.default_vat_percent'),
            'vat_on_service_charge' => config('bills.default_vat_on_service_charge'),
            'discount_type' => 'none', 'discount_value' => 0, 'tip_amount' => 0, 'tip_allocation' => 'equal',
            'split_mode' => 'equal', 'members_can_select' => true,
        ]);
        $items = collect();

        // "Split a bill here" from a café page: ?place= prefills the café, ?order= the user's OWN order items.
        [$place, $order] = $this->cafeContext($request, $request->integer('place') ?: null, $request->integer('order') ?: null);
        if ($place) {
            $bill->title = mb_substr($place->name.' — '.now()->format('M j'), 0, 120);
            $bill->restaurant_name = mb_substr($place->name, 0, 120);
        }
        if ($order) {
            $bill->split_mode = 'itemized';
            $items = $order->items->map(fn ($i) => new GroupBillItem(['name' => mb_substr($i->name, 0, 80), 'unit_price' => $i->unit_price, 'quantity' => $i->qty, 'is_shared' => false]));
        }

        return view('bills.form', ['bill' => $bill, 'items' => $items, 'peopleCount' => 4, 'cafePlace' => $place, 'cafeOrder' => $order]);
    }

    public function store(SaveBillRequest $request)
    {
        $bill = $this->bills->create($request->user(), $request->validated());

        // Link the bill to the café (and the owner's own order) it was split at. Never mass-assigned.
        [$place, $order] = $this->cafeContext($request, $request->validated('nearby_place_id'), $request->validated('food_order_id'));
        if ($place && Schema::hasColumn('group_bills', 'nearby_place_id')) {
            $bill->forceFill(['nearby_place_id' => $place->id, 'food_order_id' => $order?->id])->save();
        }

        return redirect()->route('bills.show', $bill)->with('ok', 'Bill created. Share the invite link so friends can join.');
    }

    /**
     * Resolve a café + order for prefill/linking. The order must belong to the signed-in user (IDOR-safe:
     * anyone else's order id is silently ignored) and, when a place is given, be from that café.
     *
     * @return array{0: ?NearbyPlace, 1: ?FoodOrder}
     */
    private function cafeContext(Request $request, ?int $placeId, ?int $orderId): array
    {
        if (! class_exists(FoodOrder::class) || ! Schema::hasTable('food_orders')) {
            return [$placeId ? NearbyPlace::find($placeId) : null, null];
        }
        $order = $orderId
            ? FoodOrder::where('user_id', $request->user()->id)->whereKey($orderId)->whereNotIn('status', ['cancelled', 'rejected'])->with(['items', 'restaurant'])->first()
            : null;
        $place = $placeId ? NearbyPlace::find($placeId) : ($order?->restaurant?->nearby_place_id ? NearbyPlace::find($order->restaurant->nearby_place_id) : null);
        if ($order && $place && (int) $order->restaurant?->nearby_place_id !== (int) $place->id) {
            $order = null; // an order from a different café is never copied in
        }

        return [$place, $order];
    }

    public function show(Request $request, GroupBill $bill)
    {
        $me = $this->participantOr404($request, $bill);
        $bill->load(['items.allocations', 'joinedParticipants.user:id,name', 'owner:id,name']);
        $calc = $this->calc->forBill($bill);
        $balances = $this->bills->balances($bill, $calc);
        $isOwner = $me->isOwner();
        $inviteUrl = $isOwner && $bill->plainInviteToken() ? route('bills.join', $bill->plainInviteToken()) : null;
        $pendingInvites = $isOwner ? $bill->participants()->where('status', 'invited')->get() : collect();

        return view('bills.show', [
            'bill' => $bill, 'me' => $me, 'isOwner' => $isOwner, 'calc' => $calc, 'balances' => $balances,
            'engineInput' => $this->calc->inputFor($bill), 'inviteUrl' => $inviteUrl, 'pendingInvites' => $pendingInvites,
        ]);
    }

    public function edit(Request $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        if (! $bill->isEditable()) {
            return redirect()->route('bills.show', $bill)->withErrors(['bill' => 'This bill is locked for settlement. Reopen it to edit.']);
        }

        return view('bills.form', ['bill' => $bill, 'items' => $bill->items, 'peopleCount' => $bill->joinedParticipants()->count()]);
    }

    public function update(SaveBillRequest $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        $this->bills->update($bill, $request->validated());

        return redirect()->route('bills.show', $bill)->with('ok', 'Bill updated — everyone\'s share was recalculated.');
    }

    /** Live JSON for participants: {message, data:{calculation, balances, status}}. */
    public function calculation(Request $request, GroupBill $bill)
    {
        $this->participantOr404($request, $bill);

        return response()->json(['message' => 'ok', 'data' => $this->payload($bill)]);
    }

    public function allocations(Request $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        $request->validate(['alloc' => ['nullable', 'array'], 'weight' => ['nullable', 'array']]);
        $checked = (array) $request->input('alloc', []);
        $weights = (array) $request->input('weight', []);
        $matrix = [];
        foreach ($bill->items()->pluck('id') as $itemId) {
            $matrix[$itemId] = [];
            foreach ((array) ($checked[$itemId] ?? []) as $pid => $on) {
                if ($on) {
                    $w = $weights[$itemId][$pid] ?? 1;
                    $matrix[$itemId][$pid] = is_numeric($w) ? (int) $w : 1;
                }
            }
        }
        foreach (array_keys($checked) as $itemId) {
            if (! array_key_exists((int) $itemId, $matrix)) {
                throw ValidationException::withMessages(['allocations' => 'That item is not part of this bill.']);
            }
        }
        $this->bills->setAllocations($bill, $matrix);

        return redirect()->to(route('bills.show', $bill).'#assign')->with('ok', 'Item assignments saved — totals recalculated.');
    }

    public function toggleMine(Request $request, GroupBill $bill, GroupBillItem $item)
    {
        $me = $this->participantOr404($request, $bill);
        abort_unless((int) $item->group_bill_id === (int) $bill->id, 404);
        $data = $request->validate(['on' => ['required', 'boolean'], 'weight' => ['nullable', 'integer', 'min:1', 'max:20']]);
        try {
            $this->bills->toggleMine($bill, $me, $item, (bool) $data['on'], (int) ($data['weight'] ?? 1));
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => collect($e->errors())->flatten()->first(), 'data' => $this->payload($bill->fresh())], 422);
            }
            throw $e;
        }
        if ($request->expectsJson()) {
            return response()->json(['message' => $data['on'] ? 'Added to your share.' : 'Removed from your share.', 'data' => $this->payload($bill->fresh())]);
        }

        return redirect()->to(route('bills.show', $bill).'#assign');
    }

    public function custom(Request $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        $money = ['nullable', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $request->validate(['amounts' => ['nullable', 'array'], 'amounts.*' => $money, 'tips' => ['nullable', 'array'], 'tips.*' => $money]);
        $this->bills->setCustom($bill, (array) $request->input('amounts', []), (array) $request->input('tips', []));

        return redirect()->to(route('bills.show', $bill).'#custom')->with('ok', 'Custom amounts saved.');
    }

    public function status(Request $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        $data = $request->validate(['to' => ['required', Rule::in(['open', 'settling', 'archived'])]]);
        $this->bills->transition($bill, $data['to']);
        $msg = ['settling' => 'Amounts locked — settlement started.', 'open' => 'Bill reopened for edits.', 'archived' => 'Bill archived.'][$data['to']];

        return redirect()->route($data['to'] === 'settling' ? 'bills.settle' : 'bills.show', $bill)->with('ok', $msg);
    }

    public function removeParticipant(Request $request, GroupBill $bill, BillParticipant $participant)
    {
        $this->ownerOrAbort($request, $bill);
        abort_unless((int) $participant->group_bill_id === (int) $bill->id, 404);
        $this->bills->removeParticipant($bill, $participant);

        return redirect()->route('bills.show', $bill)->with('ok', $participant->display_name.' was removed from the bill.');
    }

    private function payload(GroupBill $bill): array
    {
        $calc = $this->calc->forBill($bill);

        return ['status' => $bill->status, 'calculation' => $calc, 'balances' => $this->bills->balances($bill, $calc),
            'input' => $this->calc->inputFor($bill)];
    }
}
