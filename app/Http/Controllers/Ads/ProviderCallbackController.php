<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\AdRewardClaim;
use App\Models\AuditLog;
use App\Services\Ads\AdException;
use App\Services\Ads\ProviderCallbackVerifier;
use App\Services\Ads\RewardedAdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-to-server reward verification endpoint for a REAL ad network.
 * Not active in this build: ProviderCallbackVerifier rejects every request until a network is configured.
 */
class ProviderCallbackController extends Controller
{
    public function __invoke(Request $request, ProviderCallbackVerifier $verifier, RewardedAdService $rewarded): JsonResponse
    {
        if (! $verifier->verify($request)) {
            return response()->json(['message' => 'Invalid or unconfigured provider callback.', 'data' => null], 403);
        }
        $claim = AdRewardClaim::with('user', 'campaign.advertiser')
            ->where('id', (int) $request->input('claim_id'))->where('user_id', (int) $request->input('user_id'))->first();
        if (! $claim || ! $claim->user) {
            return response()->json(['message' => 'Unknown claim.', 'data' => null], 404);
        }
        $txn = substr((string) $request->input('transaction_id'), 0, 120);
        if ($claim->status === 'completed' && $claim->provider_txn === $txn) {
            return response()->json(['message' => 'Already processed.', 'data' => ['claim_id' => $claim->id]]);
        }
        try {
            $rewarded->complete($claim->user, $claim, 'provider', $txn);
        } catch (AdException $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => null], $e->status);
        }
        AuditLog::record('ads.provider_reward_verified', $claim, ['txn' => $txn]);

        return response()->json(['message' => 'Reward verified.', 'data' => ['claim_id' => $claim->id]]);
    }
}
