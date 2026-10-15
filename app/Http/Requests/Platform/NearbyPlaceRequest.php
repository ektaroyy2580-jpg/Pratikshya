<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class NearbyPlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'category' => ['required', 'string', 'max:40'],
            'emoji' => ['required', 'string', 'max:8'],
            'tip' => ['required', 'string', 'max:200'],
            'lat' => ['required', 'numeric', 'between:26.3,30.5'],
            'lng' => ['required', 'numeric', 'between:80,88.3'],
            'walk_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'visit_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'rating' => ['required', 'numeric', 'min:0', 'max:5'],
            'is_business_listing' => ['sometimes', 'boolean'],
            'listing_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{4,28}$/'],
            'listing_hours' => ['nullable', 'string', 'max:120'],
        ];
    }
}
