<?php

namespace App\Http\Requests\Ops;

use App\Models\ServiceAnnouncement;
use App\Services\Ops\OpsAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return OpsAccess::isOperator($this->user());
    }

    public function rules(): array
    {
        return [
            'location_id' => ['required', 'integer', Rule::in($this->user()->staffLocationIds())],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'counter_id' => ['nullable', 'integer', 'exists:counters,id'],
            'title' => ['required', 'string', 'max:80'],
            'public_message' => ['required', 'string', 'max:280'],
            'severity' => ['required', Rule::in(ServiceAnnouncement::SEVERITIES)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:now', function ($attr, $value, $fail) {
                if ($value && $this->filled('starts_at') && strtotime($value) <= strtotime($this->input('starts_at'))) {
                    $fail('The end time must be after the start time.');
                }
            }],
            'publish' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['location_id.in' => 'You can only publish notices for your assigned locations.'];
    }
}
