<?php

namespace App\Http\Requests\Integrity;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // ownership is enforced in ExchangeService (and recorded as a Dalal AI signal when violated)
    }

    public function rules(): array
    {
        return ['target_token' => ['required', 'string', 'max:12', 'regex:/^\s*([A-Za-z]{1,2}\s*-?\s*)?\d{1,6}\s*$/']];
    }

    public function messages(): array
    {
        return ['target_token.regex' => 'Enter a token like A-29.', 'target_token.required' => 'Enter the token you would like to exchange with.'];
    }
}
