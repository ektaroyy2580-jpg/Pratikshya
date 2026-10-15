<?php

namespace App\Http\Requests\Integrity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffReportRequest extends FormRequest
{
    public const REASONS = [
        'same_person_two_tokens' => 'Same person presenting two tokens',
        'token_shared_or_passed' => 'Token passed to someone else',
        'repeated_rejoin' => 'Keeps re-joining after being called',
        'other' => 'Other duplicate-token concern',
    ];

    public function authorize(): bool
    {
        // Staff/admin of the ticket's location only (TicketPolicy::manage).
        return $this->user()?->can('manage', $this->route('ticket')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(array_keys(self::REASONS))],
            'note' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return ['note.min' => 'Describe what you observed in at least 10 characters (facts only, no personal opinions).'];
    }
}
