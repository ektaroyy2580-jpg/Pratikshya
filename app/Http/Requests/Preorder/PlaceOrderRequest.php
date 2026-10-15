<?php

namespace App\Http\Requests\Preorder;

use App\Models\FoodOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'payment_mode' => ['required', Rule::in(FoodOrder::PAYMENT_MODES)],
            'special_instructions' => ['nullable', 'string', 'max:500'],
            'acknowledge_conflict' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
        ];
    }

    public function messages(): array
    {
        return ['payment_mode.required' => 'Choose demo payment or cash on pickup.'];
    }
}
