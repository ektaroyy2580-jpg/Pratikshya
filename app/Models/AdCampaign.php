<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AdCampaign extends Model
{
    public const STATUSES = ['draft', 'pending_review', 'active', 'paused', 'ended', 'rejected'];

    /** Statuses in which the advertiser may edit the campaign. */
    public const EDITABLE = ['draft', 'pending_review', 'active', 'paused', 'rejected'];

    // status, is_demo, is_healthcare, approval and review fields are never mass-assigned from requests.
    protected $fillable = [
        'name', 'headline', 'body', 'cta_label', 'cta_url', 'creative_theme',
        'target_location_id', 'target_category', 'placements', 'starts_on', 'ends_on',
        'budget_total', 'daily_budget', 'cpm_rate', 'cpc_rate', 'allows_rewarded', 'healthcare_disclosure',
    ];

    protected $casts = [
        'placements' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'budget_total' => 'decimal:2',
        'daily_budget' => 'decimal:2',
        'cpm_rate' => 'decimal:2',
        'cpc_rate' => 'decimal:2',
        'allows_rewarded' => 'boolean',
        'is_demo' => 'boolean',
        'is_healthcare' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function advertiser()
    {
        return $this->belongsTo(Advertiser::class);
    }

    public function targetLocation()
    {
        return $this->belongsTo(Location::class, 'target_location_id');
    }

    public function events()
    {
        return $this->hasMany(AdEvent::class, 'campaign_id');
    }

    public function rewardClaims()
    {
        return $this->hasMany(AdRewardClaim::class, 'campaign_id');
    }

    /** Active, within its date range and from an approved advertiser (budget/frequency are checked by AdServer). */
    public function scopeLive(Builder $q): Builder
    {
        $today = now()->toDateString();

        return $q->where('status', 'active')->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)
            ->whereHas('advertiser', fn ($a) => $a->where('status', 'approved'));
    }

    public function isLive(): bool
    {
        $today = now()->toDateString();

        return $this->status === 'active' && $this->starts_on->toDateString() <= $today
            && $this->ends_on->toDateString() >= $today && $this->advertiser?->isApproved();
    }

    public function hasPlacement(string $placement): bool
    {
        return in_array($placement, $this->placements ?? [], true);
    }

    public function imageUrl(): ?string
    {
        return $this->creative_image ? Storage::disk(config('ads.creative.disk'))->url($this->creative_image) : null;
    }

    public function themeColors(): array
    {
        return config('ads.themes.'.$this->creative_theme) ?? config('ads.themes.blue');
    }

    public function categoryKey(): string
    {
        return $this->target_category ?? $this->advertiser?->category ?? 'other';
    }

    public function categoryIcon(): string
    {
        return match ($this->categoryKey()) {
            'cafe' => 'bi-cup-hot', 'restaurant' => 'bi-egg-fried', 'pharmacy' => 'bi-capsule',
            'hospital' => 'bi-hospital', 'bookstore' => 'bi-book', 'shopping' => 'bi-bag',
            'transport' => 'bi-bus-front', default => 'bi-shop',
        };
    }

    public function statusLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function statusTone(): string
    {
        return match ($this->status) {
            'active' => 'called', 'pending_review' => 'waiting', 'paused', 'draft' => 'serving', default => 'done',
        };
    }

    /** Destination must be an internal path (/...) or an https URL. Anything else is unsafe. */
    public static function safeDestination(?string $url): bool
    {
        if (! is_string($url) || $url === '' || strlen($url) > 255 || preg_match('/[\s\\\\\x00-\x1F]/', $url)) {
            return false;
        }
        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }
        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }
        $parts = parse_url($url);

        return $parts !== false && strtolower($parts['scheme'] ?? '') === 'https' && ! empty($parts['host'])
            && ! isset($parts['user']) && ! isset($parts['pass']);
    }
}
