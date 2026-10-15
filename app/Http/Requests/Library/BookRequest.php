<?php

namespace App\Http\Requests\Library;

use App\Models\Book;
use App\Services\Library\CoverArt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('book')?->id;

        return [
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('books', 'slug')->ignore($id)],
            'author' => ['required', 'string', 'max:160'],
            'book_category_id' => ['required', 'integer', Rule::exists('book_categories', 'id')],
            'type' => ['required', Rule::in($this->route('book')?->isComic() ? ['comic'] : array_diff(Book::TYPES, ['comic']))], // comic pages are seeded content only
            'language' => ['required', Rule::in(array_keys(Book::LANGUAGES))],
            'description' => ['nullable', 'string', 'max:1000'],
            'license' => ['required', 'string', 'max:255'],
            'palette' => ['required', Rule::in(array_keys(CoverArt::PALETTES))],
            'motif' => ['required', Rule::in(CoverArt::MOTIFS)],
            'is_featured' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['license.required' => 'Every item needs a licence/source line (public domain, original, or licensed).'];
    }
}
