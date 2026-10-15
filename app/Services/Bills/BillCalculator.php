<?php

namespace App\Services\Bills;

use App\Models\GroupBill;
use InvalidArgumentException;

/**
 * Group bill calculation engine. Everything is integer paisa (1 NPR = 100 paisa); percentages are
 * integer basis points (13% = 1300). No floats are used for money anywhere.
 *
 * Calculation order (documented, configurable per bill):
 *   1. item total      = unit price × quantity
 *   2. subtotal        = Σ item totals
 *   3. discount        = fixed amount (capped at subtotal) or subtotal × discount%      ← discount is applied BEFORE charges
 *   4. net             = subtotal − discount
 *   5. service charge  = net × service%                                                  (Nepal: typically 10%)
 *   6. VAT             = (net + service charge) × VAT%   when vat_on_service = true       (Nepal convention, default)
 *                        net × VAT%                       when vat_on_service = false
 *   7. pre-tip total   = net + service charge + VAT
 *   8. grand total     = pre-tip total + tip
 * Each percentage is rounded half-up to the nearest paisa.
 *
 * Splitting:
 *   - equal:    everyone has weight 1.
 *   - itemized: each item's total is split across its assignees by share weight; unassigned items are split
 *               equally across everyone (and flagged as a warning). A person's item total is their weight for
 *               discount / service charge / VAT.
 *   - custom:   the owner enters an amount per person; they must add up exactly to the grand total.
 * Charges are allocated cumulatively (net, net+service, pre-tip[, grand]) with the largest-remainder method, so every
 * person's total is as fair as possible and every column sums EXACTLY to its bill-level total.
 * Tip: equal (weight 1 each), proportional (to each person's pre-tip share) or custom (must sum to the tip).
 */
