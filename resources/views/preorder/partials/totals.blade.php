{{-- Bill breakdown. Needs either $quote (paisa ints) or $order (decimal columns). --}}
@use('App\Services\Preorder\Money')
@php
    $t = isset($order)
        ? ['subtotal' => Money::toPaisa($order->subtotal), 'service_charge' => Money::toPaisa($order->service_charge), 'vat' => Money::toPaisa($order->vat), 'total' => Money::toPaisa($order->total)]
        : $quote;
@endphp
<div class="po-receipt">
    <div class="row-l"><span class="k">Subtotal</span><span>{{ Money::format($t['subtotal'], true) }}</span></div>
    <div class="row-l"><span class="k">Service charge ({{ config('preorder.service_charge_percent', 10) }}%)</span><span>{{ Money::format($t['service_charge'], true) }}</span></div>
    <div class="row-l"><span class="k">VAT ({{ config('preorder.vat_percent', 13) }}% on subtotal + service)</span><span>{{ Money::format($t['vat'], true) }}</span></div>
    <div class="row-l total"><span>Total</span><span>{{ Money::format($t['total'], true) }}</span></div>
</div>
