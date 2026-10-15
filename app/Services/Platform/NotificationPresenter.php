<?php

namespace App\Services\Platform;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Route;

/** Turns any module's database notification into icon + tone + safe link. */
class NotificationPresenter
{
    /** kind prefix => [icon, tone, group label] */
    private const KINDS = [
        'turn_called' => ['bi-megaphone-fill', 'green', 'Queue'],
        'turn_next' => ['bi-bell-fill', '', 'Queue'],
        'approaching' => ['bi-hourglass-split', 'violet', 'Queue'],
        'counter_changed' => ['bi-arrow-left-right', 'violet', 'Queue'],
        'turn_completed' => ['bi-check2-circle', 'green', 'Queue'],
        'turn_cancelled' => ['bi-x-circle', 'rose', 'Queue'],
        'turn_absent' => ['bi-exclamation-circle', 'amber', 'Queue'],
        'turn_' => ['bi-ticket-perforated', '', 'Queue'],
        'queue_joined' => ['bi-ticket-perforated', '', 'Queue'],
        'service_delayed' => ['bi-clock-history', 'amber', 'Service'],
        'service_paused' => ['bi-pause-circle', 'amber', 'Service'],
        'service_cancelled' => ['bi-slash-circle', 'rose', 'Service'],
        'exchange' => ['bi-arrow-left-right', 'violet', 'Exchange'],
        'food' => ['bi-cup-hot', 'amber', 'Food order'],
        'order' => ['bi-bag-check', 'amber', 'Order'],
        'announcement' => ['bi-broadcast', '', 'Announcement'],
        'incident' => ['bi-exclamation-triangle', 'rose', 'Office update'],
        'reward' => ['bi-stars', 'violet', 'Rewards'],
        'game' => ['bi-controller', 'violet', 'Games'],
        'competition' => ['bi-trophy', 'violet', 'Games'],
        'bill' => ['bi-receipt', 'green', 'Bills'],
        'ride' => ['bi-car-front', '', 'Transport'],
        'fraud' => ['bi-shield-exclamation', 'rose', 'Integrity'],
    ];

    public function present(DatabaseNotification $n): array
    {
        $data = is_array($n->data) ? $n->data : [];
        $kind = (string) ($data['kind'] ?? '');
        [$icon, $tone, $group] = ['bi-bell', '', 'General'];
        foreach (self::KINDS as $prefix => $meta) {
            if ($kind !== '' && str_starts_with($kind, $prefix)) {
                [$icon, $tone, $group] = $meta;
                break;
            }
        }

        return [
            'id' => $n->id,
            'kind' => $kind,
            'icon' => $icon,
            'tone' => $tone,
            'group' => $group,
            'message' => (string) ($data['message'] ?? 'Notification'),
            'token' => $data['token'] ?? null,
            'url' => $this->url($data),
            'read' => $n->read_at !== null,
            'when' => $n->created_at,
        ];
    }

    /** Only same-site relative URLs or known routes — never an open redirect. */
    private function url(array $data): ?string
    {
        $url = $data['url'] ?? null;
        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\')) {
            return $url;
        }
        if (! empty($data['ticket_id']) && Route::has('ticket')) {
            return route('ticket', (int) $data['ticket_id']);
        }

        return null;
    }
}