class BillCalculator
{
    /** Builds the engine input from a stored bill (joined participants only). */
    public function inputFor(GroupBill $bill): array
    {
        $bill->loadMissing(['items.allocations', 'joinedParticipants']);
        $people = $bill->joinedParticipants;
        $ids = $people->pluck('id')->all();

        return [
            'discount_type' => $bill->discount_type,
            'discount_value' => $bill->discount_type === 'percent' ? self::toBasisPoints($bill->discount_value) : self::toPaisa($bill->discount_value),
            'service_bp' => self::toBasisPoints($bill->service_charge_percent),
            'vat_bp' => self::toBasisPoints($bill->vat_percent),
            'vat_on_service' => (bool) $bill->vat_on_service_charge,
            'tip' => self::toPaisa($bill->tip_amount),
            'tip_allocation' => $bill->tip_allocation,
            'split_mode' => $bill->split_mode,
            'participants' => $people->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->display_name,
                'custom' => $p->custom_amount !== null ? self::toPaisa($p->custom_amount) : null,
                'custom_tip' => $p->custom_tip !== null ? self::toPaisa($p->custom_tip) : null,
            ])->values()->all(),
            'items' => $bill->items->map(fn ($i) => [
                'id' => $i->id, 'name' => $i->name, 'unit' => self::toPaisa($i->unit_price), 'qty' => (int) $i->quantity,
                'shared' => (bool) $i->is_shared,
                'alloc' => $i->allocations->filter(fn ($a) => in_array($a->bill_participant_id, $ids, true) && $a->weight > 0)
                    ->mapWithKeys(fn ($a) => [$a->bill_participant_id => (int) $a->weight])->all(),
            ])->values()->all(),
        ];
    }

    public function forBill(GroupBill $bill): array
    {
        return $this->calculate($this->inputFor($bill));
    }

    public function calculate(array $in): array
    {
        $splitMode = $in['split_mode'] ?? 'equal';
        $tipMode = $in['tip_allocation'] ?? 'equal';
        if (! in_array($splitMode, GroupBill::SPLIT_MODES, true) || ! in_array($tipMode, GroupBill::TIP_ALLOCATIONS, true)) {
            throw new InvalidArgumentException('Unknown split or tip mode.');
        }
        $people = array_values($in['participants'] ?? []);
        $pids = array_map(fn ($p) => $p['id'], $people);
        $errors = [];
        $warnings = [];

        // ---- 1–8: bill-level totals ----
        $items = [];
        $subtotal = 0;
        foreach ($in['items'] ?? [] as $it) {
            $total = max(0, (int) $it['unit']) * max(0, (int) $it['qty']);
            $subtotal += $total;
            $items[] = $it + ['total' => $total];
        }
        $discount = match ($in['discount_type'] ?? 'none') {
            'amount' => min(max(0, (int) $in['discount_value']), $subtotal),
            'percent' => min(self::pct($subtotal, min(10000, max(0, (int) $in['discount_value']))), $subtotal),
            default => 0,
        };
        $net = $subtotal - $discount;
        $service = self::pct($net, max(0, (int) ($in['service_bp'] ?? 0)));
        $vatOnService = (bool) ($in['vat_on_service'] ?? true);
        $vatBase = $net + ($vatOnService ? $service : 0);
        $vat = self::pct($vatBase, max(0, (int) ($in['vat_bp'] ?? 0)));
        $preTip = $net + $service + $vat;
        $tip = max(0, (int) ($in['tip'] ?? 0));
        $grand = $preTip + $tip;

        $totals = compact('subtotal', 'discount', 'net', 'service', 'vat', 'tip') + ['vat_base' => $vatBase, 'pre_tip' => $preTip, 'grand_total' => $grand];

        $result = [
            'currency' => 'NPR',
            'split_mode' => $splitMode,
            'tip_allocation' => $tipMode,
            'vat_on_service' => $vatOnService,
            'order' => $vatOnService
                ? 'Items → discount → service charge → VAT on (subtotal − discount + service charge) → tip'
                : 'Items → discount → service charge → VAT on (subtotal − discount) only → tip',
            'totals' => $totals,
            'items' => [],
            'people' => [],
        ];

        $zero = fn () => array_fill_keys($pids, 0);
        $people0 = [];
        foreach ($people as $p) {
            $people0[$p['id']] = ['id' => $p['id'], 'name' => $p['name'], 'items' => 0, 'discount' => 0, 'service' => 0, 'vat' => 0, 'tip' => 0, 'total' => 0, 'lines' => []];
        }

        if (! $pids) {
            foreach ($items as $it) {
                $result['items'][$it['id']] = ['total' => $it['total'], 'assigned' => false, 'shares' => []];
            }
            $errors[] = 'No participants have joined yet.';
            $result['verification'] = $this->verify($totals, [], $errors, $warnings, []);

            return $result;
        }

        $ones = array_fill_keys($pids, 1);

        // ---- base (item) shares ----
        $base = $zero();
        $unassigned = 0;
        foreach ($items as $it) {
            $alloc = array_intersect_key($it['alloc'] ?? [], $ones);
            $alloc = array_filter($alloc, fn ($w) => $w > 0);
            $assigned = $splitMode !== 'itemized' || (bool) $alloc;
            $weights = ($splitMode === 'itemized' && $alloc) ? $this->ordered($alloc, $pids) : $ones;
            if ($splitMode === 'itemized' && ! $alloc && $it['total'] > 0) {
                $unassigned++;
            }
            if ($splitMode === 'itemized' && ! ($it['shared'] ?? false) && count($alloc) > 1) {
                $warnings[] = '"'.$it['name'].'" is not marked shared but has '.count($alloc).' people assigned.';
            }
            $shares = $splitMode === 'itemized' ? self::largestRemainder($it['total'], $weights) : array_fill_keys($pids, 0);
            foreach ($shares as $pid => $s) {
                $base[$pid] += $s;
                if ($s > 0) {
                    $people0[$pid]['lines'][] = ['item_id' => $it['id'], 'name' => $it['name'], 'amount' => $s, 'weight' => $weights[$pid] ?? 0, 'of' => array_sum($weights)];
                }
            }
            $result['items'][$it['id']] = ['total' => $it['total'], 'assigned' => $assigned, 'shares' => array_filter($shares)];
        }
        if ($splitMode !== 'itemized') {
            $base = self::largestRemainder($subtotal, $ones);
        }
        if ($unassigned) {
            $warnings[] = $unassigned.' item'.($unassigned > 1 ? 's are' : ' is').' not assigned to anyone yet — split equally until assigned.';
        }

        // ---- custom amounts ----
        if ($splitMode === 'custom') {
            $sum = 0;
            $missing = 0;
            foreach ($people as $p) {
                $amt = $p['custom'];
                if ($amt === null) {
                    $missing++;
                    $amt = 0;
                }
                $people0[$p['id']]['items'] = $base[$p['id']];
                $people0[$p['id']]['total'] = max(0, (int) $amt);
                $sum += max(0, (int) $amt);
            }
            if ($missing) {
                $warnings[] = $missing.' participant'.($missing > 1 ? 's have' : ' has').' no custom amount yet (counted as 0).';
            }
            $result['people'] = $people0;
            $diff = $grand - $sum;
            $checks = [[
                'label' => 'Custom amounts add up to the bill total',
                'ok' => $diff === 0,
                'detail' => $diff === 0 ? 'Exact match' : ($diff > 0 ? self::money($diff).' still unassigned' : self::money(-$diff).' over the total'),
            ]];
            if ($diff !== 0) {
                $errors[] = 'Custom amounts must add up to exactly NPR '.self::money($grand).' (difference NPR '.self::money(abs($diff)).').';
            }
            $result['verification'] = $this->verify($totals, $people0, $errors, $warnings, $checks);

            return $result;
        }

        // ---- cumulative allocation of discount, service, VAT (+ tip when it uses the same weights) ----
        $w = $splitMode === 'equal' ? $ones : (array_sum($base) > 0 ? $base : $ones);
        $tipInChain = $splitMode === 'equal' ? $tipMode !== 'custom' : $tipMode === 'proportional';
        $a1 = self::largestRemainder($net, $w);
        $a2 = self::largestRemainder($net + $service, $w);
        $a3 = self::largestRemainder($preTip, $w);
        $a4 = $tipInChain ? self::largestRemainder($grand, $w) : null;

        $tipShares = $zero();
        if (! $tipInChain) {
            if ($tipMode === 'custom') {
                $sumTip = 0;
                foreach ($people as $p) {
                    $tipShares[$p['id']] = max(0, (int) ($p['custom_tip'] ?? 0));
                    $sumTip += $tipShares[$p['id']];
                }
                if ($sumTip !== $tip) {
                    $errors[] = 'Custom tip shares must add up to exactly NPR '.self::money($tip).' (currently NPR '.self::money($sumTip).').';
                }
            } else {
                $tipShares = self::largestRemainder($tip, $ones);
            }
        }

        foreach ($pids as $pid) {
            $row = &$people0[$pid];
            $row['items'] = $base[$pid];
            $row['discount'] = $base[$pid] - $a1[$pid];
            $row['service'] = $a2[$pid] - $a1[$pid];
            $row['vat'] = $a3[$pid] - $a2[$pid];
            $row['tip'] = $tipInChain ? $a4[$pid] - $a3[$pid] : $tipShares[$pid];
            $row['total'] = $a3[$pid] + $row['tip'];
            unset($row);
        }
        $result['people'] = $people0;

        $col = fn ($k) => array_sum(array_column($people0, $k));
        $checks = [
            ['label' => 'Item shares add up to the subtotal', 'ok' => $col('items') === $subtotal, 'detail' => self::money($col('items')).' of '.self::money($subtotal)],
            ['label' => 'Discount fully allocated', 'ok' => $col('discount') === $discount, 'detail' => self::money($col('discount')).' of '.self::money($discount)],
            ['label' => 'Service charge fully allocated', 'ok' => $col('service') === $service, 'detail' => self::money($col('service')).' of '.self::money($service)],
            ['label' => 'VAT fully allocated', 'ok' => $col('vat') === $vat, 'detail' => self::money($col('vat')).' of '.self::money($vat)],
            ['label' => 'Tip fully allocated', 'ok' => $col('tip') === $tip, 'detail' => self::money($col('tip')).' of '.self::money($tip)],
        ];
        $result['verification'] = $this->verify($totals, $people0, $errors, $warnings, $checks);

        return $result;
    }

    private function verify(array $totals, array $people, array $errors, array $warnings, array $checks): array
    {
        $sum = array_sum(array_column($people, 'total'));
        $diff = $totals['grand_total'] - $sum;
        array_unshift($checks, [
            'label' => 'Everyone\'s shares add up to the bill total',
            'ok' => $people && $diff === 0,
            'detail' => self::money($sum).' of '.self::money($totals['grand_total']),
        ]);
        $ok = ! $errors && ! in_array(false, array_column($checks, 'ok'), true);

        return ['ok' => $ok, 'sum' => $sum, 'total' => $totals['grand_total'], 'difference' => $diff, 'checks' => $checks, 'errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
    }

    /** Keeps the participant order stable (it is the tie-breaker for remainders). */
    private function ordered(array $weights, array $pids): array
    {
        $out = [];
        foreach ($pids as $pid) {
            if (isset($weights[$pid])) {
                $out[$pid] = (int) $weights[$pid];
            }
        }

        return $out;
    }

    // ---------------- Arithmetic helpers (integer / bcmath only) ----------------

    /**
     * Largest-remainder (Hamilton) apportionment: splits $total (paisa, ≥ 0) across keys in proportion to their weights.
     * The result always sums to exactly $total. Ties go to the earlier key. Uses bcmath so total × weight cannot overflow.
     *
     * @param  array<int|string,int>  $weights
     * @return array<int|string,int>
     */
    public static function largestRemainder(int $total, array $weights): array
    {
        if ($total < 0) {
            throw new InvalidArgumentException('Total must be non-negative.');
        }
        if (! $weights) {
            return [];
        }
        $W = array_sum(array_map(fn ($w) => max(0, (int) $w), $weights));
        if ($W === 0) {
            $weights = array_map(fn () => 1, $weights);
            $W = count($weights);
        }
        $W = (string) $W;
        $out = [];
        $rem = [];
        $order = 0;
        foreach ($weights as $k => $w) {
            $prod = bcmul((string) $total, (string) max(0, (int) $w), 0);
            $out[$k] = (int) bcdiv($prod, $W, 0);
            $rem[] = [$k, bcmod($prod, $W, 0), $order++];
        }
        $left = $total - array_sum($out);
        usort($rem, fn ($a, $b) => bccomp($b[1], $a[1], 0) ?: $a[2] <=> $b[2]);
        for ($i = 0; $left > 0; $i = ($i + 1) % count($rem), $left--) {
            $out[$rem[$i][0]]++;
        }

        return $out;
    }

    /** amount × basisPoints / 10000, rounded half-up (amount ≥ 0). */
    public static function pct(int $amount, int $bp): int
    {
        return (int) bcdiv(bcadd(bcmul((string) $amount, (string) $bp, 0), '5000', 0), '10000', 0);
    }

    /** "1234.5" / 1234.5 / "1,234.50" → 123450 paisa without floats. */
    public static function toPaisa($value): int
    {
        $s = str_replace([',', ' '], '', trim((string) ($value ?? '0')));
        if ($s === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return 0;
        }

        return (int) bcmul(bcadd($s, '0', 2), '100', 0);
    }

    /** "13.00" → 1300 basis points. */
    public static function toBasisPoints($value): int
    {
        return self::toPaisa($value);
    }

    public static function toDecimal(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $p = abs($paisa);

        return $sign.intdiv($p, 100).'.'.str_pad((string) ($p % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 240000 → "2,400.00" */
    public static function money(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $p = abs($paisa);

        return $sign.number_format(intdiv($p, 100)).'.'.str_pad((string) ($p % 100), 2, '0', STR_PAD_LEFT);
    }
}
