<?php

namespace App\Services\Preorder;

/**
 * Bill arithmetic for a preorder, in integer paisa.
 * Nepal restaurant convention: 10% service charge on the subtotal, then
 * 13% VAT on (subtotal + service charge). Each step rounds half-up to 1 paisa,
 * and total = subtotal + service + VAT exactly (no float drift).
 */
class OrderPricing
{
    /**
     * @param  array<int, array{unit_paisa:int, qty:int}>  $lines
     * @return array{subtotal:int, service_charge:int, vat:int, total:int, lines:array<int,int>}
     */
    public function quote(array $lines): array
    {
        $lineTotals = [];
        foreach ($lines as $k => $l) {
            $lineTotals[$k] = (int) $l['unit_paisa'] * (int) $l['qty'];
        }
        $subtotal = array_sum($lineTotals);
        $service = Money::percent($subtotal, (int) config('preorder.service_charge_percent', 10));
        $vat = Money::percent($subtotal + $service, (int) config('preorder.vat_percent', 13));

        return [
            'subtotal' => $subtotal,
            'service_charge' => $service,
            'vat' => $vat,
            'total' => $subtotal + $service + $vat,
            'lines' => $lineTotals,
        ];
    }
}
