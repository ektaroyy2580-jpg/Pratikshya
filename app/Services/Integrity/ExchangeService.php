<?php

namespace App\Services\Integrity;

use App\Models\AuditLog;
use App\Models\QueueExchangeRequest;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Integrity\ExchangeUpdate;
use App\Services\Queue\QueueException;
use App\Services\Queue\QueueManager;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Voluntary queue exchange workflow (master prompt §21).
 *
 *   request (A) → accept / decline (B only) → [staff approve / reject if the service requires it] → swap
 *
 * - Same service only, compatible counter, both entries WAITING, service must allow exchanges.
 * - Nothing is paid, traded or rewarded. Positions change only after every approval.
 * - Expiry is enforced server-side on every action (and by dalal:scan / integrity:prune).
 * - The target is chosen by token label only; the requester never sees who holds it.
 */
class ExchangeService
{
    public function __construct(private QueueManager $queues, private SignalRecorder $signals)
    {
    }

    public function request(Ticket $mine, User $user, string $targetLabel): QueueExchangeRequest
    {
        if ($mine->user_id !== $user->id) {
            $this->violation('exchange_not_owner', $mine, $user);
            throw new AuthorizationException('You can only request an exchange for your own token.');
        }
        $service = $mine->service;
        // Both the service setting and the organization-wide rule must allow exchanges.
        $orgAllows = ! class_exists(\App\Models\Organization::class)
            || \App\Models\Organization::allowsQueueExchange($service->location_id);
        if (! $service->allow_exchange || ! $orgAllows) {
            $this->violation('exchange_not_allowed', $mine, $user);
            throw new ExchangeException('Queue exchanges are not allowed for this service.');
        }
        if (! $service->is_active) {
            throw new ExchangeException('This service is not running right now.');
        }
        if ($mine->status !== 'waiting') {
            throw new ExchangeException('Only a waiting token can be exchanged.');
        }

        $number = $this->parseLabel($targetLabel, $service->prefix, $mine, $user);
        $target = Ticket::where('service_id', $service->id)->where('number', $number)->first();
        if (! $target || $target->status !== 'waiting' || ! $target->user_id || $number <= $service->current_number) {
            $this->violation('exchange_invalid_target', $mine, $user);
            throw new ExchangeException('No waiting token '.$service->token($number).' was found in this queue.');
        }
        if ($target->id === $mine->id || $target->user_id === $user->id) {
            throw new ExchangeException('That is your own token.');
        }
        if ($mine->counter_id && $target->counter_id && $mine->counter_id !== $target->counter_id) {
            $this->violation('exchange_incompatible_counter', $mine, $user);
            throw new ExchangeException('Those two tokens are served at different counters, so they cannot be exchanged.');
        }
        $busy = QueueExchangeRequest::open()->where(function ($q) use ($mine, $target) {
            $ids = [$mine->id, $target->id];
            $q->whereIn('requester_ticket_id', $ids)->orWhereIn('target_ticket_id', $ids);
        })->where('expires_at', '>', now())->exists();
        if ($busy) {
            throw new ExchangeException('One of these tokens already has an exchange in progress.');
        }
        $limit = (int) config('integrity.exchange.daily_limit');
        if (QueueExchangeRequest::where('requester_id', $user->id)->where('created_at', '>=', now()->startOfDay())->count() >= $limit) {
            $this->violation('exchange_daily_limit', $mine, $user);
            throw new ExchangeException("You can send at most {$limit} exchange requests per day.");
        }

        $this->expireStale();   // frees open_key slots of expired requests before inserting

        try {
            $x = new QueueExchangeRequest();
            $x->forceFill([
                'service_id' => $service->id,
                'requester_ticket_id' => $mine->id,
                'target_ticket_id' => $target->id,
                'requester_id' => $user->id,
                'recipient_id' => $target->user_id,
                'status' => 'pending',
                'open_key' => $mine->id,
                'requester_number_before' => $mine->number,
                'target_number_before' => $target->number,
                'expires_at' => now()->addMinutes((int) config('integrity.exchange.expiry_minutes')),
            ])->save();
        } catch (QueryException) {
            throw new ExchangeException('You already have an exchange request in progress for this token.');
        }

        AuditLog::record('queue_exchange_requested', $x, ['from' => $mine->token(), 'to' => $target->token()]);
        $this->signals->record('exchange_requested', $mine, $user, ['exchange_id' => $x->id]);
        $minutes = (int) config('integrity.exchange.expiry_minutes');
        $target->user->notify(new ExchangeUpdate($x, 'request',
            "Token {$mine->token()} asks to exchange places with your token {$target->token()}. Reply within {$minutes} minutes on the Exchange page.",
            $target->id));

        return $x;
    }

