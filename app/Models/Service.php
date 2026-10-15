<?php

namespace App\Models;

use App\Services\Queue\WaitTimeEstimator;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $guarded = ['id'];

    /** Mirror DB defaults so freshly created models behave the same as loaded ones. */
    protected $attributes = ['is_active' => true, 'is_paused' => false, 'allow_exchange' => true, 'requires_all_documents' => false];

    protected $casts = [
        'documents' => 'array',
        'is_active' => 'boolean',
        'is_paused' => 'boolean',
        'allow_exchange' => 'boolean',
        'requires_all_documents' => 'boolean',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function counters()
    {
        return $this->hasMany(Counter::class);
    }

    public function token(int $n): string
    {
        return $this->prefix.'-'.$n;
    }

    /** Tokens issued after the one at the counter, minus real entries that dropped out. */
    public function waitingCount(): int
    {
        $dropped = $this->tickets()
            ->whereIn('status', Ticket::INACTIVE)
            ->where('number', '>', $this->current_number)
            ->count();

        return max(0, $this->last_number - $this->current_number - $dropped);
    }

    /** Estimate for someone joining now (array with low/high/minutes/explanation). */
    public function estimate(): array
    {
        return app(WaitTimeEstimator::class)->forPeopleAhead($this, $this->waitingCount());
    }

    /** Back-compat for existing views: midpoint minutes. */
    public function estimatedWait(): int
    {
        return $this->estimate()['minutes'];
    }
}
