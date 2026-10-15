<?php

namespace App\Http\Requests\Ads;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A logged-in user applies to advertise. The advertiser starts as "pending" until an admin approves it. */
class AdvertiserApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120', 'unique:advertisers,name'],
            'category' => ['required', Rule::in(array_keys(config('ads.categories')))],
            'contact_name' => ['required', 'string', 'max:120'],
            'contact_email' => ['required', 'email', 'max:160'],
            'contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'accept_policy' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return ['accept_policy.accepted' => 'Please accept the advertising policy.', 'name.unique' => 'A business with this name is already registered.'];
    }
}
