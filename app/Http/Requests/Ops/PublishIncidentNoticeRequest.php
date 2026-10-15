<?php

namespace App\Http\Requests\Ops;

use App\Models\ServiceAnnouncement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishIncidentNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('incident'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'public_message' => ['required', 'string', 'max:280'],
            'severity' => ['required', Rule::in(ServiceAnnouncement::SEVERITIES)],
            'scope' => ['required', Rule::in(['affected', 'location'])],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