    public function accept(QueueExchangeRequest $x, User $user): QueueExchangeRequest
    {
        $this->authorizeOrSignal('respond', $x, $user);
        $this->assertStatus($x, ['pending']);

        $x->forceFill(['responded_at' => now()]);
        if ($x->service->requires_exchange_approval) {
            $x->forceFill([
                'status' => 'awaiting_staff',
                'expires_at' => now()->addMinutes((int) config('integrity.exchange.approval_minutes')),
            ])->save();
            AuditLog::record('queue_exchange_accepted', $x, ['awaiting_staff' => true]);
            $x->requester->notify(new ExchangeUpdate($x, 'accepted',
                "Your exchange with {$x->service->token($x->target_number_before)} was accepted and is waiting for staff approval. Your position has not changed yet.",
                $x->requester_ticket_id));

            return $x;
        }

        AuditLog::record('queue_exchange_accepted', $x);

        return $this->complete($x, $user);
    }

    public function decline(QueueExchangeRequest $x, User $user): QueueExchangeRequest
    {
        $this->authorizeOrSignal('respond', $x, $user);
        $this->assertStatus($x, ['pending']);
        $this->close($x, 'declined', ['responded_at' => now()]);
        AuditLog::record('queue_exchange_declined', $x);
        $this->signals->record('exchange_declined', $x->targetTicket, $user, ['exchange_id' => $x->id], evaluate: false);
        $x->requester->notify(new ExchangeUpdate($x, 'declined',
            "Your exchange request with {$x->service->token($x->target_number_before)} was declined. Your token is unchanged.", $x->requester_ticket_id));

        return $x;
    }

    public function cancel(QueueExchangeRequest $x, User $user): QueueExchangeRequest
    {
        $this->authorizeOrSignal('cancel', $x, $user);
        $this->assertStatus($x, QueueExchangeRequest::OPEN);
        $this->close($x, 'cancelled');
        AuditLog::record('queue_exchange_cancelled', $x);
        $x->recipient->notify(new ExchangeUpdate($x, 'cancelled',
            "The exchange request for your token {$x->service->token($x->target_number_before)} was withdrawn. Your token is unchanged.", $x->target_ticket_id));

        return $x;
    }

    public function approve(QueueExchangeRequest $x, User $staff, ?string $note = null): QueueExchangeRequest
    {
        $this->authorizeOrSignal('decide', $x, $staff);
        $this->assertStatus($x, ['awaiting_staff']);
        $x->forceFill(['decided_by' => $staff->id, 'decision_note' => $note]);
        AuditLog::record('queue_exchange_approved', $x, ['note' => $note]);

        return $this->complete($x, $staff);
    }

    public function reject(QueueExchangeRequest $x, User $staff, ?string $note = null): QueueExchangeRequest
    {
        $this->authorizeOrSignal('decide', $x, $staff);
        $this->assertStatus($x, ['awaiting_staff']);
        $this->close($x, 'rejected', ['decided_by' => $staff->id, 'decision_note' => $note]);
        AuditLog::record('queue_exchange_rejected', $x, ['note' => $note]);
        foreach ([[$x->requester, $x->requester_ticket_id], [$x->recipient, $x->target_ticket_id]] as [$u, $tid]) {
            $u->notify(new ExchangeUpdate($x, 'rejected', 'Staff did not approve the queue exchange. Both tokens stay as they were.', $tid));
        }

        return $x;
    }

    /** Mark open requests past their deadline as expired. Returns how many expired. */
    public function expireStale(): int
    {
        $n = 0;
        QueueExchangeRequest::open()->where('expires_at', '<=', now())->with('requester', 'service')->get()
            ->each(function (QueueExchangeRequest $x) use (&$n) {
                $this->close($x, 'expired');
                $x->requester?->notify(new ExchangeUpdate($x, 'expired',
                    "Your exchange request with {$x->service->token($x->target_number_before)} expired. Your token is unchanged.", $x->requester_ticket_id));
                $n++;
            });

        return $n;
    }

