<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookmarkRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:280'],
        ];
    }
}
