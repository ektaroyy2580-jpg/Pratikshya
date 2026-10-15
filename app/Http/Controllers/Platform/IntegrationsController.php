<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Ai\AiClient;

/**
 * Integration & AI provider status. Never renders secrets: API keys are shown as
 * "set / not set" only and base URLs are reduced to scheme://host:port.
 */
class IntegrationsController extends Controller
{
    public function index(AiClient $ai)
    {
        return view('platform.manage.integrations', [
            'ai' => $this->aiStatus($ai),
            'integrations' => $this->integrations(),
            'ping' => session('ai_ping'),
        ]);
    }

    public function ping(AiClient $ai)
    {
        if (! $ai->enabled()) {
            $result = ['ok' => false, 'message' => 'AI is switched off (AI_PROVIDER=none). Local fallbacks are in use — nothing was sent.', 'ms' => null];
        } else {
            $start = microtime(true);
            // Minimal, non-personal prompt.
            $reply = $ai->complete('You are a health check. Reply with the single word OK.', 'ping', 5);
            $ms = (int) round((microtime(true) - $start) * 1000);
            $result = $reply !== null
                ? ['ok' => true, 'message' => 'Provider answered.', 'ms' => $ms]
                : ['ok' => false, 'message' => 'Provider did not answer (unreachable, wrong model, or timed out). The app keeps using local fallbacks.', 'ms' => $ms];
        }
        AuditLog::record('ai_ping', null, ['provider' => config('ai.provider'), 'ok' => $result['ok']]);

        return back()->with('ai_ping', $result + ['at' => now()->format('H:i:s')]);
    }

    private function aiStatus(AiClient $ai): array
    {
        $url = (string) config('ai.base_url');
        $parts = parse_url($url) ?: [];
        $safeUrl = isset($parts['host']) ? ($parts['scheme'] ?? 'http').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '') : '—';

        return [
            'enabled' => $ai->enabled(),
            'provider' => (string) config('ai.provider'),
            'label' => $ai->providerLabel(),
            'model' => (string) config('ai.model'),
            'endpoint' => $safeUrl,
            'key_set' => filled(config('ai.api_key')),
            'timeout' => (int) config('ai.timeout'),
        ];
    }

    private function integrations(): array
    {
        $mailer = (string) config('mail.default');
        $mailReal = ! in_array($mailer, ['log', 'array', ''], true);

        return [
            ['Database notifications', 'bi-bell', 'active', 'Always on. Stored in the notifications table and shown in the notifications centre.'],
            ['Browser notifications', 'bi-window-stack', 'active', 'Local Notification API only — shown after each user grants permission. No push server.'],
            ['Email', 'bi-envelope', $mailReal ? 'active' : 'off', $mailReal ? "Mailer “{$mailer}” configured." : "MAIL_MAILER={$mailer}: messages are written to the log, not delivered. Users cannot opt in."],
            ['SMS', 'bi-phone', 'off', 'Not configured. Requires an SMS gateway contract (e.g. a Nepal telecom aggregator) and a new channel class.'],
            ['Maps', 'bi-map', 'active', 'Leaflet (bundled locally) with OpenStreetMap tiles. Pages keep working with coordinates if tiles fail to load.'],
            ['Payments', 'bi-credit-card', 'off', 'No payment gateway connected. Any payment step in the app is a labelled demo.'],
        ];
    }
}
