<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Services\Ads\AdServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Impression beacon + click redirect. Both only ever record REAL interactions from rendered slots. */
class AdDeliveryController extends Controller
{
    public function __construct(private AdServer $server) {}

    /** Viewability beacon: the slot posts its signed token once the card is on screen. */
    public function impression(Request $request): JsonResponse
    {
        $parsed = $this->server->parseToken($request->input('t'));
        $campaign = $parsed ? AdCampaign::with('advertiser')->find($parsed['campaign_id']) : null;
        if (! $parsed || ! $campaign || ! $campaign->isLive() || ! $campaign->hasPlacement($parsed['placement'])) {
            return response()->json(['message' => 'Ignored.', 'data' => ['counted' => false]], 422);
        }
        $counted = $this->server->recordImpression($campaign, $parsed['placement'], $request->user());

        return response()->json(['message' => $counted ? 'Recorded.' : 'Already counted.', 'data' => ['counted' => $counted]]);
    }

    /**
     * Signed click URL: logs the click, then redirects to the campaign's stored destination.
     * The destination is never taken from the request, and is re-checked (internal path or https only).
     */
    public function click(Request $request, AdCampaign $campaign, string $placement): RedirectResponse
    {
        if (! array_key_exists($placement, config('ads.placements'))) {
            abort(404);
        }
        $campaign->loadMissing('advertiser');
        if (! $campaign->isLive() || ! AdCampaign::safeDestination($campaign->cta_url)) {
            return redirect()->route('home');
        }
        $this->server->recordClick($campaign, $placement, $request->user());

        return str_starts_with($campaign->cta_url, '/')
            ? redirect()->to(url($campaign->cta_url))
            : redirect()->away($campaign->cta_url);
    }
}
