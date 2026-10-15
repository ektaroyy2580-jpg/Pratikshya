<?php

namespace App\Http\Requests\Ops;

use App\Models\OperationalIncident;
use App\Services\Ops\OpsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Location scope is enforced in rules (in:) and again in IncidentService::report().
        return OpsAccess::isOperator($this->user());
    }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', Rule::in($this->user()->staffLocationIds())],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'counter_id' => ['nullable', 'integer', 'exists:counters,id'],
            'equipment' => ['nullable', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(OperationalIncident::CATEGORIES))],
            'description' => ['required', 'string', 'min:5', 'max:2000'],
            'priority' => ['required', Rule::in(OperationalIncident::PRIORITIES)],
            'assigned_technician' => ['nullable', 'string', 'max:120'],
            'is_internal' => ['nullable', 'boolean'],
            // Extension, client MIME and sniffed MIME must all agree; getimagesize() is checked again on store.
            'photo' => ['nullable', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp',
                function ($attr, $file, $fail) {
                    if ($file && ! @getimagesize($file->getRealPath())) {
                        $fail('The photo must be a real image file.');
                    }
                }],
        ];
    }

    public function messages(): array
    {
        return [
            'location_id.in' => 'You can only report incidents for your assigned locations.',
            'photo.max' => 'The photo must be 2 MB or smaller.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP image.',
            'photo.mimetypes' => 'The photo must be a JPG, PNG or WebP image.',
        ];
    }
}
