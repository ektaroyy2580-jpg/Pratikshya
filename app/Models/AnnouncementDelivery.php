<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per (announcement, user) notified. The unique index is the dedupe guarantee. */
class AnnouncementDelivery extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['service_announcement_id', 'user_id'];
}
