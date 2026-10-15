<?php

namespace App\Services\Queue;

use App\Models\Counter;
use App\Models\QueueEvent;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\QueueUpdate;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * All queue state changes go through here, inside DB transactions.
 * Official order is decided only by token numbers — never by points,
 * ads, purchases or reading activity.
 */
class QueueManager
{
    public function __construct(private WaitTimeEstimator $estimator)
    {
    }

    /**
     * Join a queue. Idempotent: a repeated idempotency key, or an existing active
     * entry for this user + service, returns the existing ticket instead of a new one.
     */
    public function join(Service $service, User $user, array $checklist = [], ?string $idempotencyKey = null): Ticket
    {
        if (! $service->is_active) {
            $this->signal('rule_violation', null, $user, ['rule' => 'join_inactive_service'], $service);
            throw new QueueException('This service is not accepting new entries right now.');
        }
        if ($idempotencyKey && ($existing = Ticket::where('idempotency_key', $idempotencyKey)->where('user_id', $user->id)->first())) {
            $this->signal('idempotent_retry', $existing, $user);

            return $existing;
        }
        if ($existing = $this->activeTicketFor($service, $user)) {
            $this->signal('duplicate_join', $existing, $user);

            return $existing;
        }

        $checklist = collect($service->documents ?? [])
            ->mapWithKeys(fn ($d) => [$d => in_array($checklist[$d] ?? null, ['available', 'missing', 'needs_review'], true) ? $checklist[$d] : 'missing'])
            ->all();
        $allReady = collect($checklist)->every(fn ($v) => $v === 'available');
        if ($service->requires_all_documents && ! $allReady) {
            $this->signal('rule_violation', null, $user, ['rule' => 'join_missing_documents'], $service);
            throw new QueueException('This service requires every listed document before you can join.');
        }

        try {
            $ticket = DB::transaction(function () use ($service, $user, $checklist, $allReady, $idempotencyKey) {
                // Row lock on MySQL/Postgres; SQLite serialises writers inside the transaction.
                $locked = Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
                $number = $locked->last_number + 1;
                $locked->forceFill(['last_number' => $number])->save();

                $ahead = $number - $locked->current_number - 1
                    - $locked->tickets()->whereIn('status', Ticket::INACTIVE)->where('number', '>', $locked->current_number)->count();
                $estimate = $this->estimator->forPeopleAhead($locked, max(0, $ahead));

                $ticket = new Ticket(['name' => $user->name, 'docs_ready' => $allReady, 'doc_checklist' => $checklist]);
                $ticket->forceFill([
                    'service_id' => $locked->id,
                    'user_id' => $user->id,
                    'number' => $number,
                    'status' => 'waiting',
                    'counter_id' => $locked->counters()->where('is_open', true)->orderBy('number')->value('id'),
                    'initial_wait' => $estimate['minutes'],
                    'active_key' => $locked->id.':'.$user->id,
                    'idempotency_key' => $idempotencyKey,
                ])->save();

                $this->event($ticket, 'joined', null, 'waiting', $user, ['estimate' => $estimate['minutes']]);

                return $ticket;
            });
            $this->signal('joined', $ticket, $user);

            return $ticket;
        } catch (QueryException $e) {
            // A concurrent duplicate request hit the unique constraint: return the winner.
            if ($existing = $this->activeTicketFor($service, $user)) {
                $this->signal('duplicate_join', $existing, $user, ['concurrent' => true]);

                return $existing;
            }
            throw $e;
        }
    }

    public function activeTicketFor(Service $service, User $user): ?Ticket
    {
        return Ticket::where('active_key', $service->id.':'.$user->id)->first();
    }

    public function leave(Ticket $ticket, User $user): Ticket
    {
        $ticket = $this->transition($ticket, 'left', $user, fn (Ticket $t) => $t->forceFill(['left_at' => now()]));
        $this->signal('left', $ticket, $user);

        return $ticket;
    }

    /** Call the next waiting token; resolves whoever is still at the counter first. */
    public function callNext(Service $service, User $staff, ?Counter $counter = null): ?Ticket
    {
        if ($service->is_paused) {
            $this->signal('rule_violation', null, $staff, ['rule' => 'call_next_while_paused'], $service);
        }
        $called = $this->callNextInTransaction($service, $staff, $counter);
        $this->signal('staff_call_next', $called, $staff, [], $service);

        return $called;
    }

