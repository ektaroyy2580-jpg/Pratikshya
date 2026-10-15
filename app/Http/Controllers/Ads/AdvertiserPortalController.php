<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ads\AdvertiserApplicationRequest;
use App\Http\Requests\Ads\CampaignRequest;
use App\Models\AdCampaign;
use App\Models\Advertiser;
use App\Models\AdvertiserUser;
use App\Models\AuditLog;
use App\Models\Location;
use App\Services\Ads\AdReports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Advertiser self-service portal. Every lookup is scoped to the advertisers the user belongs to. */
class AdvertiserPortalController extends Controller
{
    public function __construct(private AdReports $reports) {}

    public function dashboard(Request $request): View
    {
        $this->authorize('viewAny', AdCampaign::class);
        $ids = Advertiser::idsFor($request->user());
        $advertisers = Advertiser::whereIn('id', $ids)->orderBy('name')->get();
        $campaigns = AdCampaign::with('advertiser', 'targetLocation')->whereIn('advertiser_id', $ids)->latest('updated_at')->get();
        $stats = $this->reports->forCampaigns($campaigns);

        return view('ads.portal.dashboard', [
            'advertisers' => $advertisers, 'campaigns' => $campaigns, 'stats' => $stats,
            'totals' => $this->reports->totals($stats), 'hasDemo' => $campaigns->contains('is_demo', true),
        ]);
    }

    // ---------------- Apply to advertise ----------------

    public function apply(Request $request): View
    {
        return view('ads.portal.apply', ['mine' => Advertiser::whereIn('id', Advertiser::idsFor($request->user()))->get()]);
    }

    public function storeApplication(AdvertiserApplicationRequest $request): RedirectResponse
    {
        $advertiser = DB::transaction(function () use ($request) {
            $a = new Advertiser($request->safe()->only(['name', 'category', 'contact_name', 'contact_email', 'contact_phone']));
            $a->status = 'pending';
            $a->save();
            AdvertiserUser::create(['user_id' => $request->user()->id, 'advertiser_id' => $a->id, 'role' => 'owner']);

            return $a;
        });
        AuditLog::record('ads.advertiser_applied', $advertiser);

        return redirect()->route('ads.portal')->with('ok', 'Application received. You can prepare campaigns now; an admin must approve your business before they can run.');
    }

    // ---------------- Campaigns ----------------

    public function create(Request $request): View
    {
        $this->authorize('create', AdCampaign::class);

        return view('ads.portal.form', $this->formData($request, new AdCampaign([
            'creative_theme' => 'blue', 'cta_label' => 'Learn more', 'placements' => ['explore'],
            'starts_on' => now()->toDateString(), 'ends_on' => now()->addDays(30)->toDateString(),
            'budget_total' => 5000, 'daily_budget' => 500, 'cpm_rate' => 120, 'cpc_rate' => 8,
        ])));
    }

    public function store(CampaignRequest $request): RedirectResponse
    {
        $advertiser = Advertiser::whereIn('id', Advertiser::idsFor($request->user()))->findOrFail($request->integer('advertiser_id'));
        $this->authorize('create', [AdCampaign::class, $advertiser]);

        $campaign = new AdCampaign($this->payload($request));
        $campaign->advertiser_id = $advertiser->id;
        $campaign->status = 'draft';
        $campaign->is_demo = false;
        $campaign->is_healthcare = $request->isHealthcare();
        $campaign->creative_image = $this->storeImage($request);
        $campaign->save();
        AuditLog::record('ads.campaign_created', $campaign);

        return $this->afterSave($request, $campaign, 'Campaign saved as a draft.');
    }

    public function show(Request $request, AdCampaign $campaign): View
    {
        $this->authorize('view', $campaign);
        $campaign->load('advertiser', 'targetLocation');

        return view('ads.portal.show', [
            'campaign' => $campaign,
            'stats' => $this->reports->forCampaigns(collect([$campaign]))->get($campaign->id),
            'daily' => $this->reports->daily($campaign),
            'canEdit' => $request->user()->can('update', $campaign),
        ]);
    }

    public function edit(Request $request, AdCampaign $campaign): View
    {
        $this->authorize('update', $campaign);

        return view('ads.portal.form', $this->formData($request, $campaign));
    }

