<?php

namespace Tests\Feature\Modules;

use App\Http\Controllers\Bills\BillInviteController;
use App\Models\BillParticipant;
use App\Models\BillPayment;
use App\Models\GroupBill;
use App\Models\User;
use App\Services\Bills\BillCalculator;
use App\Services\Bills\BillManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillsTest extends TestCase
{
    use RefreshDatabase;

    private function calc(): BillCalculator
    {
        return app(BillCalculator::class);
    }

    private function input(array $over = []): array
    {
        return array_merge([
            'discount_type' => 'none', 'discount_value' => 0, 'service_bp' => 0, 'vat_bp' => 0, 'vat_on_service' => true,
            'tip' => 0, 'tip_allocation' => 'equal', 'split_mode' => 'equal', 'participants' => [], 'items' => [],
        ], $over);
    }

    private function people(int $n): array
    {
        return array_map(fn ($i) => ['id' => $i, 'name' => 'P'.$i, 'custom' => null, 'custom_tip' => null], range(1, $n));
    }

    private function billData(array $over = []): array
    {
        return array_merge([
            'title' => 'Momo night', 'restaurant_name' => 'Demo Momo House', 'discount_type' => 'none', 'discount_value' => '0',
            'service_charge_percent' => '0', 'vat_percent' => '0', 'vat_on_service_charge' => '1', 'tip_amount' => '0',
            'tip_allocation' => 'equal', 'split_mode' => 'equal', 'members_can_select' => '1',
            'items' => [['name' => 'Feast', 'unit_price' => '2400', 'quantity' => 1, 'is_shared' => '1']],
        ], $over);
    }

    /** Owner + n-1 joined friends, created through the real HTTP + service flow. */
    private function billWithPeople(int $n = 4, array $over = []): array
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('bills.store'), $this->billData($over))->assertRedirect();
        $bill = GroupBill::firstOrFail();
        $friends = [];
        for ($i = 1; $i < $n; $i++) {
            $f = User::factory()->create();
            app(BillManager::class)->join($bill, $f);
            $friends[] = $f;
        }

        return [$bill->fresh(), $owner, $friends];
    }

    // ---------------- Arithmetic ----------------

    public function test_2400_split_equally_among_4_is_600_each(): void
    {
        $r = $this->calc()->calculate($this->input([
            'participants' => $this->people(4),
            'items' => [['id' => 1, 'name' => 'Dinner', 'unit' => 240000, 'qty' => 1, 'shared' => true, 'alloc' => []]],
        ]));
        $this->assertSame(240000, $r['totals']['grand_total']);
        $this->assertSame([60000, 60000, 60000, 60000], array_values(array_column($r['people'], 'total')));
        $this->assertTrue($r['verification']['ok']);

        // and through the HTTP flow + stored bill
        [$bill, $owner] = $this->billWithPeople(4);
        $calc = $this->calc()->forBill($bill);
        $this->assertSame([60000, 60000, 60000, 60000], array_values(array_column($calc['people'], 'total')));
        $this->assertSame('2400.00', $bill->total);
        $this->actingAs($owner)->get(route('bills.show', $bill))->assertOk()->assertSee('600.00')->assertSee('All allocations add up');
    }

    public function test_uneven_totals_sum_exactly_with_largest_remainder(): void
    {
        $r = $this->calc()->calculate($this->input([
            'participants' => $this->people(3),
            'items' => [['id' => 1, 'name' => 'Thali', 'unit' => 100000, 'qty' => 1, 'shared' => true, 'alloc' => []]],
        ]));
        $totals = array_values(array_column($r['people'], 'total'));
        $this->assertSame([33334, 33333, 33333], $totals);
        $this->assertSame(100000, array_sum($totals));

        // Many awkward combinations always reconcile exactly.
        foreach ([[1, 7], [99999, 6], [123457, 11], [1, 3], [500, 30]] as [$amount, $n]) {
            $r = $this->calc()->calculate($this->input([
                'participants' => $this->people($n), 'service_bp' => 1000, 'vat_bp' => 1300, 'tip' => 777,
                'items' => [['id' => 1, 'name' => 'X', 'unit' => $amount, 'qty' => 3, 'shared' => true, 'alloc' => []]],
            ]));
            $sum = array_sum(array_column($r['people'], 'total'));
            $this->assertSame($r['totals']['grand_total'], $sum, "amount=$amount n=$n");
            $this->assertLessThanOrEqual(1, max(array_column($r['people'], 'total')) - min(array_column($r['people'], 'total')));
            $this->assertTrue($r['verification']['ok']);
        }
        $this->assertSame([2, 1, 1], array_values(BillCalculator::largestRemainder(4, [1, 1, 1])));
    }

    public function test_itemized_shared_discount_service_vat_tip_reconcile(): void
    {
        // A orders momo (300 x2), B orders thukpa (450); a shared sekuwa (800) split A:B:C = 1:1:2; C orders a lassi (150).
        $items = [
            ['id' => 1, 'name' => 'Buff momo', 'unit' => 30000, 'qty' => 2, 'shared' => false, 'alloc' => [1 => 1]],
            ['id' => 2, 'name' => 'Thukpa', 'unit' => 45000, 'qty' => 1, 'shared' => false, 'alloc' => [2 => 1]],
            ['id' => 3, 'name' => 'Sekuwa platter', 'unit' => 80000, 'qty' => 1, 'shared' => true, 'alloc' => [1 => 1, 2 => 1, 3 => 2]],
            ['id' => 4, 'name' => 'Lassi', 'unit' => 15000, 'qty' => 1, 'shared' => false, 'alloc' => [3 => 1]],
        ];
        $r = $this->calc()->calculate($this->input([
            'split_mode' => 'itemized', 'participants' => $this->people(3), 'items' => $items,
            'discount_type' => 'percent', 'discount_value' => 500,  // 5%
            'service_bp' => 1000, 'vat_bp' => 1300, 'vat_on_service' => true, 'tip' => 10001, 'tip_allocation' => 'proportional',
        ]));
        $t = $r['totals'];
        $this->assertSame(200000, $t['subtotal']);
        $this->assertSame(10000, $t['discount']);                 // discount before charges
        $this->assertSame(19000, $t['service']);                  // 10% of 1,900
        $this->assertSame(27170, $t['vat']);                      // 13% of (1,900 + 190) = 271.70
        $this->assertSame(10001, $t['tip']);
        $this->assertSame(190000 + 19000 + 27170 + 10001, $t['grand_total']);

        $p = $r['people'];
        $this->assertSame(60000 + 20000, $p[1]['items']);         // momo + 1/4 sekuwa
        $this->assertSame(45000 + 20000, $p[2]['items']);
        $this->assertSame(40000 + 15000, $p[3]['items']);
        foreach (['items' => 'subtotal', 'discount' => 'discount', 'service' => 'service', 'vat' => 'vat', 'tip' => 'tip', 'total' => 'grand_total'] as $col => $tot) {
            $this->assertSame($t[$tot], array_sum(array_column($p, $col)), "column $col");
        }
        foreach ($p as $row) {
            $this->assertSame($row['items'] - $row['discount'] + $row['service'] + $row['vat'] + $row['tip'], $row['total']);
        }
        $this->assertTrue($r['verification']['ok']);

        // VAT order is configurable: VAT on net only.
        $r2 = $this->calc()->calculate($this->input(['split_mode' => 'itemized', 'participants' => $this->people(3), 'items' => $items,
            'discount_type' => 'amount', 'discount_value' => 10000, 'service_bp' => 1000, 'vat_bp' => 1300, 'vat_on_service' => false]));
        $this->assertSame(24700, $r2['totals']['vat']);           // 13% of 1,900

        // Same calculation stored and served through the bill page.
        [$bill, $owner, $friends] = $this->billWithPeople(3, [
            'split_mode' => 'itemized', 'discount_type' => 'percent', 'discount_value' => '5', 'service_charge_percent' => '10',
            'vat_percent' => '13', 'tip_amount' => '100.01', 'tip_allocation' => 'proportional',
            'items' => [
                ['name' => 'Buff momo', 'unit_price' => '300', 'quantity' => 2, 'is_shared' => '0'],
                ['name' => 'Thukpa', 'unit_price' => '450', 'quantity' => 1, 'is_shared' => '0'],
                ['name' => 'Sekuwa platter', 'unit_price' => '800', 'quantity' => 1, 'is_shared' => '1'],
                ['name' => 'Lassi', 'unit_price' => '150', 'quantity' => 1, 'is_shared' => '0'],
            ],
        ]);
        $ids = $bill->items()->pluck('id')->all();
        $ps = $bill->joinedParticipants()->pluck('id')->all();
        $this->actingAs($owner)->post(route('bills.allocations', $bill), [
            'alloc' => [$ids[0] => [$ps[0] => 1], $ids[1] => [$ps[1] => 1], $ids[2] => [$ps[0] => 1, $ps[1] => 1, $ps[2] => 1], $ids[3] => [$ps[2] => 1]],
            'weight' => [$ids[2] => [$ps[2] => 2]],
        ])->assertRedirect();
        $stored = $this->calc()->forBill($bill->fresh());
        $this->assertSame(array_column($p, 'total'), array_column($stored['people'], 'total'));
        $this->assertSame(BillCalculator::toDecimal($t['grand_total']), $bill->fresh()->total);
    }

    public function test_custom_amounts_must_equal_total(): void
    {
        [$bill, $owner] = $this->billWithPeople(3, ['split_mode' => 'custom']);
        $ps = $bill->joinedParticipants()->pluck('id')->all();

        $this->actingAs($owner)->post(route('bills.custom', $bill), ['amounts' => [$ps[0] => '1000', $ps[1] => '1000', $ps[2] => '300']]);
        $r = $this->calc()->forBill($bill->fresh());
        $this->assertFalse($r['verification']['ok']);
        $this->assertSame(10000, $r['verification']['difference']);
        // cannot lock an unbalanced bill
        $this->actingAs($owner)->post(route('bills.status', $bill), ['to' => 'settling'])->assertSessionHasErrors('status');
        $this->assertSame('open', $bill->fresh()->status);

        $this->actingAs($owner)->post(route('bills.custom', $bill), ['amounts' => [$ps[0] => '1000', $ps[1] => '1000', $ps[2] => '400']]);
        $r = $this->calc()->forBill($bill->fresh());
        $this->assertTrue($r['verification']['ok']);
        $this->assertSame(240000, $r['verification']['sum']);
        $this->actingAs($owner)->post(route('bills.status', $bill), ['to' => 'settling'])->assertRedirect(route('bills.settle', $bill));
        $this->assertSame('settling', $bill->fresh()->status);
    }

    // ---------------- Settlement honesty ----------------

    public function test_claim_is_not_confirmed_and_only_owner_confirms(): void
    {
        [$bill, $owner, [$a, $b]] = $this->billWithPeople(3, ['items' => [['name' => 'Feast', 'unit_price' => '900', 'quantity' => 1, 'is_shared' => '1']]]);
        $this->actingAs($owner)->post(route('bills.status', $bill), ['to' => 'settling']);

        $this->actingAs($a)->post(route('bills.payments.claim', $bill), ['amount' => '300', 'method' => 'esewa_link'])->assertRedirect();
        $pay = BillPayment::firstOrFail();
        $this->assertSame('claimed', $pay->status);
        $bal = app(BillManager::class)->balances($bill->fresh());
        $aRow = $bal['rows'][$pay->bill_participant_id];
        $this->assertSame(30000, $aRow['remaining']);   // a claim does not reduce what is owed
        $this->assertSame(0, $bal['collected']);

        // the payer and another member cannot confirm
        $this->actingAs($a)->post(route('bills.payments.confirm', [$bill, $pay]))->assertForbidden();
        $this->actingAs($b)->post(route('bills.payments.confirm', [$bill, $pay]))->assertForbidden();
        $this->assertSame('claimed', $pay->fresh()->status);
        // can't over-claim
        $this->actingAs($a)->post(route('bills.payments.claim', $bill), ['amount' => '1', 'method' => 'cash'])->assertSessionHasErrors('amount');

        // owner disputes, member re-claims, owner confirms
        $this->actingAs($owner)->post(route('bills.payments.dispute', [$bill, $pay]))->assertRedirect();
        $this->assertSame('disputed', $pay->fresh()->status);
        $this->actingAs($owner)->post(route('bills.payments.confirm', [$bill, $pay]))->assertRedirect();
        $this->assertSame('confirmed_by_owner', $pay->fresh()->status);
        $this->assertSame(0, app(BillManager::class)->balances($bill->fresh())['rows'][$pay->bill_participant_id]['remaining']);
        $this->assertSame('settling', $bill->fresh()->status); // b has not paid

        $this->actingAs($b)->post(route('bills.payments.claim', $bill), ['amount' => '300', 'method' => 'cash']);
        $this->assertSame('settling', $bill->fresh()->status); // claim alone never settles
        $this->actingAs($owner)->post(route('bills.payments.confirm', [$bill, BillPayment::latest('id')->first()]));
        $this->assertSame('settled', $bill->fresh()->status);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $a->id]);
    }

    public function test_payment_of_another_bill_cannot_be_confirmed(): void
    {
        [$bill1, $owner1, [$a]] = $this->billWithPeople(2);
        $this->actingAs($owner1)->post(route('bills.status', $bill1), ['to' => 'settling']);
        $this->actingAs($a)->post(route('bills.payments.claim', $bill1), ['amount' => '100', 'method' => 'cash']);
        $pay = BillPayment::firstOrFail();

        $owner2 = User::factory()->create();
        $this->actingAs($owner2)->post(route('bills.store'), $this->billData(['title' => 'Other']));
        $bill2 = GroupBill::where('title', 'Other')->firstOrFail();
        $this->actingAs($owner2)->post(route('bills.payments.confirm', [$bill2, $pay]))->assertNotFound();
        $this->assertSame('claimed', $pay->fresh()->status);
    }

    // ---------------- Access control & invites ----------------

    public function test_non_participant_cannot_view_or_modify(): void
    {
        [$bill, $owner, [$member]] = $this->billWithPeople(2);
        $stranger = User::factory()->create();
        foreach (['bills.show', 'bills.settle', 'bills.calculation', 'bills.edit'] as $r) {
            $this->actingAs($stranger)->get(route($r, $bill))->assertNotFound();
        }
        $this->actingAs($stranger)->post(route('bills.update', $bill), $this->billData(['title' => 'Hacked']))->assertNotFound();
        $this->actingAs($stranger)->post(route('bills.invite.rotate', $bill))->assertNotFound();
        $this->assertSame('Momo night', $bill->fresh()->title);
        // members can view but not edit owner settings
        $this->actingAs($member)->get(route('bills.show', $bill))->assertOk();
        $this->actingAs($member)->post(route('bills.update', $bill), $this->billData(['title' => 'Hacked']))->assertForbidden();
        $this->actingAs($member)->post(route('bills.status', $bill), ['to' => 'archived'])->assertForbidden();
        $this->assertSame('Momo night', $bill->fresh()->title);
        // guests are sent to login
        $this->app['auth']->forgetGuards();
        $this->get(route('bills.show', $bill))->assertRedirect(route('login'));
        // the invite token is never stored in plain text
        $this->assertSame(64, strlen($bill->getRawOriginal('invite_token_hash')));
    }

    public function test_join_requires_valid_token_and_explicit_confirmation(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('bills.store'), $this->billData());
        $bill = GroupBill::firstOrFail();
        $token = $bill->plainInviteToken();
        $this->assertSame(40, strlen($token));
        $friend = User::factory()->create();

        // guest → login (and comes back afterwards via intended URL)
        $this->app['auth']->forgetGuards();
        $this->get(route('bills.join', $token))->assertRedirect(route('login'));

        // viewing the link does NOT join
        $this->actingAs($friend)->get(route('bills.join', $token))->assertOk()->assertSee('Join this bill');
        $this->assertNull($bill->participantFor($friend));
        // wrong token
        $this->actingAs($friend)->post(route('bills.join.confirm', str_repeat('a', 40)))->assertNotFound();
        $this->assertNull($bill->participantFor($friend));
        // explicit confirmation joins
        $this->actingAs($friend)->post(route('bills.join.confirm', $token))->assertRedirect(route('bills.show', $bill));
        $this->assertNotNull($bill->participantFor($friend));
    }

    public function test_revoked_rotated_and_expired_tokens_are_rejected(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('bills.store'), $this->billData());
        $bill = GroupBill::firstOrFail();
        $old = $bill->plainInviteToken();
        $friend = User::factory()->create();

        $this->actingAs($owner)->post(route('bills.invite.rotate', $bill))->assertRedirect();
        $new = $bill->fresh()->plainInviteToken();
        $this->assertNotSame($old, $new);
        $this->actingAs($friend)->get(route('bills.join', $old))->assertNotFound();
        $this->actingAs($friend)->post(route('bills.join.confirm', $old))->assertNotFound();

        $this->actingAs($owner)->post(route('bills.invite.revoke', $bill))->assertRedirect();
        $this->actingAs($friend)->get(route('bills.join', $new))->assertNotFound();
        $this->actingAs($friend)->post(route('bills.join.confirm', $new))->assertNotFound();

        $bill->refresh();
        $fresh = $bill->rotateInviteToken();
        $this->travel(8)->days();
        $this->actingAs($friend)->post(route('bills.join.confirm', $fresh))->assertNotFound();
        $this->assertNull($bill->participantFor($friend));
    }

    public function test_email_invite_gives_identical_response_for_unknown_email(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('bills.store'), $this->billData());
        $bill = GroupBill::firstOrFail();
        $known = User::factory()->create(['email' => 'friend@example.test']);

        $r1 = $this->actingAs($owner)->post(route('bills.invite.email', $bill), ['email' => 'friend@example.test']);
        $r2 = $this->actingAs($owner)->post(route('bills.invite.email', $bill), ['email' => 'nobody-here@example.test']);
        $r3 = $this->actingAs($owner)->post(route('bills.invite.email', $bill), ['email' => 'friend@example.test']); // duplicate
        foreach ([$r1, $r2, $r3] as $r) {
            $r->assertRedirect(route('bills.show', $bill).'#invite');
            $r->assertSessionHas('ok', BillInviteController::EMAIL_INVITE_RESPONSE);
        }
        $this->assertSame($r1->headers->get('Location'), $r2->headers->get('Location'));

        $invite = BillParticipant::where('user_id', $known->id)->firstOrFail();
        $this->assertSame('invited', $invite->status);
        $this->assertSame(1, $known->notifications()->count());
        $this->assertNull($bill->participantFor($known)); // pending until accepted
        $this->actingAs($known)->get(route('bills.show', $bill))->assertNotFound();

        // someone else cannot accept this invitation
        $this->actingAs(User::factory()->create())->post(route('bills.invitations.accept', $invite))->assertNotFound();
        $this->actingAs($known)->post(route('bills.invitations.accept', $invite))->assertRedirect(route('bills.show', $bill));
        $this->assertNotNull($bill->participantFor($known));
    }

    public function test_pages_render_for_owner_member_and_invitee(): void
    {
        [$bill, $owner, [$member]] = $this->billWithPeople(2, ['split_mode' => 'itemized', 'items' => [
            ['name' => 'Chowmein', 'unit_price' => '250', 'quantity' => 1, 'is_shared' => '0'],
        ]]);
        // unassigned item blocks settlement
        $this->actingAs($owner)->post(route('bills.status', $bill), ['to' => 'settling'])->assertSessionHasErrors('status');
        $invitee = User::factory()->create();
        app(BillManager::class)->inviteByEmail($bill, $owner, $invitee->email);

        $this->actingAs($owner)->get(route('bills.index'))->assertOk()->assertSee('Momo night');
        $this->actingAs($owner)->get(route('bills.create'))->assertOk();
        $this->actingAs($owner)->get(route('bills.edit', $bill))->assertOk()->assertSee('Chowmein');
        $this->actingAs($owner)->get(route('bills.show', $bill))->assertOk()->assertSee('Who had what')->assertSee('Invite friends');
        $this->actingAs($member)->get(route('bills.show', $bill))->assertOk()->assertDontSee('Invite friends');
        $this->actingAs($member)->get(route('bills.settle', $bill))->assertOk();
        $this->actingAs($member)->getJson(route('bills.calculation', $bill))->assertOk()->assertJsonStructure(['message', 'data' => ['calculation' => ['totals', 'people', 'verification']]]);
        $this->actingAs($invitee)->get(route('bills.index'))->assertOk()->assertSee('Invitations');
    }

    public function test_member_item_selection_respects_owner_setting(): void
    {
        [$bill, $owner, [$member]] = $this->billWithPeople(2, ['split_mode' => 'itemized', 'items' => [
            ['name' => 'Chowmein', 'unit_price' => '250', 'quantity' => 1, 'is_shared' => '0'],
        ]]);
        $item = $bill->items()->first();
        $this->actingAs($member)->postJson(route('bills.items.mine', [$bill, $item]), ['on' => true])->assertOk()
            ->assertJsonPath('data.calculation.verification.ok', true);
        $mine = $bill->participantFor($member);
        $this->assertSame(25000, $this->calc()->forBill($bill->fresh())['people'][$mine->id]['total']);

        $bill->forceFill(['members_can_select' => false])->save();
        $this->actingAs($member)->postJson(route('bills.items.mine', [$bill, $item]), ['on' => false])->assertStatus(422);
        $this->assertSame(25000, $this->calc()->forBill($bill->fresh())['people'][$mine->id]['total']);

        // an item of another bill cannot be toggled through this bill (IDOR)
        $owner2 = User::factory()->create();
        $this->actingAs($owner2)->post(route('bills.store'), $this->billData(['title' => 'Other']));
        $foreignItem = GroupBill::where('title', 'Other')->firstOrFail()->items()->first();
        $this->actingAs($member)->postJson(route('bills.items.mine', [$bill, $foreignItem]), ['on' => true])->assertNotFound();
    }
}