    private function callNextInTransaction(Service $service, User $staff, ?Counter $counter = null): ?Ticket
    {
        return DB::transaction(function () use ($service, $staff, $counter) {
            $s = Service::whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ($s->is_paused) {
                throw new QueueException('The queue is paused. Resume it before calling the next token.');
            }

            // serving → completed; called but never started → absent.
            if ($current = $s->tickets()->where('number', $s->current_number)->first()) {
                if ($current->status === 'serving') {
                    $this->transition($current, 'completed', $staff, fn ($t) => $t->forceFill(['completed_at' => now()]));
                } elseif ($current->status === 'called') {
                    $this->transition($current, 'absent', $staff);
                }
            }

            // Skip tokens whose real entries have dropped out.
            $next = $s->current_number;
            do {
                $next++;
                if ($next > $s->last_number) {
                    return null;
                }
                $ticket = $s->tickets()->where('number', $next)->first();
            } while ($ticket && ! $ticket->isActive());

            $s->forceFill(['current_number' => $next])->save();
            $service->setRawAttributes($s->getAttributes(), true);

            if ($ticket) {
                $this->transition($ticket, 'called', $staff, function ($t) use ($counter) {
                    $t->forceFill(['called_at' => now()] + ($counter ? ['counter_id' => $counter->id] : []));
                });
            }
            $this->notifyUpcoming($s);

            return $ticket;
        });
    }

    public function startService(Ticket $ticket, User $staff): Ticket
    {
        return $this->transition($ticket, 'serving', $staff, fn ($t) => $t->forceFill(['serving_at' => now()]));
    }

    public function complete(Ticket $ticket, User $staff): Ticket
    {
        if ($ticket->status === 'called') {
            $this->startService($ticket, $staff);
        }

        return $this->transition($ticket, 'completed', $staff, fn ($t) => $t->forceFill(['completed_at' => now()]));
    }

    public function markAbsent(Ticket $ticket, User $staff): Ticket
    {
        $ticket = $this->transition($ticket, 'absent', $staff);
        $this->signal('staff_absent', $ticket, $staff);

        return $ticket;
    }

    public function cancel(Ticket $ticket, User $actor, ?string $reason = null): Ticket
    {
        $ticket = $this->transition($ticket, 'cancelled', $actor, null, ['reason' => $reason]);
        if (in_array($actor->role, ['staff', 'admin'], true) && $ticket->user_id !== $actor->id) {
            $this->signal('staff_cancel', $ticket, $actor);
        }

        return $ticket;
    }

    /** Move an entry to another counter at the same location (audited). */
    public function moveToCounter(Ticket $ticket, Counter $counter, User $staff): Ticket
    {
        if ($counter->location_id !== $ticket->service->location_id) {
            throw new QueueException('That counter belongs to a different location.');
        }
        if (! $ticket->isActive()) {
            throw new QueueException('Only active entries can be moved.');
        }
        $from = $ticket->counter?->number;
        $ticket->forceFill(['counter_id' => $counter->id])->save();
        $ticket->load('counter');
        $this->event($ticket, 'moved_counter', $ticket->status, $ticket->status, $staff, ['from' => $from, 'to' => $counter->number]);
        $ticket->user?->notify(new QueueUpdate($ticket, 'counter_changed', "Token {$ticket->token()} moved to Counter {$counter->number}."));
        $this->signal('staff_move', $ticket, $staff, ['from' => $from, 'to' => $counter->number]);

        return $ticket;
    }

    public function transition(Ticket $ticket, string $to, ?User $actor, ?callable $mutate = null, array $meta = []): Ticket
    {
        try {
            return $this->transitionInTransaction($ticket, $to, $actor, $mutate, $meta);
        } catch (QueueException $e) {
            // Dalal AI: repeated rejected state changes by one actor are a review signal.
            $this->signal('rejected_transition', $ticket, $actor, ['from' => $ticket->status, 'to' => $to]);
            throw $e;
        }
    }

