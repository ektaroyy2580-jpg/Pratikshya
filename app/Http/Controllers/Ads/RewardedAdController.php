<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdRewardClaim;
use App\Services\Ads\AdException;
use App\Services\Ads\RewardedAdService;
use App\Services\Rewards\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Voluntary rewarded ads (DEMO provider). Rewards are granted only after server-side verification. */
class RewardedAdController extends Controller
{
    public function __construct(private RewardedAdService $rewarded) {}

    public function show(Request $request): View
    {
        $user = $request->user();
        $placement = in_array($request->query('placement'), RewardedAdService::PLACEMENTS, true) ? $request->query('placement') : 'game_hub';
        $return = $request->query('return');
        $return = is_string($return) && AdCampaign::safeDestination($return) && str_starts_with($return, '/') ? $return : null;

        return view('ads.rewarded', [
            'campaign' => $this->rewarded->campaignFor($user, $placement),
            'placement' => $placement,
            'returnTo' => $return,
            'enabled' => $this->rewarded->enabled(),
            'remaining' => $this->rewarded->remainingToday($user),
            'limit' => $this->rewarded->dailyLimit(),
            'seconds' => $this->rewarded->requiredSeconds(),
            'extraRounds' => $this->rewarded->extraRoundsAvailable($user),
            'badges' => $this->rewarded->badges($user),
            'balance' => app(RewardService::class)->balance($user),
            'rewards' => config('ads.rewarded.rewards'),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reward_type' => ['required', Rule::in(array_keys(config('ads.rewarded.rewards')))],
            'placement' => ['nullable', Rule::in(RewardedAdService::PLACEMENTS)],
            'campaign_id' => ['nullable', 'integer'],
        ]);
        try {
            $claim = $this->rewarded->start($request->user(), $data['reward_type'], $data['placement'] ?? 'game_hub', $data['campaign_id'] ?? null);
        } catch (AdException $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => null], $e->status);
        }
        $remaining = max(0, $this->rewarded->requiredSeconds() - $this->rewarded->elapsedSeconds($claim));

        return response()->json(['message' => 'Sponsored video started.', 'data' => [
            'claim_id' => $claim->id,
            'required_seconds' => $this->rewarded->requiredSeconds(),
            'remaining_seconds' => $remaining,
            'complete_url' => route('ads.rewarded.complete', $claim->id),
        ]]);
    }

    public function complete(Request $request, int $claim): JsonResponse
    {
        // Scoped lookup: another user's claim id is indistinguishable from a missing one (no IDOR).
        $model = AdRewardClaim::with('campaign.advertiser')->where('user_id', $request->user()->id)->find($claim);
        if (! $model) {
            return response()->json(['message' => 'Reward not found.', 'data' => null], 404);
        }
        try {
            $result = $this->rewarded->complete($request->user(), $model);
        } catch (AdException $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => [
                'remaining_seconds' => max(0, $this->rewarded->requiredSeconds() - $this->rewarded->elapsedSeconds($model)),
            ]], $e->status);
        }

        return response()->json(['message' => 'Reward unlocked: '.$result['reward'].'.', 'data' => [
            'reward_type' => $result['claim']->reward_type,
            'reward' => $result['reward'],
            'remaining_today' => $this->rewarded->remainingToday($request->user()),
            'extra_rounds' => $this->rewarded->extraRoundsAvailable($request->user()),
        ]]);
    }
}
