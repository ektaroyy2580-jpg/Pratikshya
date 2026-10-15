<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Internal review signal produced by Dalal AI. Never shown to users, never a public list,
 * never conclusive on its own: a human reviewer decides the outcome.
 */
class FraudAlert extends Model
{
    public const STATUSES = ['open', 'reviewing', 'dismissed', 'confirmed', 'escalated'];

    public const SEVERITIES = ['low', 'medium', 'high'];

    public const OPEN_STATUSES = ['open', 'reviewing', 'escalated'];

    /** Reviewer decisions and the status each one sets. */
    public const DECISIONS = [
        'reviewing' => 'reviewing',
        'dismiss' => 'dismissed',
        'confirm' => 'confirmed',
        'escalate' => 'escalated',
    ];

    public const RULE_TITLES = [
        'duplicate_token_attempts' => 'Repeated duplicate-token attempts',
        'join_leave_churn' => 'Rapid join / leave churn',
        'simultaneous_active_tokens' => 'Several active tokens at one location',
        'new_account_burst_shared_network' => 'New accounts joining together from one network',
        'repeated_exchanges' => 'Repeated queue exchange requests',
        'staff_absent_cancel_burst' => 'Burst of absent / cancel actions by staff',
        'staff_call_next_anomaly' => 'Unusual call-next rate by staff',
        'repeated_rejected_transitions' => 'Repeated rejected status changes',
        'repeated_rule_bypass_attempts' => 'Repeated attempts to bypass queue rules',
        'staff_report' => 'Staff report: duplicate-token suspicion',
    ];

    protected $fillable = [
        'type', 'rule', 'risk_score', 'severity', 'status', 'subject_user_id', 'subject_ticket_id', 'subject_service_id',
        'reported_by', 'evidence', 'explanation', 'explanation_provider', 'recommended_actions', 'fingerprint',
        'signal_count', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'recommended_actions' => 'array',
        'risk_score' => 'integer',
        'signal_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function subjectUser()
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function subjectTicket()
    {
        return $this->belongsTo(Ticket::class, 'subject_ticket_id');
    }

    public function subjectService()
    {
        return $this->belongsTo(Service::class, 'subject_service_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function title(): string
    {
        return self::RULE_TITLES[$this->rule] ?? ucfirst(str_replace('_', ' ', $this->rule));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }

    public static function severityFor(int $score): string
    {
        return $score >= 65 ? 'high' : ($score >= 35 ? 'medium' : 'low');
    }
}