    private function transitionInTransaction(Ticket $ticket, string $to, ?User $actor, ?callable $mutate, array $meta): Ticket
    {
        return DB::transaction(function () use ($ticket, $to, $actor, $mutate, $meta) {
            $fresh = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->canTransitionTo($to)) {
                throw new QueueException("Cannot change token from {$fresh->status} to {$to}.");
            }
            $from = $fresh->status;
            $fresh->forceFill(['status' => $to]);
            if (! in_array($to, Ticket::ACTIVE, true)) {
                $fresh->forceFill(['active_key' => null]);
            }
            if ($mutate) {
                $mutate($fresh);
            }
            $fresh->save();
            $this->event($fresh, $to, $from, $to, $actor, array_filter($meta));

            $messages = [
                'called' => "Now calling {$fresh->token()} — please go to Counter {$fresh->counterNumber()}.",
                'completed' => "Service completed for {$fresh->token()}. Thank you!",
                'cancelled' => "Your token {$fresh->token()} was cancelled.",
                'absent' => "Token {$fresh->token()} was marked absent.",
            ];
            if (isset($messages[$to]) && $fresh->user && $fresh->user_id !== $actor?->id) {
                $fresh->user->notify(new QueueUpdate($fresh, "turn_{$to}", $messages[$to]));
            }
            $ticket->setRawAttributes($fresh->getAttributes(), true);

            return $fresh;
        });
    }

    /** "Approaching" / "you're next" notices, sent at most once per ticket per kind. */
    private function notifyUpcoming(Service $service): void
    {
        $service->tickets()->where('status', 'waiting')->whereNotNull('user_id')
            ->where('number', '<=', $service->current_number + 4)->with('user', 'service')->get()
            ->each(function (Ticket $t) {
                $ahead = $t->ahead();
                $kind = $ahead === 0 ? 'turn_next' : 'approaching';
                if ($ahead > 3 || $t->events()->where('type', 'notified_'.$kind)->exists()) {
                    return;
                }
                $msg = $ahead === 0
                    ? "You are next! Token {$t->token()}: head to Counter {$t->counterNumber()}."
                    : "{$ahead} people ahead of {$t->token()}. Please start heading back.";
                $t->user->notify(new QueueUpdate($t, $kind, $msg));
                $this->event($t, 'notified_'.$kind, $t->status, $t->status, null);
            });
    }

    /**
     * Voluntary queue exchange: swap the token numbers of two WAITING entries in the same
     * service. Ticket ids, owners, counters and history stay intact. Must be called inside the
     * caller's transaction (ExchangeService). Rows are locked; the (service_id, number) unique
     * index is respected by parking one entry on a free number (last_number + 1, never issued
     * while we hold the service lock) during the swap. $expected = [aNumber, bNumber] guards
     * against positions that changed since the request was made.
     *
     * @return array{0: Ticket, 1: Ticket}
     */
    public function swapPositions(Ticket $a, Ticket $b, ?User $actor, array $expected, array $meta = []): array
    {
        return DB::transaction(function () use ($a, $b, $actor, $expected, $meta) {
            $service = Service::whereKey($a->service_id)->lockForUpdate()->firstOrFail();
            $ta = Ticket::whereKey($a->id)->lockForUpdate()->firstOrFail();
            $tb = Ticket::whereKey($b->id)->lockForUpdate()->firstOrFail();

            if ($ta->service_id !== $tb->service_id || $ta->service_id !== $service->id) {
                throw new QueueException('Exchanges are only possible within the same service.');
            }
            if ($ta->status !== 'waiting' || $tb->status !== 'waiting') {
                throw new QueueException('One of the tokens is no longer waiting, so the exchange cannot go ahead.');
            }
            if ([$ta->number, $tb->number] !== array_map('intval', $expected)) {
                throw new QueueException('Queue positions changed since the request was made. Please request again.');
            }
            if ($ta->number <= $service->current_number || $tb->number <= $service->current_number) {
                throw new QueueException('One of the tokens has already been reached at the counter.');
            }

            [$na, $nb] = [$ta->number, $tb->number];
            [$wa, $wb] = [$ta->initial_wait, $tb->initial_wait];
            $parking = max($service->last_number, (int) Ticket::where('service_id', $service->id)->max('number')) + 1;

            $ta->forceFill(['number' => $parking])->save();
            $tb->forceFill(['number' => $na, 'initial_wait' => $wa])->save();
            $ta->forceFill(['number' => $nb, 'initial_wait' => $wb])->save();

            $this->event($ta, 'exchanged', 'waiting', 'waiting', $actor, $meta + ['from' => $service->token($na), 'to' => $service->token($nb)]);
            $this->event($tb, 'exchanged', 'waiting', 'waiting', $actor, $meta + ['from' => $service->token($nb), 'to' => $service->token($na)]);

            $a->setRawAttributes($ta->getAttributes(), true);
            $b->setRawAttributes($tb->getAttributes(), true);

            return [$ta, $tb];
        });
    }

    /** Dalal AI hook. Never allowed to break a queue operation. */
    private function signal(string $type, ?Ticket $ticket, ?User $actor, array $meta = [], ?Service $service = null): void
    {
        try {
            app(\App\Services\Integrity\SignalRecorder::class)->record($type, $ticket, $actor, $meta, $service);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function event(Ticket $ticket, string $type, ?string $from, ?string $to, ?User $actor, array $meta = []): void
    {
        QueueEvent::create([
            'ticket_id' => $ticket->id, 'actor_id' => $actor?->id, 'type' => $type,
            'from_status' => $from, 'to_status' => $to, 'meta' => $meta ?: null,
        ]);
    }
}
