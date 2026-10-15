<?php

namespace Database\Seeders\Modules;

use App\Models\BillPayment;
use App\Models\GroupBill;
use App\Models\User;
use App\Services\Bills\BillManager;
use Illuminate\Database\Seeder;

/**
 * Demo data for group bill splitting (fictional people & restaurant, clearly labelled DEMO).
 * Safe to re-run: bills are matched by owner + title and only built once.
 *   bills.demo@waitwise.np / Demo@1234      — owner of both demo bills
 *   bills.friend1@waitwise.np / Demo@1234   — participant
 *   bills.friend2@waitwise.np / Demo@1234   — participant
 */
class BillsSeeder extends Seeder
{
    public function run(): void
    {
        $owner = $this->user('bills.demo@waitwise.np', 'Anish Demo');
        $f1 = $this->user('bills.friend1@waitwise.np', 'Prakriti Demo');
        $f2 = $this->user('bills.friend2@waitwise.np', 'Sujan Demo');
        $m = app(BillManager::class);

        // 1) Itemized momo dinner — open, ready for item assignment.
        if (! GroupBill::where('owner_id', $owner->id)->where('title', 'Momo dinner (demo)')->exists()) {
            $bill = $m->create($owner, [
                'title' => 'Momo dinner (demo)', 'restaurant_name' => 'Demo Momo Ghar, Thamel (fictional)',
                'discount_type' => 'none', 'discount_value' => '0', 'service_charge_percent' => '10', 'vat_percent' => '13',
                'vat_on_service_charge' => true, 'tip_amount' => '100', 'tip_allocation' => 'equal', 'split_mode' => 'itemized',
                'members_can_select' => true, 'payment_note' => 'Demo only — cash or eSewa to Anish (no real account).',
                'items' => [
                    ['name' => 'Buff steam momo', 'unit_price' => '220', 'quantity' => 2, 'is_shared' => false],
                    ['name' => 'Chicken jhol momo', 'unit_price' => '260', 'quantity' => 1, 'is_shared' => false],
                    ['name' => 'Veg kothey momo', 'unit_price' => '200', 'quantity' => 1, 'is_shared' => false],
                    ['name' => 'Sekuwa platter', 'unit_price' => '650', 'quantity' => 1, 'is_shared' => true],
                    ['name' => 'Masala tea', 'unit_price' => '60', 'quantity' => 3, 'is_shared' => true],
                ],
            ]);
            $p1 = $m->join($bill, $f1);
            $p2 = $m->join($bill, $f2);
            $items = $bill->items()->get()->keyBy('name');
            $po = $bill->participantFor($owner);
            $m->setAllocations($bill, [
                $items['Buff steam momo']->id => [$po->id => 1],
                $items['Chicken jhol momo']->id => [$p1->id => 1],
                $items['Veg kothey momo']->id => [$p2->id => 1],
                $items['Sekuwa platter']->id => [$po->id => 1, $p1->id => 1, $p2->id => 2],
                $items['Masala tea']->id => [$po->id => 1, $p1->id => 1, $p2->id => 1],
            ]);
        }

        // 2) Equal-split lunch — NPR 2,400 between 3 people, settling, with one CLAIMED (unconfirmed) payment.
        if (! GroupBill::where('owner_id', $owner->id)->where('title', 'Team lunch (demo)')->exists()) {
            $bill = $m->create($owner, [
                'title' => 'Team lunch (demo)', 'restaurant_name' => 'Demo Thakali Kitchen (fictional)',
                'discount_type' => 'none', 'discount_value' => '0', 'service_charge_percent' => '0', 'vat_percent' => '0',
                'vat_on_service_charge' => true, 'tip_amount' => '0', 'tip_allocation' => 'equal', 'split_mode' => 'equal',
                'members_can_select' => false, 'payment_note' => null,
                'items' => [
                    ['name' => 'Thakali set', 'unit_price' => '450', 'quantity' => 4, 'is_shared' => true],
                    ['name' => 'Lassi', 'unit_price' => '150', 'quantity' => 4, 'is_shared' => true],
                ],
            ]);
            $p1 = $m->join($bill, $f1);
            $m->join($bill, $f2);
            $m->transition($bill, 'settling');
            $m->claimPayment($bill->fresh(), $p1, ['amount' => '800', 'method' => 'demo', 'reference' => 'DEMO-001', 'note' => 'Demo claim — owner has not confirmed']);
        }
    }

    private function user(string $email, string $name): User
    {
        return User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'Demo@1234', 'email_verified_at' => now()]);
    }
}
