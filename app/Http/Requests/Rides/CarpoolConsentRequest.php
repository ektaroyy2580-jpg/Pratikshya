<?php

namespace App\Http\Requests\Rides;

use Illuminate\Foundation\Http\FormRequest;

class CarpoolConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'consent_share' => ['required', 'boolean'],
            // Turning sharing ON needs an explicit acknowledgement of what is shared.
            'acknowledge' => ['exclude_unless:consent_share,1,true', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return ['acknowledge.accepted' => 'Please confirm you understand what is shared before turning carpool matching on.'];
    }
}