    // ----------------------------------------------------------------------

    /** Final step: swap the two numbers atomically, then audit + notify both people. */
    private function complete(QueueExchangeRequest $x, User $actor): QueueExchangeRequest
    {
        try {
            DB::transaction(function () use ($x, $actor) {
                $locked = QueueExchangeRequest::whereKey($x->id)->lockForUpdate()->firstOrFail();
                if (! $locked->isOpen()) {
                    throw new ExchangeException('This exchange is no longer open.');
                }
                $this->queues->swapPositions($x->requesterTicket, $x->targetTicket, $actor,
                    [$x->requester_number_before, $x->target_number_before], ['exchange_id' => $x->id]);
                $x->forceFill(['status' => 'completed', 'open_key' => null, 'completed_at' => now()])->save();
            });
        } catch (QueueException $e) {
            if ($x->fresh()?->isOpen()) {
                $this->close($x, 'failed', ['decision_note' => mb_substr($e->getMessage(), 0, 255)]);
            }
            throw new ExchangeException($e->getMessage());
        }

        $a = $x->requesterTicket->fresh(['service', 'counter']);
        $b = $x->targetTicket->fresh(['service', 'counter']);
        AuditLog::record('queue_exchange_completed', $a, ['exchange_id' => $x->id, 'from' => $x->service->token($x->requester_number_before), 'to' => $a->token()]);
        AuditLog::record('queue_exchange_completed', $b, ['exchange_id' => $x->id, 'from' => $x->service->token($x->target_number_before), 'to' => $b->token()]);
        $this->signals->record('exchange_completed', $a, $actor, ['exchange_id' => $x->id], evaluate: false);

        foreach ([$a, $b] as $t) {
            $ahead = $t->ahead();
            $t->user?->notify(new ExchangeUpdate($x, 'completed',
                "Exchange complete. Your token is now {$t->token()} with {$ahead} ".($ahead === 1 ? 'person' : 'people')." ahead at Counter {$t->counterNumber()}.", $t->id));
        }

        return $x;
    }

    private function close(QueueExchangeRequest $x, string $status, array $extra = []): void
    {
        $x->forceFill(['status' => $status, 'open_key' => null] + $extra)->save();
    }

    private function assertStatus(QueueExchangeRequest $x, array $allowed): void
    {
        $x->refresh();
        if ($x->isExpired()) {
            $this->close($x, 'expired');
            throw new ExchangeException('This exchange request has expired. Positions were not changed.');
        }
        if (! in_array($x->status, $allowed, true)) {
            throw new ExchangeException('This exchange request is '.strtolower($x->statusLabel()).' and can no longer be changed.');
        }
    }

    private function authorizeOrSignal(string $ability, QueueExchangeRequest $x, User $user): void
    {
        if (Gate::forUser($user)->denies($ability, $x)) {
            $this->violation('exchange_unauthorized_'.$ability, null, $user, $x->service_id);
            throw new AuthorizationException('You cannot act on this exchange request.');
        }
    }

    private function parseLabel(string $label, string $prefix, Ticket $mine, User $user): int
    {
        $clean = strtoupper(preg_replace('/\s+/', '', $label));
        if (! preg_match('/^(?:([A-Z]{1,2})-?)?(\d{1,6})$/', $clean, $m)) {
            throw new ExchangeException("Enter a token like {$prefix}-29.");
        }
        if ($m[1] !== '' && $m[1] !== strtoupper($prefix)) {
            $this->violation('exchange_cross_service', $mine, $user);
            throw new ExchangeException("Exchanges are only possible within the same service ({$prefix}- tokens).");
        }

        return (int) $m[2];
    }

    private function violation(string $rule, ?Ticket $ticket, User $user, ?int $serviceId = null): void
    {
        $service = $ticket?->service ?? ($serviceId ? \App\Models\Service::find($serviceId) : null);
        $this->signals->record('rule_violation', $ticket, $user, ['rule' => $rule], $service, $user);
    }
}
