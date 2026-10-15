<?php

namespace App\Services\Rewards;

use App\Models\RewardTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * WaitPoints ledger. Awards are idempotent per reference key, so a retried
 * request, double click or replayed callback can never pay out twice.
 * Points NEVER influence queue position.
 */
class RewardService
{
    public const DAILY_CAP = 1000;

    /** Award points once per $referenceKey. Returns the transaction, or null if already awarded / capped. */
    public function award(User $user, int $points, string $source, string $reason, string $referenceKey, ?Model $subject = null): ?RewardTransaction
    {
        if ($points <= 0) {
            return null;
        }
        $earnedToday = (int) RewardTransaction::where('user_id', $user->id)->where('points', '>', 0)
            ->where('created_at', '>=', now()->startOfDay())->sum('points');
        $points = min($points, max(0, self::DAILY_CAP - $earnedToday));
        if ($points === 0) {
            return null;
        }

        try {
            return DB::transaction(fn () => RewardTransaction::create([
                'user_id' => $user->id, 'points' => $points, 'source' => $source, 'reason' => $reason,
                'reference_key' => $referenceKey,
                'subject_type' => $subject?->getMorphClass(), 'subject_id' => $subject?->getKey(),
            ]));
        } catch (QueryException $e) {
            return null; // unique reference_key: already awarded
        }
    }

    /** Spend points; fails (returns null) if the balance is insufficient. */
    public function redeem(User $user, int $points, string $reason, string $referenceKey): ?RewardTransaction
    {
        return DB::transaction(function () use ($user, $points, $reason, $referenceKey) {
            if ($points <= 0 || $this->balance($user) < $points) {
                return null;
            }
            try {
                return RewardTransaction::create([
                    'user_id' => $user->id, 'points' => -$points, 'source' => 'redemption', 'reason' => $reason, 'reference_key' => $referenceKey,
                ]);
            } catch (QueryException $e) {
                return null;
            }
        });
    }

    public function balance(User $user): int
    {
        return (int) RewardTransaction::where('user_id', $user->id)->sum('points');
    }
}
