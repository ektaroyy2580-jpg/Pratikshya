<?php

namespace App\Http\Requests\Bills;

use App\Models\BillPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClaimPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'regex:/^\d{1,9}(\.\d{1,2})?$/', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(BillPayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:160'],
        ];
    }
}
