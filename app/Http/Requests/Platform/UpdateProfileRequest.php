<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

/** Only name and phone are editable. Role and email are never accepted here. */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9][0-9\s\-]{5,18}$/'],
        ];
    }

    public function messages(): array
    {
        return ['phone.regex' => 'Use digits only, e.g. 98XXXXXXXX or +977 98XXXXXXXX.'];
    }
}
