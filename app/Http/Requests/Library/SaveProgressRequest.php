<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

/** Position input for the reader's auto-save (fetch JSON) and page-hide beacon (form data). */
class SaveProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'chapter_id' => ['required', 'integer', 'min:1'],
            'position' => ['required', 'numeric', 'between:0,100'],
            'page_index' => ['nullable', 'integer', 'between:0,500'],
            // Audiobook mode: the sentence being spoken (range-checked against the chapter in ReadingProgressService).
            'mode' => ['nullable', 'string', 'in:read,listen'],
            'listen_sentence_index' => ['nullable', 'required_if:mode,listen', 'integer', 'between:0,100000'],
        ];
    }
}
