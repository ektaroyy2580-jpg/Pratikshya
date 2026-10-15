<?php

namespace App\Http\Requests\Games;

use Illuminate\Foundation\Http\FormRequest;

class InviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('host', $this->route('competition')) ?? false;
    }

    public function rules(): array
    {
        return ['ticket_id' => ['required', 'integer', 'min:1']];
    }
}
