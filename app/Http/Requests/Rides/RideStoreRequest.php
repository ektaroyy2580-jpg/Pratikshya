<?php

namespace App\Http\Requests\Rides;

use Illuminate\Validation\Rule;

class RideStoreRequest extends RideQuoteRequest
{
    public function rules(): array
    {
        $enabled = array_keys(array_filter(config('rides.providers', []), fn ($p) => filter_var($p['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)));

        return parent::rules() + [
            'provider' => ['required', 'string', Rule::in($enabled)],
            'vehicle_type' => ['required', 'string', 'max:32', 'alpha_dash'],
            'consent_share' => ['nullable', 'boolean'],
        ];
    }
}
