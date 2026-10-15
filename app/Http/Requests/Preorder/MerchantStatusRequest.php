<?php

namespace App\Http\Requests\Preorder;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MerchantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // per-restaurant check happens via FoodOrderPolicy@manage
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['accepted', 'rejected', 'preparing', 'ready', 'collected'])],
            'reason' => ['nullable', 'string', 'max:200'],
            'pickup_code' => ['nullable', 'string', 'max:10', 'required_if:status,collected'],
        ];
    }

    public function messages(): array
    {
        return ['pickup_code.required_if' => 'Enter the customer\'s pickup code to hand over the order.'];
    }
}
