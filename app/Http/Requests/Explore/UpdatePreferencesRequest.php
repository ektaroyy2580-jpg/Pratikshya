<?php

namespace App\Http\Requests\Explore;

use App\Services\Explore\PlaceCategories;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'suggestions_enabled' => ['required', 'boolean'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', Rule::in(PlaceCategories::recommendable())],
        ];
    }
}
