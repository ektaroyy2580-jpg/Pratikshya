<?php

namespace App\Services\Ads;

use Illuminate\Http\Request;

/**
 * Server-to-server reward callback verification for a REAL rewarded-ad network.
 *
 * STUB: rejects everything unless ADS_PROVIDER=network AND a callback secret is configured.
 * When configured it requires:
 *   - header <signature_header> = hex HMAC-SHA256(raw request body, callback_secret)
 *   - body field `timestamp` (unix seconds) no older than `max_age_seconds` (replay protection)
 *   - body fields `claim_id`, `user_id`, `transaction_id`
 * Each network signs differently — adapt `expectedSignature()` to the network's documentation before use.
 */
class ProviderCallbackVerifier
{
    public function configured(): bool
    {
        return config('ads.provider') === 'network' && (string) config('ads.providers.network.callback_secret') !== '';
    }

    public function verify(Request $request): bool
    {
        if (! $this->configured()) {
            return false;
        }
        $given = (string) $request->header((string) config('ads.providers.network.signature_header', 'X-Ad-Signature'));
        if ($given === '' || ! hash_equals($this->expectedSignature($request->getContent()), strtolower($given))) {
            return false;
        }
        $ts = (int) $request->input('timestamp', 0);
        $maxAge = (int) config('ads.providers.network.max_age_seconds', 300);

        return $ts > 0 && abs(now()->timestamp - $ts) <= $maxAge
            && $request->filled(['claim_id', 'user_id', 'transaction_id']);
    }

    public function expectedSignature(string $rawBody): string
    {
        return hash_hmac('sha256', $rawBody, (string) config('ads.providers.network.callback_secret'));
    }
}
