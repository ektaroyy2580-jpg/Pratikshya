<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A recommendation the user dismissed; hidden until it expires. */
class ExploreDismissal extends Model
{
    protected $fillable = ['expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function place()
    {
        return $this->belongsTo(NearbyPlace::class, 'nearby_place_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
