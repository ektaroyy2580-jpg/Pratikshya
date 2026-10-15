<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Staff-reported operational incident. Everything on this model is INTERNAL:
 * description, resolution notes, technician and photo never reach public views.
 * Public communication happens only through ServiceAnnouncement.
 */
class OperationalIncident extends Model
{
    public const CATEGORIES = [
        'counter_closed' => ['Counter closed', 'bi-door-closed'],
        'projector' => ['Display / projector', 'bi-display'],
        'printer' => ['Printer unavailable', 'bi-printer'],
        'network' => ['Network outage', 'bi-wifi-off'],
        'service_delay' => ['Service delayed', 'bi-hourglass-bottom'],
        'queue_redirect' => ['Queue redirected', 'bi-signpost-split'],
        'office_closed' => ['Office closed', 'bi-building-x'],
        'repair' => ['Repair in progress', 'bi-tools'],
        'security' => ['Security', 'bi-shield-exclamation'],
        'other' => ['Other', 'bi-three-dots'],
    ];

    /** Categories that are always internal, regardless of the checkbox. */
    public const ALWAYS_INTERNAL = ['security'];

    public const PRIORITIES = ['low', 'medium', 'high', 'critical'];

    public const STATUSES = ['open', 'acknowledged', 'in_progress', 'resolved', 'closed'];

    public const TRANSITIONS = [
        'open' => ['acknowledged', 'in_progress', 'resolved', 'closed'],
        'acknowledged' => ['in_progress', 'resolved', 'closed'],
        'in_progress' => ['resolved', 'closed'],
        'resolved' => ['closed', 'in_progress'],
        'closed' => ['in_progress'],
    ];

    // Workflow columns (status, resolved_at, reported_by, reported_at, photo_path) are set by IncidentService only.
    protected $fillable = [
        'location_id', 'counter_id', 'service_id', 'equipment', 'category', 'description',
        'priority', 'assigned_technician', 'assigned_technician_id', 'resolution_notes', 'is_internal',
    ];

    protected $attributes = ['status' => 'open', 'priority' => 'medium', 'is_internal' => false];

    protected $casts = [
        'is_internal' => 'boolean',
        'reported_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function updates()
    {
        return $this->hasMany(IncidentUpdate::class)->latest('id');
    }

    public function announcements()
    {
        return $this->hasMany(ServiceAnnouncement::class);
    }

    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->isAdmin() ? $q : $q->whereIn('location_id', $user->staffLocationIds());
    }

    public function scopeUnresolved(Builder $q): Builder
    {
        return $q->whereIn('status', ['open', 'acknowledged', 'in_progress']);
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category][0] ?? ucfirst((string) $this->category);
    }

    public function categoryIcon(): string
    {
        return self::CATEGORIES[$this->category][1] ?? 'bi-exclamation-circle';
    }

    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function isConfidential(): bool
    {
        return (bool) $this->is_internal || in_array($this->category, self::ALWAYS_INTERNAL, true);
    }

    public function isUnresolved(): bool
    {
        return in_array($this->status, ['open', 'acknowledged', 'in_progress'], true);
    }

    public function canTransitionTo(string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function reference(): string
    {
        return 'INC-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }
}
