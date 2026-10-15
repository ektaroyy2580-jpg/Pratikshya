<?php

namespace App\Services\Integrity;

use App\Models\IntegritySignal;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records Dalal AI integrity signals with data minimisation:
 *  - the IP address is never stored, only HMAC-SHA256(ip, APP_KEY) truncated to 32 hex chars,
 *  - the user agent is reduced to a coarse family ("Chrome · mobile"),
 *  - meta holds counts / statuses / rule names, never free text typed by users.
 * Recording never breaks the queue: failures are logged and swallowed.
 */
class SignalRecorder
{
    public function __construct(private DalalEngine $engine)
    {
    }

    public function record(string $type, ?Ticket $ticket = null, ?User $actor = null, array $meta = [], ?Service $service = null, ?User $subject = null, bool $evaluate = true): ?IntegritySignal
    {
        try {
            $service ??= $ticket?->service;
            $subjectId = $subject?->id ?? $ticket?->user_id ?? $actor?->id;

            $signal = IntegritySignal::create([
                'type' => $type,
                'user_id' => $subjectId,
                'actor_id' => $actor?->id,
                'ticket_id' => $ticket?->id,
                'service_id' => $service?->id,
                'location_id' => $service?->location_id,
                'actor_role' => $actor?->role,
                'network_hash' => self::networkHash(request()?->ip()),
                'ua_family' => self::uaFamily(request()?->userAgent()),
                'meta' => $meta ?: null,
            ]);

            if ($evaluate) {
                $this->engine->afterSignal($signal);
            }

            return $signal;
        } catch (Throwable $e) {
            Log::warning('Integrity signal not recorded: '.$e->getMessage());

            return null;
        }
    }

    /** Salted, truncated, one-way hash of an IP. Null when there is no IP (CLI, queue workers). */
    public static function networkHash(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        return substr(hash_hmac('sha256', $ip, (string) config('app.key')), 0, 32);
    }

    /** Coarse browser family + device class. Never the full user agent string. */
    public static function uaFamily(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }
        $family = match (true) {
            (bool) preg_match('/bot|crawl|spider|curl|wget|python|httpclient|symfony/i', $ua) => 'Automated',
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Other',
        };
        $device = preg_match('/Mobile|Android|iPhone|iPad/i', $ua) ? 'mobile' : 'desktop';

        return $family === 'Automated' ? $family : $family.' · '.$device;
    }
}
