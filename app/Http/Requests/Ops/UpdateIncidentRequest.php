<?php

namespace App\Http\Requests\Ops;

use App\Models\OperationalIncident;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('incident'));
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(OperationalIncident::STATUSES)],
            'assigned_technician' => ['nullable', 'string', 'max:120'],
            'resolution_notes' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
