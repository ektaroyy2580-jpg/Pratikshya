<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'area' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:40'],
            'icon' => ['required', 'string', 'max:40', 'regex:/^bi-[a-z0-9\-]+$/'],
            'lat' => ['required', 'numeric', 'between:26.3,30.5'],
            'lng' => ['required', 'numeric', 'between:80,88.3'],
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
        ];
    }

    public function messages(): array
    {
        return ['lat.between' => 'The pin must be inside Nepal.', 'lng.between' => 'The pin must be inside Nepal.'];
    }
}
