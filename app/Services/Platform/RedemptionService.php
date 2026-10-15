<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\RewardCatalogueItem;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Notifications\Platform\PlatformNotice;
use App\Services\Rewards\RewardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Redeems WaitPoints for demo partner rewards. Spending goes through the shared
 * RewardService ledger (balance check + unique reference), so a double click or
 * replayed form can never charge twice. Points never affect queue position.
 */
class RedemptionService
{
    public function __construct(private RewardService $rewards)
    {
    }

    /**
     * @return array{0: ?RewardRedemption, 1: string} [redemption or null, message]
     */
    public function redeem(User $user, RewardCatalogueItem $item, string $nonce): array
    {
        $reference = 'redeem:'.$user->id.':'.$nonce;

        // Same form submitted twice: return the original redemption, charge nothing.
        if ($existing = RewardRedemption::where('reference_key', $reference)->where('user_id', $user->id)->first()) {
            return [$existing, 'This redemption was already processed.'];
        }
        if (! $item->is_active || ! $item->inStock()) {
            return [null, 'This reward is not available right now.'];
        }
        if ($this->rewards->balance($user) < $item->points_cost) {
            return [null, 'Not enough WaitPoints for this reward yet.'];
        }

        try {
            $redemption = $this->issue($user, $item, $reference);
        } catch (\RuntimeException $e) {
            return [null, 'This reward just ran out of stock.'];
        }

        if (! $redemption) {
            return [null, 'Not enough WaitPoints for this reward yet.'];
        }
        AuditLog::record('reward_redeemed', $redemption, ['item' => $item->slug, 'points' => $item->points_cost]);
        $user->notify(new PlatformNotice('reward_redeemed', "Reward ready: {$item->title}. Your one-time code is {$redemption->code}.", '/rewards', ['redemption_id' => $redemption->id]));

        return [$redemption, 'Redeemed! Show your one-time code at the partner.'];
    }

    private function issue(User $user, RewardCatalogueItem $item, string $reference): ?RewardRedemption
    {
        return DB::transaction(function () use ($user, $item, $reference) {
            $tx = $this->rewards->redeem($user, $item->points_cost, 'Redeemed: '.$item->title, $reference);
            if (! $tx) {
                return null;
            }
            if ($item->stock !== null) {
                $updated = RewardCatalogueItem::whereKey($item->id)->where('stock', '>', 0)->decrement('stock');
                if (! $updated) {
                    throw new \RuntimeException('out_of_stock');
                }
            }
            $r = new RewardRedemption;
            $r->forceFill([
                'user_id' => $user->id,
                'reward_catalogue_item_id' => $item->id,
                'reward_transaction_id' => $tx->id,
                'reference_key' => $reference,
                'code' => $this->uniqueCode(),
                'points' => $item->points_cost,
                'status' => 'issued',
                'expires_at' => now()->addDays(max(1, (int) $item->valid_days)),
            ])->save();

            return $r;
        });
    }

    private function uniqueCode(): string
    {
        do {
            $code = 'PK-'.strtoupper(Str::random(4)).'-'.strtoupper(Str::random(4));
            $code = strtr($code, ['0' => '7', 'O' => 'Q', '1' => '8', 'I' => 'J', 'L' => 'M']);
        } while (RewardRedemption::where('code', $code)->exists());

        return $code;
    }
}
