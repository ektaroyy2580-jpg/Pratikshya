<?php

namespace Database\Seeders\Modules;

use App\Models\FoodOrder;
use App\Models\Location;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\NearbyPlace;
use App\Models\Restaurant;
use App\Models\RestaurantMenu;
use App\Models\RestaurantStaff;
use App\Models\User;
use App\Services\Preorder\Money;
use Illuminate\Database\Seeder;

/**
 * Cafe preordering demo data — FICTIONAL cafés next to the existing demo
 * locations. Prices are illustrative NPR. Safe to re-run (natural keys:
 * restaurant slug, menu item name per restaurant, user email).
 *
 * Demo accounts (password Demo@1234):
 *   preorder.demo@waitwise.np      — customer
 *   preorder.merchant@waitwise.np  — merchant for the momo house and tea stall
 */
class PreorderSeeder extends Seeder
{
    public function run(): void
    {
        $cafes = [
            [
                'slug' => 'ekantakuna-momo-house', 'name' => 'Ekantakuna Momo House', 'location' => 'Transport Management Office',
                'place' => 'Ekantakuna Momo House', 'cuisine' => 'Momo & Newari snacks', 'art' => 'momo', 'buffer' => 4,
                'tagline' => 'Hand-folded momo, steamed to order while you wait.',
                'pickup' => 'Ground floor, opposite the licence office gate. Show your pickup code at the green counter.',
                'opening' => 'Demo listing · 10:00–20:00 daily', 'contact' => 'Demo contact: 01-0000000 (fictional)', 'walk' => 3,
                'menu' => [
                    'Momo' => [
                        ['Buff Steamed Momo', 'Ten juicy buff dumplings with tomato-timur achar.', '180.00', 12, ['spicy-option'], 'momo'],
                        ['Chicken Jhol Momo', 'Steamed chicken momo in a warm sesame-tomato jhol.', '230.00', 14, ['contains-sesame'], 'jhol'],
                        ['Veg Kothey Momo', 'Pan-fried on one side — crisp base, soft top. Cabbage, paneer, spring onion.', '170.00', 15, ['vegetarian'], 'momo'],
                        ['Chilli C-Momo', 'Fried momo tossed in a sweet-hot chilli glaze with peppers.', '250.00', 16, ['spicy'], 'cmomo'],
                    ],
                    'Sides & drinks' => [
                        ['Aloo Sandeko', 'Spiced potato salad with mustard oil, lemon and fresh coriander.', '120.00', 5, ['vegan', 'spicy'], 'salad'],
                        ['Masala Chiya', 'Milk tea brewed with cardamom, clove and ginger.', '50.00', 4, ['vegetarian'], 'chiya'],
                        ['Lemon Mint Soda', 'Fresh lime, mint and soda — not too sweet.', '90.00', 3, ['vegan'], 'lassi'],
                    ],
                ],
                'unavailable' => ['Chilli C-Momo'],
            ],
            [
                'slug' => 'tripureshwor-tea-stall', 'name' => 'Tripureshwor Tea Stall', 'location' => 'Department of Passports',
                'place' => 'Tripureshwor Tea Stall', 'cuisine' => 'Chiya & street snacks', 'art' => 'chiya', 'buffer' => 2,
                'tagline' => 'Strong chiya and hot snacks, three minutes from the passport hall.',
                'pickup' => 'Tea stall beside the bus stop, left of the main gate. Collect from the steel counter.',
                'opening' => 'Demo listing · 06:30–19:00', 'contact' => 'Demo contact: 01-0000001 (fictional)', 'walk' => 3,
                'menu' => [
                    'Chiya' => [
                        ['Masala Chiya', 'Slow-boiled milk tea with whole spices.', '40.00', 4, ['vegetarian'], 'chiya'],
                        ['Kalo Chiya', 'Black tea with a squeeze of lemon.', '25.00', 3, ['vegan'], 'chiya'],
                        ['Ginger Lemon Honey', 'Hot, soothing and freshly grated.', '70.00', 4, ['vegetarian'], 'chiya'],
                    ],
                    'Snacks' => [
                        ['Sel Roti', 'Crisp ring-shaped rice bread, fried fresh each morning.', '40.00', 3, ['vegan'], 'selroti'],
                        ['Aloo Samosa (2 pcs)', 'Spiced potato & pea filling with tamarind chutney.', '60.00', 6, ['vegetarian'], 'samosa'],
                        ['Aloo Chop', 'Gram-flour coated potato fritter with achar.', '50.00', 7, ['vegan'], 'samosa'],
                        ['Sweet Lassi', 'Thick yoghurt lassi, lightly sweetened.', '110.00', 4, ['vegetarian'], 'lassi'],
                    ],
                ],
                'unavailable' => [],
            ],
            [
                'slug' => 'thapathali-thakali-kitchen', 'name' => 'Thapathali Thakali Kitchen', 'location' => 'Department of Passports',
                'place' => 'Thapathali Thakali Kitchen', 'cuisine' => 'Thakali sets & dal-bhat', 'art' => 'thali', 'buffer' => 6,
                'tagline' => 'Brass-plate Thakali sets with refills of dal and bhat.',
                'pickup' => 'First floor above the pharmacy. Packed sets are handed over at the cash desk.',
                'opening' => 'Demo listing · 10:30–21:00', 'contact' => 'Demo contact: 01-0000002 (fictional)', 'walk' => 5,
                'menu' => [
                    'Thakali sets' => [
                        ['Veg Thakali Set', 'Bhat, kalo dal, gundruk, seasonal tarkari, saag, achar and papad.', '380.00', 14, ['vegetarian'], 'thali'],
                        ['Chicken Thakali Set', 'Full set with local-style chicken curry.', '520.00', 18, [], 'thali'],
                        ['Mutton Thakali Set', 'Full set with slow-cooked mutton curry.', '680.00', 22, [], 'thali'],
                        ['Dhido Set', 'Buckwheat dhido with gundruk jhol and ghee.', '450.00', 20, ['vegetarian', 'gluten-free'], 'dhido'],
                    ],
                    'Small plates' => [
                        ['Gundruk Soup', 'Tangy fermented-greens soup with soybeans.', '120.00', 6, ['vegan'], 'soup'],
                        ['Chiura & Bhatmas', 'Beaten rice with fried soybean and spicy achar.', '150.00', 5, ['vegan', 'spicy'], 'salad'],
                    ],
                ],
                'unavailable' => [],
            ],
            [
                'slug' => 'baneshwor-coffee-lab', 'name' => 'Baneshwor Coffee Lab', 'location' => 'Ward No. 10 Office',
                'place' => 'Baneshwor Coffee Lab', 'cuisine' => 'Coffee & bakery', 'art' => 'croissant', 'buffer' => 3,
                'tagline' => 'Small-batch bakes and Nepali-grown coffee near the ward office.',
                'pickup' => 'Ground floor corner shop, blue door. Pickup shelf is on the right as you enter.',
                'opening' => 'Demo listing · 07:00–19:00', 'contact' => 'Demo contact: 01-0000003 (fictional)', 'walk' => 3,
                'menu' => [
                    'Bakery' => [
                        ['Butter Croissant', 'Flaky, all-butter, baked this morning.', '180.00', 3, ['vegetarian'], 'croissant'],
                        ['Cinnamon Roll', 'Soft roll with cinnamon-brown sugar swirl and glaze.', '210.00', 3, ['vegetarian'], 'bun'],
                        ['Chocolate Fudge Slice', 'Dark chocolate sponge with fudge frosting.', '260.00', 2, ['vegetarian'], 'cake'],
                        ['Chicken Pesto Sandwich', 'Toasted sourdough, grilled chicken, basil pesto, tomato.', '340.00', 8, [], 'sandwich'],
                    ],
                    'Coffee' => [
                        ['Americano', 'Double shot of Nepali highland arabica.', '180.00', 4, ['vegan'], 'coffee'],
                        ['Cappuccino', 'Equal parts espresso, steamed milk and foam.', '220.00', 5, ['vegetarian'], 'coffee'],
                        ['Iced Latte', 'Espresso over milk and ice.', '260.00', 5, ['vegetarian'], 'lassi'],
                    ],
                ],
                'unavailable' => [],
            ],
        ];

        $restaurants = [];
        foreach ($cafes as $c) {
            $loc = Location::where('name', $c['location'])->first();
            if (! $loc) {
                continue; // demo location not present on this install
            }
            $place = NearbyPlace::where('location_id', $loc->id)->where('name', $c['place'])->first();
            $r = Restaurant::updateOrCreate(['slug' => $c['slug']], [
                'location_id' => $loc->id,
                'nearby_place_id' => $place?->id,
                'name' => $c['name'],
                'cuisine' => $c['cuisine'],
                'tagline' => $c['tagline'],
                'art' => $c['art'],
                'pickup_instructions' => $c['pickup'],
                'prep_buffer_minutes' => $c['buffer'],
                'opening_note' => $c['opening'],
                'merchant_contact' => $c['contact'],
                'lat' => $place?->lat ?? $loc->lat,
                'lng' => $place?->lng ?? $loc->lng,
                'walk_minutes' => $place?->walk_minutes ?? $c['walk'],
                'is_demo' => true,
            ]);
            if ($r->wasRecentlyCreated) {
                $r->forceFill(['accepts_preorders' => true])->save();
            }
            $menu = RestaurantMenu::firstOrCreate(['restaurant_id' => $r->id, 'name' => 'All day'], ['is_active' => true]);
            $sortCat = 0;
            foreach ($c['menu'] as $catName => $items) {
                $cat = MenuCategory::updateOrCreate(['restaurant_menu_id' => $menu->id, 'name' => $catName], ['sort' => $sortCat++]);
                foreach ($items as $i => [$name, $desc, $price, $prep, $tags, $art]) {
                    $item = MenuItem::updateOrCreate(['restaurant_id' => $r->id, 'name' => $name], [
                        'menu_category_id' => $cat->id, 'description' => $desc, 'price' => $price,
                        'prep_minutes' => $prep, 'dietary_tags' => $tags, 'art' => $art, 'sort' => $i,
                    ]);
                    if ($item->wasRecentlyCreated) {
                        $item->forceFill(['available' => ! in_array($name, $c['unavailable'], true)])->save();
                    }
                }
            }
            $restaurants[$c['slug']] = $r;
        }

        // A non-participating business: listed nowhere in preorder, shows the "participating only" rule.
        $hospital = Location::whereIn('name', ['Kathmandu City Hospital OPD', 'Bir Hospital OPD'])->first();
        if ($hospital) {
            $canteen = Restaurant::updateOrCreate(['slug' => 'hospital-canteen-cafe'], [
                'location_id' => $hospital->id,
                'nearby_place_id' => NearbyPlace::where('location_id', $hospital->id)->where('name', 'Hospital Canteen Cafe')->value('id'),
                'name' => 'Hospital Canteen Cafe', 'cuisine' => 'Canteen', 'art' => 'chiya',
                'tagline' => 'Not taking preorders (demo of a non-participating business).',
                'opening_note' => 'Demo listing · walk-in only', 'walk_minutes' => 1, 'is_demo' => true,
                'lat' => $hospital->lat, 'lng' => $hospital->lng,
            ]);
            $canteen->forceFill(['accepts_preorders' => false])->save();
        }

        // Demo accounts
        $customer = User::firstOrCreate(['email' => 'preorder.demo@waitwise.np'], ['name' => 'Preorder Demo', 'password' => 'Demo@1234']);
        $merchant = User::firstOrCreate(['email' => 'preorder.merchant@waitwise.np'], ['name' => 'Cafe Merchant Demo', 'password' => 'Demo@1234']);
        foreach (['ekantakuna-momo-house', 'tripureshwor-tea-stall'] as $slug) {
            if (isset($restaurants[$slug])) {
                RestaurantStaff::firstOrCreate(['user_id' => $merchant->id, 'restaurant_id' => $restaurants[$slug]->id], ['role' => 'merchant']);
            }
        }

        // One past (collected) demo order so order history isn't empty.
        if (isset($restaurants['tripureshwor-tea-stall']) && ! FoodOrder::where('idempotency_key', 'seed-preorder-demo-1')->exists()) {
            $r = $restaurants['tripureshwor-tea-stall'];
            $chiya = MenuItem::where('restaurant_id', $r->id)->where('name', 'Masala Chiya')->first();
            $roti = MenuItem::where('restaurant_id', $r->id)->where('name', 'Sel Roti')->first();
            if ($chiya && $roti) {
                $lines = [[$chiya, 2], [$roti, 2]];
                $sub = array_sum(array_map(fn ($l) => $l[0]->pricePaisa() * $l[1], $lines));
                $svc = Money::percent($sub, 10);
                $vat = Money::percent($sub + $svc, 13);
                $at = now()->subDays(2)->setTime(11, 20);
                $order = new FoodOrder(['special_instructions' => 'Less sugar in the chiya, please.']);
                $order->forceFill([
                    'user_id' => $customer->id, 'restaurant_id' => $r->id, 'status' => 'collected',
                    'subtotal' => Money::fromPaisa($sub), 'service_charge' => Money::fromPaisa($svc),
                    'vat' => Money::fromPaisa($vat), 'total' => Money::fromPaisa($sub + $svc + $vat),
                    'payment_mode' => 'demo', 'payment_status' => 'demo_paid', 'prep_minutes' => 7,
                    'estimated_ready_at' => $at->copy()->addMinutes(7), 'pickup_code' => '4821',
                    'timing_state' => 'no_ticket', 'idempotency_key' => 'seed-preorder-demo-1',
                    'accepted_at' => $at->copy()->addMinute(), 'preparing_at' => $at->copy()->addMinutes(2),
                    'ready_at' => $at->copy()->addMinutes(8), 'collected_at' => $at->copy()->addMinutes(12),
                    'created_at' => $at, 'updated_at' => $at->copy()->addMinutes(12),
                ])->save();
                foreach ($lines as [$item, $qty]) {
                    $order->items()->create([
                        'menu_item_id' => $item->id, 'name' => $item->name, 'art' => $item->art, 'qty' => $qty,
                        'unit_price' => $item->price, 'line_total' => Money::fromPaisa($item->pricePaisa() * $qty),
                        'prep_minutes' => $item->prep_minutes,
                    ]);
                }
            }
        }
    }
}
