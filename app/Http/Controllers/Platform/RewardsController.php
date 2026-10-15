<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\RewardCatalogueItem;
use App\Models\RewardRedemption;
use App\Models\RewardTransaction;
use App\Services\Platform\RedemptionService;
use App\Services\Rewards\RewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Rewards wallet. Points are a perk only — they never affect queue order. */
class RewardsController extends Controller
{
    public function index(Request $request, RewardService $rewards)
    {
        $user = $request->user();
        $ledger = RewardTransaction::where('user_id', $user->id)->latest('id')->paginate(15)->withQueryString();
        $earned = (int) RewardTransaction::where('user_id', $user->id)->where('points', '>', 0)->sum('points');
        $spent = (int) -RewardTransaction::where('user_id', $user->id)->where('points', '<', 0)->sum('points');
        $catalogue = RewardCatalogueItem::where('is_active', true)->orderBy('points_cost')->get();
        $redemptions = RewardRedemption::with('item')->where('user_id', $user->id)->latest('id')->take(10)->get();

        return view('platform.rewards', [
            'balance' => $rewards->balance($user),
            'earned' => $earned,
            'spent' => $spent,
            'ledger' => $ledger,
            'catalogue' => $catalogue,
            'redemptions' => $redemptions,
            'nonce' => (string) Str::uuid(),
            'fresh' => session('redeemed_id') ? $redemptions->firstWhere('id', session('redeemed_id')) : null,
        ]);
    }

    public function redeem(Request $request, int $item, RedemptionService $service)
    {
        $data = $request->validate(['nonce' => ['required', 'uuid']]);
        $reward = RewardCatalogueItem::whereKey($item)->where('is_active', true)->firstOrFail();
        [$redemption, $message] = $service->redeem($request->user(), $reward, $data['nonce']);

        if (! $redemption) {
            return back()->withErrors(['redeem' => $message]);
        }

        return redirect()->route('platform.rewards')->with('ok', $message)->with('redeemed_id', $redemption->id);
    }
}
