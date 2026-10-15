<?php

namespace App\Http\Controllers\Integrity;

use App\Http\Controllers\Controller;
use App\Http\Requests\Integrity\ReviewAlertRequest;
use App\Models\AuditLog;
use App\Models\FraudAlert;
use App\Models\IntegritySignal;
use App\Models\QueueEvent;
use App\Services\Ai\AiClient;
use App\Services\Integrity\DalalEngine;
use App\Services\Integrity\ExplanationService;
use Illuminate\Http\Request;

/** Dalal AI admin review. Internal only: never a public list, users never see alerts. */
class AlertReviewController extends Controller
{
    public const OUTCOMES = [
        'reviewing' => 'under_review',
        'dismiss' => 'false_positive',
        'confirm' => 'confirmed_issue',
        'escalate' => 'escalated',
    ];

    public function index(Request $request)
    {
        $this->authorize('viewAny', FraudAlert::class);
        $filters = $request->validate([
            'status' => ['nullable', 'in:active,'.implode(',', FraudAlert::STATUSES)],
            'severity' => ['nullable', 'in:'.implode(',', FraudAlert::SEVERITIES)],
            'rule' => ['nullable', 'in:'.implode(',', array_keys(FraudAlert::RULE_TITLES))],
        ]);

        $alerts = FraudAlert::with('subjectService.location', 'subjectUser')
            ->when($filters['status'] ?? null, fn ($q, $s) => $s === 'active' ? $q->whereIn('status', FraudAlert::OPEN_STATUSES) : $q->where('status', $s))
            ->when($filters['severity'] ?? null, fn ($q, $s) => $q->where('severity', $s))
            ->when($filters['rule'] ?? null, fn ($q, $r) => $q->where('rule', $r))
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'escalated' THEN 1 WHEN 'reviewing' THEN 2 ELSE 3 END")
            ->orderByDesc('risk_score')->latest('last_seen_at')
            ->paginate(12)->withQueryString();

        $counts = FraudAlert::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $severityOpen = FraudAlert::whereIn('status', FraudAlert::OPEN_STATUSES)->selectRaw('severity, count(*) c')->groupBy('severity')->pluck('c', 'severity');
        $signals24h = IntegritySignal::where('created_at', '>=', now()->subDay())->count();

        return view('integrity.admin.index', compact('alerts', 'filters', 'counts', 'severityOpen', 'signals24h'));
    }

    public function show(FraudAlert $alert, AiClient $ai)
    {
        $this->authorize('view', $alert);
        $alert->load('subjectUser', 'subjectService.location', 'subjectTicket.service', 'reporter', 'reviewer');

        $signals = IntegritySignal::with('actor', 'ticket.service')
            ->whereIn('id', $alert->evidence['signal_ids'] ?? [])->orderBy('created_at')->get();
        $queueEvents = $alert->subjectTicket
            ? QueueEvent::with('actor')->where('ticket_id', $alert->subject_ticket_id)->latest('id')->take(15)->get()
            : collect();
        $reviewLog = AuditLog::with('actor')->where('subject_type', $alert->getMorphClass())->where('subject_id', $alert->id)->latest('id')->get();
        $related = $alert->subject_user_id
            ? FraudAlert::where('subject_user_id', $alert->subject_user_id)->whereKeyNot($alert->id)->latest('id')->take(5)->get()
            : collect();
        $aiEnabled = $ai->enabled() && config('integrity.ai_explanations');

        return view('integrity.admin.show', compact('alert', 'signals', 'queueEvents', 'reviewLog', 'related', 'aiEnabled'));
    }

    public function review(ReviewAlertRequest $request, FraudAlert $alert)
    {
        $data = $request->validated();
        $outcome = self::OUTCOMES[$data['decision']];
        $from = $alert->status;
        $alert->forceFill([
            'status' => FraudAlert::DECISIONS[$data['decision']],
            'reviewed_by' => $request->user()->id,
            'review_outcome' => $outcome,
            'review_note' => $data['note'],
            'reviewed_at' => now(),
        ])->save();
        AuditLog::record('integrity_alert_'.$outcome, $alert, ['from' => $from, 'to' => $alert->status, 'note' => $data['note']]);

        $labels = ['under_review' => 'marked as under review', 'false_positive' => 'dismissed as a false positive',
            'confirmed_issue' => 'confirmed', 'escalated' => 'escalated'];

        return redirect()->route('integrity.admin.show', $alert)->with('ok', "Alert #{$alert->id} {$labels[$outcome]}. Outcome recorded in the audit log.");
    }

    public function explain(FraudAlert $alert, ExplanationService $explainer)
    {
        $this->authorize('review', $alert);
        [$text, $provider] = $explainer->explain($alert, true);
        $alert->forceFill(['explanation' => $text, 'explanation_provider' => $provider])->save();

        return back()->with('ok', $provider === ExplanationService::TEMPLATE_PROVIDER
            ? 'AI is unavailable, so the deterministic explanation was kept.'
            : 'Explanation reworded by '.$provider.'.');
    }

    public function scan(DalalEngine $engine)
    {
        $this->authorize('viewAny', FraudAlert::class);
        $r = $engine->scan();

        return back()->with('ok', "Scan complete: {$r['signals']} signals in 24h checked, {$r['alerts_matched']} rule matches (deduplicated), {$r['exchanges_expired']} exchanges expired.");
    }
}
