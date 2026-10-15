<?php

namespace App\Http\Requests\Library;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:80'],
            'name_ne' => ['nullable', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('book_categories', 'slug')->ignore($id)],
            'kind' => ['required', Rule::in(['genre', 'periodical'])],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^bi-[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
