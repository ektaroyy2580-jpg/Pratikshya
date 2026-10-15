<?php

namespace App\Http\Requests\Explore;

use Illuminate\Foundation\Http\FormRequest;

/** Read-only query: where the user is (optional, never stored) and how much time they have. */
class RecommendationQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'location' => ['nullable', 'integer', 'exists:locations,id'],
            'ticket' => ['nullable', 'integer'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array{0:float,1:float}|null */
    public function origin(): ?array
    {
        return $this->filled('lat') && $this->filled('lng')
            ? [(float) $this->input('lat'), (float) $this->input('lng')]
            : null;
    }
}
