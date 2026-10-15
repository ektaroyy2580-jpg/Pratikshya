<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookFavorite extends Model
{
    // Only ever filled from the authenticated user + a route-bound book, never from raw request input.
    protected $fillable = ['user_id', 'book_id'];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
