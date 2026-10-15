<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'browser_push' => ['sometimes', 'boolean'],
            'email' => ['sometimes', 'boolean'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
            'personalised_ads' => ['sometimes', 'boolean'],
            'location_suggestions' => ['sometimes', 'boolean'],
        ];
    }
}
