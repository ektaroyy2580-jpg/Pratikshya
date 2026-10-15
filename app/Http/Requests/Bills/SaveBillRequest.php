<?php

namespace App\Http\Requests\Bills;

use App\Models\GroupBill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create / update a bill (settings + items). Ownership is checked in the controller. */
class SaveBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')
            ->map(fn ($r) => ['is_shared' => filter_var($r['is_shared'] ?? false, FILTER_VALIDATE_BOOLEAN)] + $r)
            ->values()->all();
        $this->merge([
            'items' => $items,
            'vat_on_service_charge' => $this->boolean('vat_on_service_charge'),
            'members_can_select' => $this->boolean('members_can_select'),
            'discount_type' => $this->input('discount_type', 'none'),
            'tip_amount' => $this->input('tip_amount') === null || $this->input('tip_amount') === '' ? '0' : $this->input('tip_amount'),
            'discount_value' => $this->input('discount_value') === null || $this->input('discount_value') === '' ? '0' : $this->input('discount_value'),
        ]);
    }

    public function rules(): array
    {
        $money = ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/'];
        $pct = ['required', 'regex:/^\d{1,3}(\.\d{1,2})?$/', 'numeric', 'min:0', 'max:100'];

        return [
            'title' => ['required', 'string', 'max:120'],
            'restaurant_name' => ['nullable', 'string', 'max:120'],
            'discount_type' => ['required', Rule::in(GroupBill::DISCOUNT_TYPES)],
            'discount_value' => array_merge($money, ['numeric', 'max:'.($this->input('discount_type') === 'percent' ? 100 : 9999999)]),
            'service_charge_percent' => $pct,
            'vat_percent' => $pct,
            'vat_on_service_charge' => ['boolean'],
            'tip_amount' => array_merge($money, ['numeric', 'max:1000000']),
            'tip_allocation' => ['required', Rule::in(GroupBill::TIP_ALLOCATIONS)],
            'split_mode' => ['required', Rule::in(GroupBill::SPLIT_MODES)],
            'members_can_select' => ['boolean'],
            'payment_note' => ['nullable', 'string', 'max:160'],
            // Café context (set only by the café page link; ownership of the order is re-checked in the controller).
            'nearby_place_id' => ['nullable', 'integer', 'exists:nearby_places,id'],
            'food_order_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:'.(int) config('bills.max_items', 80)],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.name' => ['required', 'string', 'max:80'],
            'items.*.unit_price' => array_merge($money, ['numeric', 'max:200000']),
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.is_shared' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one item.',
            'items.min' => 'Add at least one item.',
            'items.*.unit_price.regex' => 'Prices need at most 2 decimals.',
            'items.*.name.required' => 'Each item needs a name.',
        ];
    }
}
