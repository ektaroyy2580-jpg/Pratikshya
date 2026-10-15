<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class ServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $docs = collect(preg_split('/\r\n|\n|\r/', (string) $this->input('documents_text', '')))
            ->map(fn ($d) => trim($d))->filter()->unique()->values()->all();
        $this->merge(['documents' => $docs, 'prefix' => strtoupper(trim((string) $this->input('prefix')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'prefix' => ['required', 'string', 'size:1', 'regex:/^[A-Z]$/'],
            'counter' => ['required', 'integer', 'min:1', 'max:99'],
            'avg_minutes' => ['required', 'integer', 'min:1', 'max:240'],
            'documents' => ['array', 'max:15'],
            'documents.*' => ['string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'is_paused' => ['sometimes', 'boolean'],
            'allow_exchange' => ['sometimes', 'boolean'],
            'requires_all_documents' => ['sometimes', 'boolean'],
        ];
    }
}
