<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdRewardClaim;
use App\Models\User;
use App\Services\Rewards\RewardService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Voluntary rewarded ads (DEMO provider: a 15-second, clearly labelled animated sponsored "video").
 *
 * The client never decides that an ad was watched. The server records started_at when the user opts in
 * and only accepts completion when the server-side elapsed time is at least `required_seconds`, the claim
 * belongs to the user, and it has not already been completed. Rewards are limited per day and are
 * idempotent (WaitPoints use reference `adreward:{claimId}`). Rewards never affect queue position.
 */
class RewardedAdService
{
    public const PLACEMENTS = ['game_hub', 'post_game_reward'];

    public function __construct(private AdServer $server, private RewardService $rewards) {}

    public function enabled(): bool
    {
        return $this->server->enabled() && (bool) config('ads.rewarded.enabled', true);
    }

    public function requiredSeconds(): int
    {
        return max(1, (int) config('ads.rewarded.required_seconds', 15));
    }

    public function dailyLimit(): int
    {
        return max(0, (int) config('ads.rewarded.daily_limit', 3));
    }

    public function completedToday(User $user): int
    {
        return AdRewardClaim::where('user_id', $user->id)->where('status', 'completed')
            ->whereDate('claim_date', now()->toDateString())->count();
    }

    public function remainingToday(User $user): int
    {
        return max(0, $this->dailyLimit() - $this->completedToday($user));
    }

    /** A live rewarded-enabled campaign the user has not already claimed today. */
    public function campaignFor(User $user, string $placement = 'game_hub', array $context = []): ?AdCampaign
    {
        if (! $this->enabled() || ! in_array($placement, self::PLACEMENTS, true)) {
            return null;
        }
        $claimedToday = AdRewardClaim::where('user_id', $user->id)->whereDate('claim_date', now()->toDateString())
            ->where('status', '!=', 'started')->pluck('campaign_id')->all();

        return $this->server->eligible($placement, $context, $user)
            ->filter(fn (AdCampaign $c) => $c->allows_rewarded && ! in_array($c->id, $claimedToday, true))
            ->sortByDesc('relevance')->first();
    }

    /** User opted in: create (or resume) a claim with the SERVER start time. */
    public function start(User $user, string $rewardType, string $placement = 'game_hub', ?int $campaignId = null): AdRewardClaim
    {
        if (! $this->enabled()) {
            throw new AdException('Rewarded ads are not available right now.', 503);
        }
        if (! array_key_exists($rewardType, config('ads.rewarded.rewards'))) {
            throw new AdException('Choose a valid reward.');
        }
        if ($this->remainingToday($user) <= 0) {
            throw new AdException('You have reached today\'s limit for rewarded ads. Come back tomorrow.', 429);
        }

        $campaign = $campaignId
            ? $this->server->eligible($placement, [], $user)->first(fn (AdCampaign $c) => $c->id === $campaignId && $c->allows_rewarded)
            : $this->campaignFor($user, $placement);
        if (! $campaign) {
            throw new AdException('No sponsored video is available right now. Please try again later.', 404);
        }

        $today = now()->toDateString();
        $existing = AdRewardClaim::where('user_id', $user->id)->where('campaign_id', $campaign->id)->whereDate('claim_date', $today)->first();
        if ($existing) {
            if ($existing->status !== 'started' || $this->isExpired($existing)) {
                throw new AdException('You already watched this sponsor today.', 409);
            }

            // Resume the same claim: the original server start time is kept (restarting cannot shorten the wait).
            return $existing;
        }

        try {
            $claim = AdRewardClaim::create([
                'user_id' => $user->id, 'campaign_id' => $campaign->id, 'claim_date' => $today,
                'placement' => $placement, 'reward_type' => $rewardType, 'status' => 'started', 'started_at' => now(),
            ]);
        } catch (QueryException) {
            throw new AdException('You already watched this sponsor today.', 409);
        }
        $this->server->record('reward_start', $campaign, $placement, $user);

        return $claim;
    }

    public function isExpired(AdRewardClaim $claim): bool
    {
        return $claim->status === 'started'
            && $claim->started_at->lt(now()->subMinutes((int) config('ads.rewarded.expires_minutes', 10)));
    }

