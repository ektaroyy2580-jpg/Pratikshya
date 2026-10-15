<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookBookmark extends Model
{
    protected $fillable = ['note'];

    protected $casts = ['position' => 'float', 'page_index' => 'integer'];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function chapter()
    {
        return $this->belongsTo(BookChapter::class, 'book_chapter_id');
    }

    public function placeLabel(): string
    {
        $ch = $this->chapter;
        if (! $ch) {
            return '';
        }

        return 'Ch. '.$ch->position.' · '.($ch->isComic() ? 'page '.(($this->page_index ?? 0) + 1) : (int) round($this->position).'%');
    }

    public function url(): string
    {
        $q = $this->chapter?->isComic() ? ['page' => ($this->page_index ?? 0) + 1] : ['at' => (int) round($this->position)];

        return route('library.read', ['book' => $this->book, 'chapter' => $this->chapter?->position] + $q);
    }
}
