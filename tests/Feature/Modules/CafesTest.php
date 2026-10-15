<?php

namespace Tests\Feature\Modules;

use App\Models\Counter;
use App\Models\FoodOrder;
use App\Models\GroupBill;
use App\Models\Location;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\NearbyPlace;
use App\Models\Restaurant;
use App\Models\RestaurantMenu;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Cafes\CafeImages;
use App\Services\Explore\RecommendationService;
use App\Services\Preorder\PreorderService;
use App\Services\Queue\QueueManager;
use Database\Seeders\Modules\CafesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CafesTest extends TestCase
{
    use RefreshDatabase;

    private Location $loc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loc = Location::create(['name' => 'Cafe Test Office', 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
    }

    private function place(string $name, string $category = 'Restaurant', int $meters = 150): NearbyPlace
    {
        return NearbyPlace::create(['location_id' => $this->loc->id, 'name' => $name, 'category' => $category, 'emoji' => 'x', 'tip' => 'Demo tip',
            'lat' => 27.7 + $meters / 111195, 'lng' => 85.3, 'walk_minutes' => 2, 'visit_minutes' => 10, 'rating' => 4.4]);
    }

    /** Participating café with two dishes. */
    private function cafe(NearbyPlace $place, array $over = []): Restaurant
    {
        $r = Restaurant::create(array_merge([
            'location_id' => $this->loc->id, 'nearby_place_id' => $place->id, 'name' => $place->name, 'slug' => 'cafe-'.uniqid(),
            'cuisine' => 'Momo', 'prep_buffer_minutes' => 3, 'lat' => $place->lat, 'lng' => $place->lng, 'opening_note' => 'Demo listing · 10:00–20:00',
        ], $over));
        $menu = RestaurantMenu::create(['restaurant_id' => $r->id, 'name' => 'All day']);
        $cat = MenuCategory::create(['restaurant_menu_id' => $menu->id, 'name' => 'Momo']);
        MenuItem::create(['restaurant_id' => $r->id, 'menu_category_id' => $cat->id, 'name' => 'Buff Steamed Momo', 'price' => '180.00', 'prep_minutes' => 8, 'art' => 'momo']);
        MenuItem::create(['restaurant_id' => $r->id, 'menu_category_id' => $cat->id, 'name' => 'Masala Chiya', 'price' => '55.50', 'prep_minutes' => 4, 'art' => 'chiya']);

        return $r;
    }

    private function ticketFor(User $user, int $ahead, int $avg = 4): Ticket
    {
        $s = $this->loc->services()->create(['name' => 'Licence '.uniqid(), 'prefix' => 'D', 'counter' => 1, 'avg_minutes' => $avg,
            'current_number' => 10, 'last_number' => 10 + $ahead, 'documents' => []]);
        Counter::create(['location_id' => $this->loc->id, 'service_id' => $s->id, 'number' => 1, 'label' => 'Counter 1']);

        return app(QueueManager::class)->join($s, $user, []);
    }

    private function order(User $u, Restaurant $r, array $qty): FoodOrder
    {
        return app(PreorderService::class)->place($u, $r, $qty, [], null, 'demo', true);
    }

    // ---------------- Café page ----------------

    public function test_cafe_page_renders_for_participating_cafe_with_menu_photos_and_sections(): void
    {
        $p = $this->place('Hub Momo House');
        $r = $this->cafe($p);
        $this->seed(CafesSeeder::class);
        $u = User::factory()->create();
        $this->ticketFor($u, 8);

        $html = $this->actingAs($u)->get(route('cafes.show', $p))->assertOk()
            ->assertSee('Hub Momo House')->assertSee('Pre-order available')->assertSee('Menu &amp; Pre-order', false)
            ->assertSee('Split a bill here')->assertSee('Your order here')->assertSee('Buff Steamed Momo')
            ->assertSee('Representative photo')->assertSee('Wikimedia Commons')->assertSee('Hours unverified')
            ->assertSee('You have approximately')->assertSee(route('preorder.cart.set', $r->items()->first()->id))
            ->getContent();
        $this->assertStringContainsString('images/cafes/', $html);   // seeded gallery
        $this->assertStringContainsString('images/dishes/', $html);  // dish photos
        $this->assertNotNull($p->fresh()->image_path);
    }

    public function test_cafe_page_for_non_participating_cafe_offers_directions_and_bill_but_no_preorder(): void
    {
        $p = $this->place('Corner Chiya Ghar', 'Cafe');
        $u = User::factory()->create();

        $this->actingAs($u)->get(route('cafes.show', $p))->assertOk()
            ->assertSee('Pre-order not available here')->assertSee('Walk-in only')->assertSee('Directions')
            ->assertSee(route('bills.create', ['place' => $p->id]), false)
            ->assertDontSee('Review &amp; pre-order', false);

        // A paused café is treated the same (and its old preorder URL still 404s).
        $p2 = $this->place('Paused Momo');
        $r2 = $this->cafe($p2, ['accepts_preorders' => false]);
        $this->actingAs($u)->get(route('cafes.show', $p2))->assertOk()->assertSee('Pre-order not available here');
        $this->actingAs($u)->get(route('preorder.restaurant', $r2))->assertNotFound();
    }

    public function test_old_routes_open_the_cafe_page_and_non_cafes_keep_their_place_page(): void
    {
        $p = $this->place('Legacy Momo');
        $r = $this->cafe($p);
        $park = $this->place('Quiet Park', 'Park');
        $u = User::factory()->create();
        $this->get(route('cafes.show', $p))->assertRedirect(route('login'));

        $this->actingAs($u)->get(route('explore.place', $p))->assertOk()->assertSee('Menu &amp; Pre-order', false)->assertSee('Buff Steamed Momo');
        $this->actingAs($u)->get(route('preorder.restaurant', $r))->assertOk()->assertSee('Split a bill here')->assertSee('Buff Steamed Momo');
        $this->actingAs($u)->get(route('explore.place', $park))->assertOk()->assertSee('Quiet Park')->assertDontSee('Menu &amp; Pre-order', false);
        $this->actingAs($u)->get(route('cafes.show', $park))->assertRedirect(route('explore.place', $park));
    }

    // ---------------- Pre-order from the café page ----------------

    public function test_preorder_from_cafe_page_enforces_timing_and_shows_live_order_to_owner_only(): void
    {
        $p = $this->place('Order Momo');
        $r = $this->cafe($p);
        $item = $r->items()->where('name', 'Buff Steamed Momo')->first();
        $u = User::factory()->create();
        $this->ticketFor($u, 1, 1); // ~1 minute left: any order clashes with the turn

        $this->actingAs($u)->get(route('cafes.show', $p))->assertOk();
        $this->actingAs($u)->postJson(route('preorder.cart.set', $item->id), ['qty' => 2])->assertOk()->assertJsonPath('data.count', 2);
        // The café page now shows the cart for this café.
        $this->actingAs($u)->get(route('cafes.show', $p))->assertSee('Review &amp; pre-order', false)->assertSee('2 × Buff Steamed Momo');

        // Timing clash must be acknowledged.
        $this->actingAs($u)->post(route('preorder.place'), ['payment_mode' => 'demo', 'idempotency_key' => 'k-1'])->assertSessionHasErrors();
        $this->assertSame(0, FoodOrder::count());
        $this->actingAs($u)->post(route('preorder.place'), ['payment_mode' => 'demo', 'idempotency_key' => 'k-2', 'acknowledge_conflict' => 1])->assertRedirect();
        $order = FoodOrder::firstOrFail();
        $this->assertSame('conflict', $order->timing_state);

        $this->actingAs($u)->get(route('cafes.show', $p))->assertSee($order->reference())->assertSee('Waiting for the café to accept')
            ->assertSee(route('preorder.order.state', $order), false);

        // Another user never sees it, and cannot read its state.
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('cafes.show', $p))->assertOk()->assertDontSee($order->reference())->assertSee('No active order here');
        $this->actingAs($other)->getJson(route('preorder.order.state', $order))->assertForbidden();
    }

    // ---------------- Split a bill here ----------------

    public function test_split_bill_here_prefills_cafe_and_own_order_items_and_links_the_bill(): void
    {
        $p = $this->place('Bill Momo');
        $r = $this->cafe($p);
        $u = User::factory()->create();
        $order = $this->order($u, $r, [$r->items()->where('name', 'Buff Steamed Momo')->value('id') => 2, $r->items()->where('name', 'Masala Chiya')->value('id') => 3]);

        $this->actingAs($u)->get(route('cafes.show', $p))->assertSee('Split this order')
            ->assertSee(route('bills.create', ['place' => $p->id, 'order' => $order->id]));

        $this->actingAs($u)->get(route('bills.create', ['place' => $p->id, 'order' => $order->id]))->assertOk()
            ->assertSee('Splitting a bill at Bill Momo')->assertSee('value="Bill Momo"', false)
            ->assertSee('value="Buff Steamed Momo"', false)->assertSee('value="Masala Chiya"', false)
            ->assertSee('name="food_order_id" value="'.$order->id.'"', false);

        $this->actingAs($u)->post(route('bills.store'), [
            'title' => 'Bill Momo lunch', 'restaurant_name' => 'Bill Momo', 'nearby_place_id' => $p->id, 'food_order_id' => $order->id,
            'discount_type' => 'none', 'service_charge_percent' => '10', 'vat_percent' => '13', 'vat_on_service_charge' => '1',
            'tip_amount' => '0', 'tip_allocation' => 'equal', 'split_mode' => 'itemized', 'members_can_select' => '1',
            'items' => [
                ['name' => 'Buff Steamed Momo', 'unit_price' => '180', 'quantity' => 2],
                ['name' => 'Masala Chiya', 'unit_price' => '55.50', 'quantity' => 3],
            ],
        ])->assertRedirect();

        $bill = GroupBill::firstOrFail();
        $this->assertSame($p->id, (int) $bill->nearby_place_id);
        $this->assertSame($order->id, (int) $bill->food_order_id);
        // Same 10% service + 13% VAT convention: the bill total equals the food order total to the paisa.
        $this->assertSame((string) $order->total, (string) $bill->fresh()->total);

        // The bill shows on the café page for its participants only.
        $this->actingAs($u)->get(route('cafes.show', $p))->assertSee('Bill Momo lunch');
        $this->actingAs(User::factory()->create())->get(route('cafes.show', $p))->assertDontSee('Bill Momo lunch')->assertSee('No bills here yet');
        $this->actingAs($u)->get(route('bills.show', $bill))->assertSee(route('cafes.show', $p->id), false);
    }

    public function test_split_bill_never_copies_or_links_someone_elses_order(): void
    {
        $p = $this->place('Idor Momo');
        $r = $this->cafe($p);
        $victim = User::factory()->create();
        $theirs = $this->order($victim, $r, [$r->items()->where('name', 'Buff Steamed Momo')->value('id') => 4]);
        $attacker = User::factory()->create();

        $this->actingAs($attacker)->get(route('bills.create', ['place' => $p->id, 'order' => $theirs->id]))->assertOk()
            ->assertSee('Splitting a bill at Idor Momo')->assertDontSee('value="Buff Steamed Momo"', false)
            ->assertDontSee('name="food_order_id"', false)->assertDontSee($theirs->reference());

        $this->actingAs($attacker)->post(route('bills.store'), [
            'title' => 'Sneaky', 'nearby_place_id' => $p->id, 'food_order_id' => $theirs->id,
            'discount_type' => 'none', 'service_charge_percent' => '10', 'vat_percent' => '13', 'tip_amount' => '0',
            'tip_allocation' => 'equal', 'split_mode' => 'equal', 'items' => [['name' => 'Tea', 'unit_price' => '50', 'quantity' => 1]],
        ])->assertRedirect();
        $bill = GroupBill::firstOrFail();
        $this->assertSame($p->id, (int) $bill->nearby_place_id);
        $this->assertNull($bill->food_order_id);

        // An order from a different café is not copied into this café's bill either.
        $p2 = $this->place('Other Momo');
        $r2 = $this->cafe($p2);
        $mine = $this->order($attacker, $r2, [$r2->items()->first()->id => 1]);
        $this->actingAs($attacker)->get(route('bills.create', ['place' => $p->id, 'order' => $mine->id]))
            ->assertDontSee('name="food_order_id"', false);
    }

    // ---------------- Entry points ----------------

    public function test_explore_cards_drawer_data_and_ticket_widgets_link_to_the_cafe_page(): void
    {
        $p = $this->place('Link Momo', 'Restaurant', 120);
        $r = $this->cafe($p);
        $park = $this->place('Link Park', 'Park', 120);
        $u = User::factory()->create();
        $t = $this->ticketFor($u, 12);

        $item = app(RecommendationService::class)->present($p, $this->loc, null, 40);
        $this->assertSame(route('cafes.show', $p), $item['url']);
        $this->assertTrue($item['is_cafe']);
        $this->assertSame(route('explore.place', $park), app(RecommendationService::class)->present($park, $this->loc)['url']);

        $html = $this->actingAs($u)->get(route('explore.index', ['location' => $this->loc->id]))->assertOk()->assertSee('Open café')->getContent();
        $this->assertStringContainsString(route('cafes.show', $p), $html);
        $this->assertStringContainsString(str_replace('/', '\/', route('cafes.show', $p)).'#menu', $html); // drawer "Pre-order" link

        $this->actingAs($u);
        $ex = view('explore.partials.ticket-widget', ['ticket' => $t])->render();
        $this->assertStringContainsString(route('cafes.show', $p), $ex);
        $this->assertStringContainsString('Open café', $ex);
        $po = view('preorder.partials.ticket-widget', ['ticket' => $t])->render();
        $this->assertStringContainsString(route('cafes.show', $p).'#menu', $po);
        $this->assertStringContainsString('Open café', $po);
        $this->assertStringNotContainsString(route('preorder.restaurant', $r), $po);

        $this->get(route('preorder.index'))->assertSee(route('cafes.show', $p), false);
    }

    // ---------------- Photos & credits ----------------

    public function test_credits_page_is_public_and_lists_licences(): void
    {
        $this->get(route('explore.credits'))->assertOk()->assertSee('Photo credits')
            ->assertSee('Wikimedia Commons')->assertSee('CC BY-SA 4.0')->assertSee('commons.wikimedia.org/wiki/File:', false);

        foreach (CafeImages::catalog() as $img) {
            $this->assertMatchesRegularExpression('/^(CC0|CC BY(-SA)? \d\.\d|Public domain)$/', $img['license'], $img['key']);
            $this->assertStringStartsWith('https://commons.wikimedia.org/wiki/File:', $img['source_url']);
            $this->assertFileExists(public_path($img['path']));
        }
    }

    public function test_seeder_is_idempotent_and_missing_images_fall_back_to_illustrations(): void
    {
        $p = $this->place('Seed Momo House');
        $r = $this->cafe($p);
        $tea = $this->place('Seed Chiya Ghar', 'Cafe');
        $park = $this->place('Seed Park', 'Park');

        $this->seed(CafesSeeder::class);
        $first = [$p->fresh()->image_path, $tea->fresh()->image_path, $r->fresh()->image_path, MenuItem::orderBy('id')->pluck('image_path')->all()];
        $this->seed(CafesSeeder::class);
        $this->assertSame($first, [$p->fresh()->image_path, $tea->fresh()->image_path, $r->fresh()->image_path, MenuItem::orderBy('id')->pluck('image_path')->all()]);

        $this->assertStringStartsWith('images/cafes/momo', $p->fresh()->image_path);
        $this->assertStringStartsWith('images/cafes/chiya', $tea->fresh()->image_path);
        $this->assertSame('images/dishes/buff-momo.jpg', MenuItem::where('name', 'Buff Steamed Momo')->value('image_path'));
        $this->assertStringContainsString('Wikimedia Commons', (string) $p->fresh()->image_credit);
        $this->assertNull($park->fresh()->image_path);
        $this->assertCount(4, json_decode($p->fresh()->gallery, true));

        // Missing file → no <img>, the CSS illustration remains.
        $this->assertNull(CafeImages::url('images/cafes/does-not-exist.jpg'));
        $this->assertNull(CafeImages::url('../.env'));
        MenuItem::where('name', 'Masala Chiya')->update(['image_path' => 'images/dishes/missing.jpg']);
        $html = $this->actingAs(User::factory()->create())->get(route('cafes.show', $p))->assertOk()->getContent();
        $this->assertStringNotContainsString('images/dishes/missing.jpg', $html);
        $this->assertStringContainsString('fa-chiya', $html);
    }
}
