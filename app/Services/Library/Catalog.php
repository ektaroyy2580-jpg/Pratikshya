<?php

namespace App\Services\Library;

use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;

/** Read-side queries for browsing and search (only visible items). */
class Catalog
{
    public function visible(): Builder
    {
        return Book::query()->active()->with('category');
    }

    /** Search by title, author or category name (EN or Nepali), with optional type/language filters. */
    public function search(string $q, ?string $type = null, ?string $language = null, bool $audioOnly = false): Builder
    {
        $term = '%'.addcslashes(trim($q), '%_\\').'%';

        return $this->visible()
            ->when(trim($q) !== '', fn ($query) => $query->where(function ($w) use ($term) {
                $w->where('books.title', 'like', $term)
                    ->orWhere('books.author', 'like', $term)
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $term)->orWhere('name_ne', 'like', $term)->orWhere('slug', 'like', $term));
            }))
            ->when($type && in_array($type, Book::TYPES, true), fn ($query) => $query->where('type', $type))
            ->when($language && array_key_exists($language, Book::LANGUAGES), fn ($query) => $query->where('language', $language))
            ->when($audioOnly, fn ($query) => $query->audiobooks())
            ->orderByDesc('is_featured')->orderBy('title');
    }

    /** Most-read items (count of readers), falling back to featured. */
    public function popular(int $limit = 8)
    {
        return $this->visible()
            ->withCount('readers')
            ->orderByDesc('readers_count')->orderByDesc('is_featured')->orderBy('title')
            ->limit($limit)->get();
    }
}
