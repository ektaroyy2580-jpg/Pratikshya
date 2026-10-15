<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['actor_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip'];

    protected $casts = ['meta' => 'array', 'created_at' => 'datetime'];

    public static function record(string $action, ?Model $subject = null, array $meta = []): self
    {
        return static::create([
            'actor_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
