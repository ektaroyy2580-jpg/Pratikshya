<?php

namespace App\Http\Requests\Integrity;

use App\Models\FraudAlert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review', $this->route('alert')) ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(array_keys(FraudAlert::DECISIONS))],
            'note' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['note.required' => 'Please add a short review note. It is kept in the audit trail.'];
    }
}
