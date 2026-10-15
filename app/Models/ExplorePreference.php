<?php

namespace App\Models;

use App\Services\Explore\PlaceCategories;
use Illuminate\Database\Eloquent\Model;

/** One row per user: location-based suggestions on/off + preferred categories. */
class ExplorePreference extends Model
{
    protected $fillable = ['suggestions_enabled', 'categories'];

    protected $attributes = ['suggestions_enabled' => true];

    protected $casts = ['suggestions_enabled' => 'boolean', 'categories' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Never creates a row just by reading. */
    public static function for(User $user): self
    {
        return static::where('user_id', $user->id)->first()
            ?? tap(new static(['suggestions_enabled' => true, 'categories' => null]), fn ($p) => $p->user_id = $user->id);
    }

    /** Category keys the user wants recommended (defaults to every recommendable category). */
    public function preferredCategories(): array
    {
        $all = PlaceCategories::recommendable();
        $chosen = array_values(array_intersect($all, (array) ($this->categories ?? [])));

        return $this->categories === null ? $all : $chosen;
    }
}