    public function elapsedSeconds(AdRewardClaim $claim): int
    {
        return (int) floor(max(0, now()->getTimestamp() - $claim->started_at->getTimestamp()));
    }

    /**
     * Complete a claim. $verifiedBy = 'demo_timer' enforces the server-side minimum watch time;
     * 'provider' is used only after a signed server-to-server callback from a configured ad network.
     *
     * @return array{claim: AdRewardClaim, reward: string}
     */
    public function complete(User $user, AdRewardClaim $claim, string $verifiedBy = 'demo_timer', ?string $providerTxn = null): array
    {
        if ($claim->user_id !== $user->id) {
            throw new AdException('This reward does not belong to you.', 403);
        }
        if ($claim->status === 'completed') {
            throw new AdException('This reward was already claimed.', 409);
        }
        if ($claim->status !== 'started') {
            throw new AdException('This sponsored video session has expired. Start again.', 410);
        }
        if ($this->isExpired($claim)) {
            $claim->forceFill(['status' => 'expired'])->save();
            throw new AdException('This sponsored video session has expired. Start again.', 410);
        }
        if ($verifiedBy === 'demo_timer' && $this->elapsedSeconds($claim) < $this->requiredSeconds()) {
            throw new AdException('Please watch the full sponsored video to earn the reward.', 422);
        }
        if ($this->completedToday($user) >= $this->dailyLimit()) {
            throw new AdException('You have reached today\'s limit for rewarded ads.', 429);
        }

        return DB::transaction(function () use ($user, $claim, $verifiedBy, $providerTxn) {
            // Atomic state guard: only one request can move this claim out of "started".
            $moved = AdRewardClaim::whereKey($claim->id)->where('user_id', $user->id)->where('status', 'started')
                ->update(['status' => 'completed', 'completed_at' => now(), 'verified_by' => $verifiedBy,
                    'provider_txn' => $providerTxn, 'updated_at' => now()]);
            if ($moved !== 1) {
                throw new AdException('This reward was already claimed.', 409);
            }
            $claim->refresh();

            $label = config('ads.rewarded.rewards.'.$claim->reward_type.'.label');
            if ($claim->reward_type === 'points') {
                $amount = (int) config('ads.rewarded.rewards.points.amount', 10);
                $tx = $this->rewards->award($user, $amount, 'rewarded_ad',
                    'Watched a sponsored video ('.($claim->campaign?->advertiser?->name ?? 'sponsor').')',
                    'adreward:'.$claim->id, $claim);
                $claim->forceFill(['points_awarded' => (int) ($tx?->points ?? 0)])->save();
                $label = ($tx?->points ?? 0).' WaitPoints'.($tx ? '' : ' (daily points cap reached)');
            }
            if ($claim->campaign) {
                $this->server->record('reward_complete', $claim->campaign, $claim->placement, $user);
            }

            return ['claim' => $claim, 'reward' => $label];
        });
    }

    // ---------------- Read API for other modules (games) ----------------

    /** Unused extra-round tokens earned from rewarded ads. */
    public function extraRoundsAvailable(User $user): int
    {
        return AdRewardClaim::where('user_id', $user->id)->where('status', 'completed')
            ->where('reward_type', 'extra_round')->whereNull('consumed_at')->count();
    }

    /** Consume one extra-round token (call from the games module). Returns false when none are left. */
    public function consumeExtraRound(User $user): bool
    {
        $id = AdRewardClaim::where('user_id', $user->id)->where('status', 'completed')
            ->where('reward_type', 'extra_round')->whereNull('consumed_at')->orderBy('id')->value('id');

        return $id !== null && AdRewardClaim::whereKey($id)->whereNull('consumed_at')->update(['consumed_at' => now()]) === 1;
    }

    /** Cosmetic supporter badges earned (one per sponsor). */
    public function badges(User $user): Collection
    {
        return AdRewardClaim::with('campaign.advertiser')->where('user_id', $user->id)->where('status', 'completed')
            ->where('reward_type', 'cosmetic')->latest('completed_at')->get()
            ->map(fn ($c) => ['sponsor' => $c->campaign?->advertiser?->name ?? 'Sponsor', 'earned_at' => $c->completed_at])
            ->unique('sponsor')->values();
    }
}
