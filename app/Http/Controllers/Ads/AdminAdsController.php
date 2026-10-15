<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Advertiser;
use App\Models\AuditLog;
use App\Services\Ads\AdReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Platform admin: approve advertisers and campaigns, placement overview, reports. Route group is role:admin. */
class AdminAdsController extends Controller
{
    public function __construct(private AdReports $reports) {}

    public function index(Request $request): View
    {
        $campaigns = AdCampaign::with('advertiser', 'targetLocation')->orderByRaw("case status when 'pending_review' then 0 when 'active' then 1 when 'paused' then 2 else 3 end")
            ->latest('updated_at')->get();
        $stats = $this->reports->forCampaigns($campaigns);

        return view('ads.admin.index', [
            'campaigns' => $campaigns,
            'pending' => $campaigns->where('status', 'pending_review'),
            'advertisers' => Advertiser::withCount('campaigns')->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 else 2 end")->orderBy('name')->get(),
            'stats' => $stats,
            'totals' => $this->reports->totals($stats),
            'placements' => $this->reports->placements(),
            'tab' => in_array($request->query('tab'), ['review', 'campaigns', 'advertisers', 'placements'], true) ? $request->query('tab') : 'review',
        ]);
    }

    public function advertiserStatus(Request $request, Advertiser $advertiser): RedirectResponse
    {
        $this->authorize('review', $advertiser);
        $data = $request->validate(['status' => ['required', Rule::in(Advertiser::STATUSES)]]);
        $advertiser->forceFill(['status' => $data['status'], 'reviewed_at' => now(), 'reviewed_by' => $request->user()->id])->save();
        AuditLog::record('ads.advertiser_'.$data['status'], $advertiser);

        return redirect()->route('ads.admin', ['tab' => 'advertisers'])->with('ok', $advertiser->name.' is now '.$data['status'].'.');
    }

    public function campaign(AdCampaign $campaign): View
    {
        $this->authorize('review', $campaign);
        $campaign->load('advertiser', 'targetLocation');

        return view('ads.admin.campaign', [
            'campaign' => $campaign,
            'stats' => $this->reports->forCampaigns(collect([$campaign]))->get($campaign->id),
            'daily' => $this->reports->daily($campaign),
        ]);
    }

    public function review(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $this->authorize('review', $campaign);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject', 'pause', 'end'])],
            'note' => ['nullable', 'string', 'max:255', 'required_if:decision,reject'],
        ], ['note.required_if' => 'Give the advertiser a reason for the rejection.']);

        $allowed = [
            'approve' => ['pending_review', 'paused'],
            'reject' => ['pending_review'],
            'pause' => ['active'],
            'end' => ['active', 'paused', 'pending_review'],
        ][$data['decision']];
        if (! in_array($campaign->status, $allowed, true)) {
            return back()->withErrors(['campaign' => 'That action is not available for a '.$campaign->statusLabel().' campaign.']);
        }
        if ($data['decision'] === 'approve' && ! $campaign->advertiser->isApproved()) {
            return back()->withErrors(['campaign' => 'Approve the advertiser before approving its campaigns.']);
        }
        if ($data['decision'] === 'approve' && $campaign->is_healthcare && trim((string) $campaign->healthcare_disclosure) === '') {
            return back()->withErrors(['campaign' => 'Healthcare campaigns need a disclosure before approval.']);
        }

        $status = ['approve' => 'active', 'reject' => 'rejected', 'pause' => 'paused', 'end' => 'ended'][$data['decision']];
        $campaign->forceFill(array_filter([
            'status' => $status,
            'review_note' => $data['note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'approved_at' => $data['decision'] === 'approve' ? now() : null,
        ], fn ($v, $k) => $v !== null || $k === 'review_note', ARRAY_FILTER_USE_BOTH))->save();
        AuditLog::record('ads.campaign_'.$data['decision'], $campaign, ['note' => $data['note'] ?? null]);

        return back()->with('ok', '"'.$campaign->name.'" is now '.strtolower($campaign->statusLabel()).'.');
    }
}
