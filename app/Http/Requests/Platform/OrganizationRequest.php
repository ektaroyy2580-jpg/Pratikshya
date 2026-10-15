<?php

namespace App\Http\Requests\Platform;

use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Authorization is done in the controller via OrganizationPolicy. */
class OrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'type' => ['required', Rule::in(Organization::TYPES)],
            'contact_email' => ['nullable', 'email', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9\s\-]{4,28}$/'],
            'website' => ['nullable', 'url:http,https', 'max:200'],
            'allow_queue_exchange' => ['sometimes', 'boolean'],
        ];
    }
}