    public function update(CampaignRequest $request, AdCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        $wasLive = in_array($campaign->status, ['active', 'paused'], true);

        $campaign->fill($this->payload($request));
        $campaign->is_healthcare = $request->isHealthcare();
        if ($request->boolean('remove_image') || $request->hasFile('creative_image')) {
            $this->deleteImage($campaign);
            $campaign->creative_image = $this->storeImage($request);
        }
        // Any edit takes the campaign out of rotation until it is reviewed again.
        $campaign->status = 'draft';
        $campaign->approved_at = null;
        $campaign->save();
        AuditLog::record('ads.campaign_updated', $campaign, ['was_live' => $wasLive]);

        return $this->afterSave($request, $campaign, $wasLive
            ? 'Changes saved. Edited campaigns leave rotation until they are reviewed again.'
            : 'Campaign updated.');
    }

    public function submit(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        if (! in_array($campaign->status, ['draft', 'rejected'], true)) {
            return back()->withErrors(['campaign' => 'Only drafts or rejected campaigns can be submitted.']);
        }
        if (! $campaign->advertiser->isApproved()) {
            return back()->withErrors(['campaign' => 'Your business is awaiting admin approval. You can submit campaigns once it is approved.']);
        }
        $campaign->forceFill(['status' => 'pending_review', 'submitted_at' => now(), 'review_note' => null])->save();
        AuditLog::record('ads.campaign_submitted', $campaign);

        return redirect()->route('ads.campaigns.show', $campaign)->with('ok', 'Submitted for review. An admin will check it before it runs.');
    }

    public function pause(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        if ($campaign->status !== 'active') {
            return back()->withErrors(['campaign' => 'Only active campaigns can be paused.']);
        }
        $campaign->forceFill(['status' => 'paused'])->save();
        AuditLog::record('ads.campaign_paused', $campaign);

        return back()->with('ok', 'Campaign paused.');
    }

    public function resume(Request $request, AdCampaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);
        if ($campaign->status !== 'paused' || ! $campaign->approved_at || ! $campaign->advertiser->isApproved()) {
            return back()->withErrors(['campaign' => 'This campaign cannot be resumed. Submit it for review instead.']);
        }
        $campaign->forceFill(['status' => 'active'])->save();
        AuditLog::record('ads.campaign_resumed', $campaign);

        return back()->with('ok', 'Campaign resumed.');
    }

    // ---------------- helpers ----------------

    private function payload(CampaignRequest $request): array
    {
        $data = $request->safe()->only((new AdCampaign)->getFillable());
        $data['placements'] = array_values(array_unique($request->input('placements', [])));
        if (! $request->isHealthcare()) {
            $data['healthcare_disclosure'] = null;
        }

        return $data;
    }

    private function afterSave(CampaignRequest $request, AdCampaign $campaign, string $message): RedirectResponse
    {
        if ($request->input('action') === 'submit') {
            if (! $campaign->advertiser->isApproved()) {
                return redirect()->route('ads.campaigns.show', $campaign)
                    ->with('ok', $message.' It will be submittable once your business is approved.');
            }
            $campaign->forceFill(['status' => 'pending_review', 'submitted_at' => now(), 'review_note' => null])->save();
            AuditLog::record('ads.campaign_submitted', $campaign);

            return redirect()->route('ads.campaigns.show', $campaign)->with('ok', 'Saved and submitted for review.');
        }

        return redirect()->route('ads.campaigns.show', $campaign)->with('ok', $message);
    }

    /** Stores an uploaded creative on the public disk under ads/ with a random name. */
    private function storeImage(CampaignRequest $request): ?string
    {
        $file = $request->file('creative_image');
        if (! $file) {
            return null;
        }
        $ext = match ($file->getMimeType()) { 'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg' };

        return $file->storeAs(config('ads.creative.directory'), bin2hex(random_bytes(16)).'.'.$ext, config('ads.creative.disk')) ?: null;
    }

    private function deleteImage(AdCampaign $campaign): void
    {
        if ($campaign->creative_image && str_starts_with($campaign->creative_image, config('ads.creative.directory').'/')) {
            Storage::disk(config('ads.creative.disk'))->delete($campaign->creative_image);
        }
    }

    private function formData(Request $request, AdCampaign $campaign): array
    {
        $advertisers = Advertiser::whereIn('id', Advertiser::idsFor($request->user()))->where('status', '!=', 'suspended')
            ->whereIn('id', AdvertiserUser::where('user_id', $request->user()->id)->whereIn('role', ['owner', 'manager'])->pluck('advertiser_id'))
            ->orderBy('name')->get();

        return [
            'campaign' => $campaign,
            'advertisers' => $advertisers,
            'locations' => Location::orderBy('name')->get(['id', 'name', 'area']),
            'placements' => config('ads.placements'),
            'categories' => config('ads.categories'),
            'themes' => config('ads.themes'),
            'healthcareCategories' => config('ads.healthcare_categories'),
        ];
    }
}
