<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JoinQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'docs' => ['nullable', 'array', 'max:20'],
            'docs.*' => ['in:available,missing,needs_review'],
            'idempotency_key' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9\-]+$/'],
            'confirm_missing' => ['nullable', 'boolean'],
        ];
    }
}
