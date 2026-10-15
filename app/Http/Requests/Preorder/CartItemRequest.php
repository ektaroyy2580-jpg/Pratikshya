<?php

namespace App\Http\Requests\Preorder;

use Illuminate\Foundation\Http\FormRequest;

class CartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'qty' => ['required', 'integer', 'min:0', 'max:'.config('preorder.max_qty_per_item', 20)],
            'note' => ['nullable', 'string', 'max:200'],
            'replace' => ['nullable', 'boolean'],
        ];
    }
}
