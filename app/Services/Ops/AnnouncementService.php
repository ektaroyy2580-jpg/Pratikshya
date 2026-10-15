<?php

namespace App\Services\Ops;

use App\Models\AuditLog;
use App\Models\Counter;
use App\Models\OperationalIncident;
use App\Models\Service;
use App\Models\ServiceAnnouncement;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Ops\OfficeAnnouncement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Public notices: sanitising, scope permissions, publishing, deduplicated notifications, public feed. */
class AnnouncementService
{
    public const MAX_MESSAGE = 280;

    public function __construct(private IncidentService $incidents)
    {
    }

    /** Strip markup and collapse whitespace; what is left is plain public text. */
    public static function sanitize(?string $text): string
    {
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $text) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** Heuristic personal-data check: emails, Nepali mobile numbers, long digit runs, citizenship-style numbers. */
    public static function containsPersonalData(string $text): bool
    {
        $patterns = [
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',          // email
            '/(\+?977[\s\-]?)?\b9[678]\d[\s\-]?\d{3}[\s\-]?\d{4}\b/', // NP mobile
            '/\b\d{7,}\b/',                                          // long numbers (ids, phones)
            '/\b\d{2}[\-\/]\d{2}[\-\/]\d{2}[\-\/]\d{3,6}\b/',        // citizenship-style
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a notice (draft or published). Scope rules:
     *  - service/counter scoped → any operator of the location;
     *  - whole-location → admin or staff with `can_publish_location_notices`.
     */
    public function create(User $user, array $data, ?OperationalIncident $incident = null): ServiceAnnouncement
    {
        $locationId = (int) $data['location_id'];
        abort_unless(OpsAccess::canOperate($user, $locationId), 403, 'You can only publish notices for your assigned locations.');

        $serviceId = ($data['service_id'] ?? null) ?: null;
        $counterId = ($data['counter_id'] ?? null) ?: null;
        $this->incidents->assertBelongs($locationId, $serviceId, $counterId);
        if (! $serviceId && $counterId) {
            // A counter notice inherits the counter's service so it reaches the right queue.
            $serviceId = Counter::whereKey($counterId)->value('service_id');
        }
        if (! $serviceId && ! $counterId && ! OpsAccess::canPublishLocationWide($user, $locationId)) {
            abort(403, 'Location-wide notices need admin approval or the location-notice permission.');
        }

        $title = self::sanitize($data['title'] ?? '');
        $message = self::sanitize($data['public_message'] ?? '');
        $errors = [];
        if ($title === '' || mb_strlen($title) > 80) {
            $errors['title'] = 'Add a short public title (max 80 characters).';
        }
        if ($message === '' || mb_strlen($message) > self::MAX_MESSAGE) {
            $errors['public_message'] = 'The public message must be 1–'.self::MAX_MESSAGE.' characters of plain text.';
        }
        if (self::containsPersonalData($title.' '.$message)) {
            $errors['public_message'] = 'Public notices must not contain personal data (emails, phone or ID numbers).';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $publish = ! empty($data['publish']);

        $announcement = DB::transaction(function () use ($user, $data, $locationId, $serviceId, $counterId, $title, $message, $incident) {
            $a = new ServiceAnnouncement([
                'location_id' => $locationId, 'service_id' => $serviceId, 'counter_id' => $counterId,
                'title' => $title, 'public_message' => $message,
                'severity' => $data['severity'] ?? 'info',
                'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null,
            ]);
            $a->forceFill(['operational_incident_id' => $incident?->id, 'status' => 'draft']);
            $a->save();
            AuditLog::record('ops.notice.created', $a, ['incident_id' => $incident?->id, 'scope' => $a->isLocationWide() ? 'location' : 'service']);

            return $a;
        });

        return $publish ? $this->publish($announcement, $user) : $announcement;
    }

    /** Publish a public notice from an incident. Only the staff-written public text is used — never internal notes. */
    public function publishFromIncident(OperationalIncident $incident, User $user, array $data): ServiceAnnouncement
    {
        if ($incident->isConfidential()) {
            abort(403, 'Internal and security incidents can never be published.');
        }
        $a = $this->create($user, [
            'location_id' => $incident->location_id,
            'service_id' => $data['scope'] === 'location' ? null : $incident->service_id,
            'counter_id' => $data['scope'] === 'location' ? null : $incident->counter_id,
            'title' => $data['title'], 'public_message' => $data['public_message'],
            'severity' => $data['severity'] ?? 'warning', 'ends_at' => $data['ends_at'] ?? null,
            'publish' => true,
        ], $incident);
        $this->incidents->log($incident, $user, 'published', null, null, 'Public notice #'.$a->id.' published');

        return $a;
    }

    public function publish(ServiceAnnouncement $a, User $user): ServiceAnnouncement
    {
        $this->authorizeManage($a, $user);
        if ($a->status !== 'draft') {
            return $a;
        }
        $a->forceFill([
            'status' => 'published', 'published_by' => $user->id,
            'starts_at' => $a->starts_at ?? now(),
        ])->save();
        AuditLog::record('ops.notice.published', $a, ['severity' => $a->severity]);
        $this->notifyAffected($a);

        return $a;
    }

    public function expire(ServiceAnnouncement $a, User $user): ServiceAnnouncement
    {
        $this->authorizeManage($a, $user);
        if ($a->status === 'expired') {
            return $a;
        }
        $a->forceFill(['status' => 'expired', 'ends_at' => ($a->ends_at && $a->ends_at->lt(now())) ? $a->ends_at : now()])->save();
        AuditLog::record('ops.notice.expired', $a);

        return $a;
    }

    public function authorizeManage(ServiceAnnouncement $a, User $user): void
    {
        abort_unless(OpsAccess::canOperate($user, $a->location_id), 403);
        if ($a->isLocationWide()) {
            abort_unless(OpsAccess::canPublishLocationWide($user, $a->location_id), 403, 'Location-wide notices need the location-notice permission.');
        }
    }

    /**
     * Database notification for users holding active tickets in the affected service(s).
     * Deduplicated per (announcement, user) by a unique index, so re-publishing or retries never double-notify.
     * Returns the number of users newly notified.
     */
    public function notifyAffected(ServiceAnnouncement $a): int
    {
        if (! $a->isLive()) {
            return 0; // scheduled notices are shown when live; see integration notes
        }
        $serviceIds = $a->service_id ? [$a->service_id] : Service::where('location_id', $a->location_id)->pluck('id')->all();
        $userIds = Ticket::whereIn('service_id', $serviceIds)
            ->whereIn('status', Ticket::ACTIVE)
            ->whereNotNull('user_id')
            ->distinct()->pluck('user_id');

        $sent = 0;
        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $inserted = DB::table('announcement_deliveries')->insertOrIgnore([
                'service_announcement_id' => $a->id, 'user_id' => $user->id, 'created_at' => now(),
            ]);
            if ($inserted) {
                $user->notify(new OfficeAnnouncement($a));
                $sent++;
            }
        }

        return $sent;
    }

    /** Live, public-safe notices for a service (its own + location-wide), most severe first. */
    public function publicFor(Service $service): Collection
    {
        return ServiceAnnouncement::live()->forService($service)->publicSafe()
            ->with('counter')
            ->get()
            ->sortBy(fn ($a) => [array_search($a->severity, ['critical', 'warning', 'info'], true), -$a->id])
            ->values();
    }

    /** A suggested PUBLIC text for an incident. Built only from category + counters, never from internal notes. */
    public function suggestionFor(OperationalIncident $incident): array
    {
        $n = $incident->counter?->number;
        $alt = $incident->counter
            ? Counter::where('location_id', $incident->location_id)->where('is_open', true)->whereKeyNot($incident->counter_id)->orderBy('number')->value('number')
            : null;
        $at = $n ? " at Counter {$n}" : '';

        $r = match ($incident->category) {
            'counter_closed' => ['Counter '.($n ?? '').' unavailable', 'Counter '.($n ?? '').' is temporarily unavailable.'.($alt ? " Please proceed to Counter {$alt}." : ' Please follow staff directions.'), 'warning'],
            'printer' => ['Printer unavailable', "Printer{$at} unavailable — please bring photocopies of your documents.", 'warning'],
            'projector' => ['Token display not working', 'The token display is temporarily not working. Please watch this app for your token.', 'info'],
            'network' => ['System slowdown', 'Our office network is having problems. Service may be slower than usual.', 'warning'],
            'service_delay' => ['Service delayed', 'Service is running slower than usual. Thank you for your patience.', 'warning'],
            'queue_redirect' => ['Queue moved', 'This queue has moved'.($alt ? " to Counter {$alt}" : '').'. Please follow the signs.', 'warning'],
            'office_closed' => ['Office temporarily closed', 'The office is temporarily closed. Your place in the queue is kept.', 'critical'],
            'repair' => ['Repair work in progress', 'Repair work is in progress'.$at.'. Some services may be slower.', 'info'],
            default => ['Service update', 'Please note: there is a temporary service disruption. Staff will assist you.', 'info'],
        };

        return ['title' => trim(preg_replace('/\s+/', ' ', $r[0])), 'message' => trim(preg_replace('/\s+/', ' ', $r[1])), 'severity' => $r[2]];
    }
}
