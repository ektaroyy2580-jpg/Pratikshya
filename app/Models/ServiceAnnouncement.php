<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Public office notice. Only `title` and `public_message` (sanitised, max 280 chars)
 * are ever exposed publicly, never the linked incident's internal details.
 */
class ServiceAnnouncement extends Model
{
    public const SEVERITIES = ['info', 'warning', 'critical'];

    // status / published_by / operational_incident_id are set by AnnouncementService only.
    protected $fillable = ['location_id', 'service_id', 'counter_id', 'title', 'public_message', 'severity', 'starts_at', 'ends_at'];

    protected $attributes = ['status' => 'draft', 'severity' => 'info'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime'];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function incident()
    {
        return $this->belongsTo(OperationalIncident::class, 'operational_incident_id');
    }

    public function publisher()
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** Published and inside its time window right now. */
    public function scopeLive(Builder $q): Builder
    {
        $now = now();

        return $q->where('status', 'published')
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    /** Notices relevant to a service: its own notices plus location-wide / unassigned-counter ones. */
    public function scopeForService(Builder $q, Service $service): Builder
    {
        return $q->where('location_id', $service->location_id)
            ->where(fn ($w) => $w->where('service_id', $service->id)->orWhereNull('service_id'));
    }

    /** Never surface notices linked to confidential incidents (defence in depth). */
    public function scopePublicSafe(Builder $q): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('operational_incident_id')
            ->orWhereHas('incident', fn ($i) => $i->where('is_internal', false)->where('category', '!=', 'security')));
    }

    public function isLocationWide(): bool
    {
        return $this->service_id === null && $this->counter_id === null;
    }

    public function isLive(): bool
    {
        return $this->status === 'published'
            && (! $this->starts_at || $this->starts_at->lte(now()))
            && (! $this->ends_at || $this->ends_at->gt(now()));
    }

    /** draft | scheduled | live | expired */
    public function displayStatus(): string
    {
        if ($this->status !== 'published') {
            return $this->status;
        }
        if ($this->ends_at && $this->ends_at->lte(now())) {
            return 'expired';
        }

        return $this->starts_at && $this->starts_at->gt(now()) ? 'scheduled' : 'live';
    }

    public function scopeLabel(): string
    {
        if ($this->service_id) {
            return ($this->service?->name ?? 'Service').($this->counter_id ? ' · Counter '.$this->counter?->number : '');
        }

        return $this->counter_id ? 'Counter '.($this->counter?->number ?? '') : 'Whole location';
    }

    /** The only shape that ever leaves the server for public consumers. */
    public function publicArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'message' => $this->public_message,
            'severity' => $this->severity,
            'scope' => $this->service_id ? 'service' : ($this->counter_id ? 'counter' : 'location'),
            'counter' => $this->counter?->number,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
        ];
    }
}
