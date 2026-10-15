<?php

namespace Tests\Feature\Modules;

use App\Models\Counter;
use App\Models\FoodOrder;
use App\Models\Location;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\NearbyPlace;
use App\Models\QueueEvent;
use App\Models\Restaurant;
use App\Models\RestaurantMenu;
use App\Models\RestaurantStaff;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Preorder\FoodOrderUpdate;
use App\Services\Preorder\Money;
use App\Services\Preorder\OrderPricing;
use App\Services\Preorder\PreorderException;
use App\Services\Preorder\PreorderService;
use App\Services\Preorder\TimingCheck;
use App\Services\Queue\QueueManager;
use Database\Seeders\Modules\PreorderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PreorderTest extends TestCase
{
    use RefreshDatabase;

    private Location $loc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loc = Location::create(['name' => 'Test Office', 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
    }

    private function cafe(array $over = []): Restaurant
    {
        $place = NearbyPlace::create(['location_id' => $this->loc->id, 'name' => 'Test Momo '.uniqid(), 'category' => 'Restaurant', 'emoji' => 'x', 'tip' => 't',
            'lat' => 27.701, 'lng' => 85.301, 'walk_minutes' => 3, 'visit_minutes' => 10, 'rating' => 4.5]);
        $r = Restaurant::create(array_merge([
            'location_id' => $this->loc->id, 'nearby_place_id' => $place->id, 'name' => 'Momo Test', 'slug' => 'momo-'.uniqid(),
            'cuisine' => 'Momo', 'prep_buffer_minutes' => 4, 'lat' => 27.701, 'lng' => 85.301,
        ], $over));
        $menu = RestaurantMenu::create(['restaurant_id' => $r->id, 'name' => 'All day']);
        MenuCategory::create(['restaurant_menu_id' => $menu->id, 'name' => 'Momo']);

        return $r;
    }

    private function item(Restaurant $r, string $name, string $price, int $prep = 10, bool $available = true): MenuItem
    {
        $i = MenuItem::create(['restaurant_id' => $r->id, 'name' => $name, 'price' => $price, 'prep_minutes' => $prep, 'art' => 'momo']);
        $i->forceFill(['available' => $available])->save();

        return $i;
    }

    /** A queue where the user is `ahead` people from the counter at `avg` minutes each. */
    private function ticketFor(User $user, int $ahead, int $avg = 4): Ticket
    {
        $s = $this->loc->services()->create(['name' => 'Licence', 'prefix' => 'D', 'counter' => 1, 'avg_minutes' => $avg,
            'current_number' => 10, 'last_number' => 10 + $ahead, 'documents' => []]);
        Counter::create(['location_id' => $this->loc->id, 'service_id' => $s->id, 'number' => 1, 'label' => 'Counter 1']);

        return app(QueueManager::class)->join($s, $user, []);
    }

    private function merchantFor(Restaurant $r): User
    {
        $m = User::factory()->create();
        RestaurantStaff::create(['user_id' => $m->id, 'restaurant_id' => $r->id]);

        return $m;
    }

    private function place(User $u, Restaurant $r, array $qty, string $mode = 'demo', bool $ack = false): FoodOrder
    {
        return app(PreorderService::class)->place($u, $r, $qty, [], null, $mode, $ack);
    }

    // ---------------- Money ----------------

    public function test_totals_apply_service_then_vat_in_exact_paisa(): void
    {
        $q = app(OrderPricing::class)->quote([
            ['unit_paisa' => Money::toPaisa('180.00'), 'qty' => 2],   // 360.00
            ['unit_paisa' => Money::toPaisa('49.99'), 'qty' => 3],    // 149.97
        ]);
        $this->assertSame(50997, $q['subtotal']);
        $this->assertSame(5100, $q['service_charge']);   // 10% of 509.97 = 50.997 → 51.00
        $this->assertSame(7293, $q['vat']);              // 13% of 560.97 = 72.9261 → 72.93
        $this->assertSame(63390, $q['total']);
        $this->assertSame($q['subtotal'] + $q['service_charge'] + $q['vat'], $q['total']);
        $this->assertSame('633.90', Money::fromPaisa($q['total']));
    }

    public function test_placed_order_stores_snapshot_and_exact_decimal_totals(): void
    {
        $r = $this->cafe();
        $a = $this->item($r, 'Buff Momo', '180.00');
        $b = $this->item($r, 'Chiya', '45.50', 4);
        $u = User::factory()->create();

        $o = $this->place($u, $r, [$a->id => 2, $b->id => 1]);

        // 405.50 + 40.55 service + 13% of 446.05 (57.9865 → 57.99) = 504.04
        $this->assertSame('405.50', $o->subtotal);
        $this->assertSame('40.55', $o->service_charge);
        $this->assertSame('57.99', $o->vat);
        $this->assertSame('504.04', $o->total);
        $this->assertSame('360.00', $o->items->firstWhere('name', 'Buff Momo')->line_total);

        // Later menu price changes don't alter the order snapshot.
        $a->update(['price' => '999.00']);
        $this->assertSame('180.00', $o->fresh()->items->firstWhere('name', 'Buff Momo')->unit_price);
    }

    // ---------------- Menu rules ----------------

    public function test_unavailable_item_is_rejected_at_cart_and_order(): void
    {
        $r = $this->cafe();
        $off = $this->item($r, 'Sold Out Momo', '200.00', 10, false);
        $u = User::factory()->create();

        $this->actingAs($u)->postJson(route('preorder.cart.set', $off->id), ['qty' => 1])->assertStatus(422);
        $this->assertSame([], session('preorder.cart.items') ?? []);

        $this->expectException(PreorderException::class);
        $this->place($u, $r, [$off->id => 1]);
    }

    public function test_item_from_another_restaurant_is_rejected(): void
    {
        $r1 = $this->cafe();
        $r2 = $this->cafe(['name' => 'Other']);
        $foreign = $this->item($r2, 'Foreign', '100.00');

        $this->expectException(PreorderException::class);
        $this->place(User::factory()->create(), $r1, [$foreign->id => 1]);
    }

    public function test_non_participating_restaurant_is_hidden_and_rejected(): void
    {
        $r = $this->cafe(['name' => 'Walk-in Only']);
        $r->forceFill(['accepts_preorders' => false])->save();
        $i = $this->item($r, 'Tea', '30.00');
        $u = User::factory()->create();

        $this->actingAs($u)->get(route('preorder.index'))->assertOk()->assertDontSee('Walk-in Only');
        $this->actingAs($u)->get(route('preorder.restaurant', $r))->assertNotFound();
        $this->actingAs($u)->postJson(route('preorder.cart.set', $i->id), ['qty' => 1])->assertStatus(422);

        try {
            $this->place($u, $r, [$i->id => 1]);
            $this->fail('Order at non-participating restaurant should fail');
        } catch (PreorderException $e) {
            $this->assertStringContainsString("isn't taking preorders", $e->getMessage());
        }
        $this->assertSame(0, FoodOrder::count());
    }

    // ---------------- Timing check ----------------

    public function test_timing_states_ok_tight_and_conflict(): void
    {
        $r = $this->cafe();   // buffer 4, walk 3 (nearby place at the ticket's location), collect 3
        $u = User::factory()->create();
        $t = $this->ticketFor($u, 20);  // 20 ahead × 4 min = 80, low = floor(80×0.6) = 48
        $check = app(TimingCheck::class);
        $this->assertSame(48, $check->conservativeWait($t));

        $ok = $check->evaluate($r, [['prep_minutes' => 10, 'qty' => 1]], $t);   // prep 14, away = 14+3+3 = 20
        $this->assertSame(['ok', 14, 3, 20], [$ok['state'], $ok['prep_minutes'], $ok['walk_minutes'], $ok['away_minutes']]);

        $tight = $check->evaluate($r, [['prep_minutes' => 36, 'qty' => 1]], $t); // prep 40, away 46 → spare 2
        $this->assertSame('tight', $tight['state']);
        $this->assertFalse($tight['needs_confirmation']);

        $conflict = $check->evaluate($r, [['prep_minutes' => 20, 'qty' => 6]], $t); // 20×1.75=35+4=39 → away 45? spare 3 → tight
        $this->assertSame('tight', $conflict['state']);
        $conflict = $check->evaluate($r, [['prep_minutes' => 30, 'qty' => 8]], $t); // 30×2=60+4=64 → away 70
        $this->assertSame('conflict', $conflict['state']);
        $this->assertTrue($conflict['needs_confirmation']);
        $this->assertStringContainsString('short', $conflict['headline']);

        $none = $check->evaluate($r, [['prep_minutes' => 10, 'qty' => 1]], null);
        $this->assertSame('no_ticket', $none['state']);
    }

    public function test_haversine_walk_used_when_no_nearby_place_at_ticket_location(): void
    {
        $r = $this->cafe(['nearby_place_id' => null, 'lat' => 27.709, 'lng' => 85.3]); // ~1 km north
        $walk = app(TimingCheck::class)->walkMinutes($r, null);
        $this->assertSame(14, $walk); // 1000.75 m / 75 m per min → 14
    }

    public function test_conflict_requires_explicit_confirmation(): void
    {
        $r = $this->cafe();
        $slow = $this->item($r, 'Slow Thakali', '500.00', 40);
        $u = User::factory()->create();
        $this->ticketFor($u, 3);   // low wait ≈ 7 min — far too short

        $this->actingAs($u)->postJson(route('preorder.cart.set', $slow->id), ['qty' => 1])->assertOk();
        $this->actingAs($u)->get(route('preorder.checkout'))->assertOk()->assertSee('I understand this order may not be ready');

        $this->actingAs($u)->post(route('preorder.place'), ['payment_mode' => 'demo'])
            ->assertSessionHasErrors('acknowledge_conflict');
        $this->assertSame(0, FoodOrder::count());

        $this->actingAs($u)->post(route('preorder.place'), ['payment_mode' => 'demo', 'acknowledge_conflict' => 1])->assertRedirect();
        $o = FoodOrder::firstOrFail();
        $this->assertSame('conflict', $o->timing_state);
        $this->assertTrue($o->conflict_acknowledged);
    }

    // ---------------- Payment ----------------

    public function test_demo_payment_never_marks_paid(): void
    {
        $r = $this->cafe();
        $i = $this->item($r, 'Momo', '180.00');
        $u = User::factory()->create();

        $demo = $this->place($u, $r, [$i->id => 1], 'demo');
        $cash = $this->place($u, $r, [$i->id => 1], 'cash_on_pickup');
        $this->assertSame('demo_paid', $demo->payment_status);
        $this->assertSame('unpaid', $cash->payment_status);

        // Walk the demo order through every status: it must never become "paid".
        $m = $this->merchantFor($r);
        $svc = app(PreorderService::class);
        foreach (['accepted', 'preparing', 'ready'] as $st) {
            $svc->transition($demo->fresh(), $st, $m);
        }
        $svc->transition($demo->fresh(), 'collected', $m, null, $demo->pickup_code);
        $this->assertSame(0, FoodOrder::where('payment_status', 'paid')->count());

        // Arbitrary payment_status from the client is ignored.
        $this->actingAs($u)->postJson(route('preorder.cart.set', $i->id), ['qty' => 1]);
        $this->actingAs($u)->post(route('preorder.place'), ['payment_mode' => 'card', 'payment_status' => 'paid'])->assertSessionHasErrors('payment_mode');
        $this->actingAs($u)->get(route('preorder.checkout'))->assertSee('Demo mode — no money is charged', false)->assertDontSee('card number', false);
    }

    // ---------------- Cancellation ----------------

    public function test_cancel_only_while_placed(): void
    {
        $r = $this->cafe();
        $i = $this->item($r, 'Momo', '180.00');
        $u = User::factory()->create();

        $o1 = $this->place($u, $r, [$i->id => 1]);
        $this->actingAs($u)->post(route('preorder.order.cancel', $o1))->assertRedirect();
        $this->assertSame('cancelled', $o1->fresh()->status);

        $o2 = $this->place($u, $r, [$i->id => 1]);
        app(PreorderService::class)->transition($o2, 'accepted', $this->merchantFor($r));
        $this->actingAs($u)->post(route('preorder.order.cancel', $o2))->assertSessionHasErrors('preorder');
        $this->assertSame('accepted', $o2->fresh()->status);
    }

    // ---------------- Authorization ----------------

    public function test_user_cannot_view_or_cancel_others_orders(): void
    {
        $r = $this->cafe();
        $i = $this->item($r, 'Momo', '180.00');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $o = $this->place($owner, $r, [$i->id => 1]);

        $this->actingAs($other)->get(route('preorder.order', $o))->assertForbidden();
        $this->actingAs($other)->getJson(route('preorder.order.state', $o))->assertForbidden();
        $this->actingAs($other)->post(route('preorder.order.cancel', $o))->assertForbidden();
        $this->actingAs($other)->get(route('preorder.orders'))->assertOk()->assertDontSee($o->reference());
        $this->assertSame('placed', $o->fresh()->status);

        $this->actingAs($owner)->getJson(route('preorder.order.state', $o))->assertOk()
            ->assertJsonStructure(['message', 'data' => ['status', 'step', 'can_cancel']])
            ->assertJsonPath('data.status', 'placed');
    }

    public function test_merchant_manages_only_own_restaurant(): void
    {
        $mine = $this->cafe(['name' => 'Mine']);
        $theirs = $this->cafe(['name' => 'Theirs']);
        $i = $this->item($theirs, 'Momo', '180.00');
        $mi = $this->item($mine, 'Tea', '40.00');
        $m = $this->merchantFor($mine);
        $o = $this->place(User::factory()->create(), $theirs, [$i->id => 1]);

        $this->actingAs($m)->post(route('preorder.merchant.status', $o), ['status' => 'accepted'])->assertForbidden();
        $this->actingAs($m)->getJson(route('preorder.merchant.feed', $theirs))->assertForbidden();
        $this->actingAs($m)->post(route('preorder.merchant.toggle', $theirs))->assertForbidden();
        $this->actingAs($m)->post(route('preorder.merchant.item.toggle', [$theirs, $i->id]))->assertForbidden();
        // Item id from another restaurant under my restaurant's URL → 404, not a cross-tenant toggle.
        $this->actingAs($m)->post(route('preorder.merchant.item.toggle', [$mine, $i->id]))->assertNotFound();
        $this->assertSame('placed', $o->fresh()->status);
        $this->assertTrue($i->fresh()->available);

        $this->actingAs($m)->get(route('preorder.merchant'))->assertOk()->assertSee('Mine')->assertDontSee($o->reference());
        $this->actingAs(User::factory()->create())->get(route('preorder.merchant'))->assertForbidden();

        $this->actingAs($m)->post(route('preorder.merchant.item.toggle', [$mine, $mi->id]))->assertRedirect();
        $this->assertFalse($mi->fresh()->available);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->post(route('preorder.merchant.status', $o), ['status' => 'accepted'])->assertRedirect();
        $this->assertSame('accepted', $o->fresh()->status);
    }

    public function test_collection_requires_matching_pickup_code_and_valid_transitions(): void
    {
        $r = $this->cafe();
        $i = $this->item($r, 'Momo', '180.00');
        $m = $this->merchantFor($r);
        $o = $this->place(User::factory()->create(), $r, [$i->id => 1]);

        // Can't skip straight to collected.
        $this->actingAs($m)->post(route('preorder.merchant.status', $o), ['status' => 'collected', 'pickup_code' => $o->pickup_code])->assertSessionHasErrors('preorder');
        foreach (['accepted', 'preparing', 'ready'] as $st) {
            $this->actingAs($m)->post(route('preorder.merchant.status', $o), ['status' => $st])->assertSessionHasNoErrors();
        }
        $wrong = $o->pickup_code === '0000' ? '1111' : '0000';
        $this->actingAs($m)->post(route('preorder.merchant.status', $o), ['status' => 'collected', 'pickup_code' => $wrong])->assertSessionHasErrors('preorder');
        $this->assertSame('ready', $o->fresh()->status);
        $this->actingAs($m)->post(route('preorder.merchant.status', $o), ['status' => 'collected', 'pickup_code' => $o->pickup_code])->assertSessionHasNoErrors();
        $this->assertSame('collected', $o->fresh()->status);
    }

    // ---------------- Notifications ----------------

    public function test_each_status_change_notifies_customer_exactly_once(): void
    {
        $r = $this->cafe();
        $i = $this->item($r, 'Momo', '180.00');
        $u = User::factory()->create();
        $m = $this->merchantFor($r);
        $svc = app(PreorderService::class);

        $o = $this->place($u, $r, [$i->id => 1]);
        $this->assertSame(1, $m->notifications()->count());   // merchant: new order
        foreach (['accepted', 'preparing', 'ready'] as $st) {
            $svc->transition($o->fresh(), $st, $m);
        }
        $svc->transition($o->fresh(), 'collected', $m, null, $o->pickup_code);

        $statuses = $u->notifications()->get()->pluck('data.status')->all();
        $this->assertEqualsCanonicalizing(['placed', 'accepted', 'preparing', 'ready', 'collected'], $statuses);
        $this->assertSame('food_order_update', $u->notifications()->first()->data['kind']);

        // A stale/double click on an old status can't transition or notify again.
        try {
            $svc->transition($o, 'accepted', $m);   // $o still says "placed" in memory
            $this->fail('Stale transition should fail');
        } catch (PreorderException) {
        }
        $this->assertSame(5, $u->notifications()->count());

        // Re-sending the same dedupe key is a no-op.
        $this->assertFalse($svc->notifyOnce($u, new FoodOrderUpdate($o->fresh(), 'food_order_update', 'dup', "food_order:{$o->id}:collected")));
        $this->assertSame(5, $u->notifications()->count());
    }

    // ---------------- Queue isolation ----------------

    public function test_order_lifecycle_never_touches_the_ticket(): void
    {
        $r = $this->cafe();
        $i = $this->item($r, 'Momo', '180.00', 30);
        $u = User::factory()->create();
        $t = $this->ticketFor($u, 2);
        $before = $t->fresh()->getAttributes();
        $service = $t->service->fresh()->getAttributes();
        $events = QueueEvent::count();
        $m = $this->merchantFor($r);
        $svc = app(PreorderService::class);

        $o = $this->place($u, $r, [$i->id => 2], 'demo', true);   // conflict, confirmed
        $this->assertSame($t->id, $o->ticket_id);
        $svc->transition($o->fresh(), 'accepted', $m);

        // The ticket is called while food is cooking: order is unaffected.
        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'admin'])->save();
        $this->assertSame($before, $t->fresh()->getAttributes());
        $this->assertSame($service, $t->service->fresh()->getAttributes());
        $this->assertSame($events, QueueEvent::count());

        app(QueueManager::class)->callNext($t->service->fresh(), $staff);
        app(QueueManager::class)->callNext($t->service->fresh(), $staff);
        app(QueueManager::class)->callNext($t->service->fresh(), $staff);
        $this->assertSame('called', $t->fresh()->status);
        $this->assertSame('accepted', $o->fresh()->status);

        $svc->transition($o->fresh(), 'ready', $m);
        $this->assertSame('called', $t->fresh()->status);
        $this->assertSame($t->number, $t->fresh()->number);
    }

    // ---------------- Pages & seeder ----------------

    public function test_full_web_flow_and_pages_render(): void
    {
        $r = $this->cafe(['name' => 'Flow Cafe']);
        $i = $this->item($r, 'Juicy Momo', '180.00');
        $u = User::factory()->create();

        $this->actingAs($u)->get(route('preorder.index'))->assertOk()->assertSee('Flow Cafe');
        $this->actingAs($u)->get(route('preorder.restaurant', $r))->assertOk()->assertSee('Juicy Momo');
        $this->actingAs($u)->postJson(route('preorder.cart.set', $i->id), ['qty' => 2])->assertOk()->assertJsonPath('data.count', 2);
        $this->actingAs($u)->get(route('preorder.cart'))->assertOk()->assertSee('Rs 360');
        $this->actingAs($u)->post(route('preorder.cart.instructions'), ['special_instructions' => 'Extra achar', 'to_checkout' => 1])->assertRedirect(route('preorder.checkout'));
        $this->actingAs($u)->get(route('preorder.checkout'))->assertOk()->assertSee('Extra achar');
        $this->actingAs($u)->post(route('preorder.place'), ['payment_mode' => 'cash_on_pickup', 'special_instructions' => 'Extra achar', 'idempotency_key' => 'abc-123'])->assertRedirect();
        // Double submit with the same key returns the same order.
        $this->actingAs($u)->post(route('preorder.place'), ['payment_mode' => 'cash_on_pickup', 'idempotency_key' => 'abc-123'])->assertRedirect();
        $this->assertSame(1, FoodOrder::count());
        $o = FoodOrder::firstOrFail();
        $this->assertSame('Extra achar', $o->special_instructions);
        $this->actingAs($u)->get(route('preorder.order', $o))->assertOk()->assertSee($o->reference());
        $this->actingAs($u)->get(route('preorder.orders'))->assertOk()->assertSee('Flow Cafe');
        $this->get(route('preorder.cart'))->assertOk(); // still authenticated session

        // Ticket widget renders for a ticket at this location.
        $t = $this->ticketFor($u, 5);
        $html = view('preorder.partials.ticket-widget', ['ticket' => $t])->render();
        $this->assertStringContainsString('Flow Cafe', $html);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('preorder.index'))->assertRedirect(route('login'));
        $this->post(route('preorder.place'), ['payment_mode' => 'demo'])->assertRedirect(route('login'));
    }

    public function test_seeder_is_idempotent(): void
    {
        foreach ([['Department of Passports', 27.6946, 85.3135], ['Transport Management Office', 27.66, 85.30], ['Ward No. 10 Office', 27.69, 85.33]] as [$n, $la, $ln]) {
            Location::create(['name' => $n, 'area' => 'KTM', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => $la, 'lng' => $ln]);
        }
        $this->seed(PreorderSeeder::class);
        $counts = [Restaurant::count(), MenuItem::count(), FoodOrder::count(), User::count()];
        $this->seed(PreorderSeeder::class);
        $this->assertSame($counts, [Restaurant::count(), MenuItem::count(), FoodOrder::count(), User::count()]);
        $this->assertGreaterThanOrEqual(4, Restaurant::participating()->count());
        $this->assertTrue(RestaurantStaff::whereHas('user', fn ($q) => $q->where('email', 'preorder.merchant@waitwise.np'))->exists());
    }
}
