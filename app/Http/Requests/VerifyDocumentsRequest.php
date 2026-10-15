<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'result' => ['required', 'in:verified,incomplete,rejected'],
            'missing' => ['nullable', 'array', 'max:20'],
            'missing.*' => ['string', 'max:120'],
            'reason' => ['nullable', 'required_if:result,rejected', 'string', 'max:255'],
        ];
    }
}
