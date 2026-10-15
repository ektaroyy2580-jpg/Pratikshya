<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookCategory extends Model
{
    protected $fillable = ['slug', 'name', 'name_ne', 'kind', 'icon', 'description', 'sort', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort' => 'integer'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function books()
    {
        return $this->hasMany(Book::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
