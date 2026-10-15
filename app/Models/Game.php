<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Registry row for a server-validated game (e.g. the multiplayer Quick Brain Battle). */
class Game extends Model
{
    public const QUICK_BRAIN_BATTLE = 'quick-brain-battle';

    protected $fillable = ['key', 'title', 'description', 'mode', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function competitions()
    {
        return $this->hasMany(Competition::class);
    }

    public static function quickBrainBattle(): self
    {
        return static::firstOrCreate(['key' => self::QUICK_BRAIN_BATTLE], [
            'title' => 'Quick Brain Battle',
            'description' => 'Live multiple-choice battle for 2–4 people waiting in the same queue.',
            'mode' => 'multiplayer',
            'is_active' => true,
        ]);
    }
}
