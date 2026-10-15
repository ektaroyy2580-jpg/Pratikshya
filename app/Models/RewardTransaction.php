<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardTransaction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'points', 'source', 'reason', 'reference_key', 'subject_type', 'subject_id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
