<?php

namespace App\Services\Games;

use App\Models\AuditLog;
use App\Models\Competition;
use App\Models\CompetitionAnswer;
use App\Models\CompetitionInvitation;
use App\Models\CompetitionPlayer;
use App\Models\CompetitionQuestion;
use App\Models\Game;
use App\Models\GameQuestion;
use App\Models\RewardTransaction;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\Games\CompetitionResult;
use App\Notifications\Games\CompetitionStarting;
use App\Notifications\Games\InvitationReceived;
use App\Services\Rewards\RewardService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Server-authoritative multiplayer Quick Brain Battle.
 *
 * - Only people with a WAITING ticket can create/join, and only in their own service (queue).
 * - Question windows are fixed on the server clock (epoch ms). Clients poll state; the server
 *   decides correctness, timing, points, ranking and rewards. No client-side scoring.
 * - Being called to the counter pauses/removes the player from the GAME only — the queue is
 *   never read-modified here, so games can never change queue order.
 * - Lifecycle changes are applied lazily on every read (sync) and use compare-and-set updates,
 *   so concurrent polls cannot double-complete or double-reward.
 */
class CompetitionService
{
    public const COUNTDOWN_MS = 5000;   // "3-2-1" before the first question

    public const REVEAL_MS = 4000;      // answer reveal between questions

    public const GRACE_MS = 750;        // network latency allowance after the deadline

    public const LOBBY_TTL_MINUTES = 20;

    public const BASE_POINTS = 100;

    public const SPEED_BONUS = 50;

    public function __construct(private RewardService $rewards)
    {
    }

    public static function nowMs(): int
    {
        return (int) now()->getTimestampMs();
    }

    // ------------------------------------------------------------------ eligibility

    /** The user's ticket that lets them play: status `waiting` (optionally in a given service). */
    public function eligibleTicket(User $user, ?int $serviceId = null): ?Ticket
    {
        return Ticket::with('service')->where('user_id', $user->id)->where('status', 'waiting')
            ->when($serviceId, fn ($q) => $q->where('service_id', $serviceId))
            ->latest('id')->first();
    }

    /** The user's current seat in a live competition, if any. */
    public function activeSeat(User $user): ?CompetitionPlayer
    {
        return CompetitionPlayer::with('competition')->where('user_id', $user->id)
            ->whereIn('status', ['joined', 'ready', 'playing', 'paused'])
            ->whereHas('competition', fn ($q) => $q->whereIn('status', Competition::LIVE))
            ->latest('id')->first();
    }

    /** Open, joinable competitions in a service (queue). */
    public function openIn(int $serviceId, int $limit = 6): Collection
    {
        return Competition::with(['players' => fn ($q) => $q->whereIn('status', CompetitionPlayer::ACTIVE)])
            ->where('service_id', $serviceId)->whereIn('status', Competition::LOBBY)
            ->where('visibility', 'open')->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')->limit($limit)->get()
            ->filter(fn (Competition $c) => $c->players->count() < $c->max_players)->values();
    }

    // ------------------------------------------------------------------ lobby actions

    public function create(User $user, array $opts = []): Competition
    {
        $ticket = $this->eligibleTicket($user);
        if (! $ticket) {
            throw new GameException('Join a queue first — multiplayer battles are for people waiting in the same queue.', 403);
        }
        $this->assertNoOtherSeat($user);
        QuestionBank::ensureSeeded();

        $qCount = (int) ($opts['question_count'] ?? 5);
        $secs = (int) ($opts['seconds_per_question'] ?? 15);
        $category = $opts['category'] ?? null;

        $c = DB::transaction(function () use ($user, $ticket, $opts, $qCount, $secs, $category) {
            $c = new Competition([
                'category' => array_key_exists((string) $category, GameQuestion::CATEGORIES) ? $category : null,
                'visibility' => ($opts['visibility'] ?? 'open') === 'invite' ? 'invite' : 'open',
                'max_players' => max(Competition::MIN_PLAYERS, min(Competition::MAX_PLAYERS, (int) ($opts['max_players'] ?? 4))),
                'question_count' => in_array($qCount, [5, 8, 10], true) ? $qCount : 5,
                'seconds_per_question' => in_array($secs, [10, 15, 20], true) ? $secs : 15,
            ]);
            $c->forceFill([
                'game_id' => Game::quickBrainBattle()->id,
                'service_id' => $ticket->service_id,
                'host_id' => $user->id,
                'status' => 'waiting_for_players',
                'expires_at' => now()->addMinutes(self::LOBBY_TTL_MINUTES),
            ])->save();
            $this->seat($c, $user, $ticket, 'ready'); // host is ready by default

            return $c;
        });
        AuditLog::record('games.competition_created', $c, ['service_id' => $c->service_id]);

        return $c->fresh();
    }

    public function join(Competition $c, User $user, bool $viaInvitation = false): CompetitionPlayer
    {
        $this->sync($c);
        if (! $c->isLobby()) {
            throw new GameException('This competition is no longer accepting players.', 409);
        }
        $ticket = $this->eligibleTicket($user, $c->service_id);
        if (! $ticket) {
            throw new GameException('You can only join competitions in a queue where you are currently waiting.', 403);
        }
        if ($c->visibility === 'invite' && ! $viaInvitation) {
            throw new GameException('This competition is invite-only.', 403);
        }

        $existing = $c->players()->where('user_id', $user->id)->first();
        if ($existing && $existing->isActive()) {
            return $existing;
        }
        $this->assertNoOtherSeat($user, $c->id);

        $player = DB::transaction(function () use ($c, $user, $ticket, $existing) {
            $locked = Competition::whereKey($c->id)->lockForUpdate()->firstOrFail();
            if (! $locked->isLobby()) {
                throw new GameException('This competition is no longer accepting players.', 409);
            }
            if ($locked->players()->whereIn('status', CompetitionPlayer::ACTIVE)->count() >= $locked->max_players) {
                throw new GameException('This competition is full.', 409);
            }
            $p = $this->seat($locked, $user, $ticket, 'joined', $existing);
            $locked->forceFill(['expires_at' => now()->addMinutes(self::LOBBY_TTL_MINUTES)])->save();
            $locked->invitations()->where('invitee_id', $user->id)->where('status', 'pending')
                ->update(['status' => 'accepted', 'responded_at' => now()]);

            return $p;
        });
        $this->refreshLobbyStatus($c);

        return $player;
    }

    public function setReady(Competition $c, User $user, bool $ready = true): void
    {
        $this->sync($c);
        $p = $this->requireSeat($c, $user);
        if (! $c->isLobby() || ! in_array($p->status, ['joined', 'ready'], true)) {
            throw new GameException('You can only change readiness in the lobby.', 409);
        }
        $p->forceFill(['status' => $ready ? 'ready' : 'joined'])->save();
        $this->refreshLobbyStatus($c);
    }

    public function leave(Competition $c, User $user): void
    {
        $this->sync($c);
        $p = $this->requireSeat($c, $user);
        if (! $p->isActive() && $p->status !== 'paused') {
            return;
        }
        $p->forceFill(['status' => 'left', 'left_reason' => 'left_voluntarily'])->save();

        if ($c->isLobby()) {
            if ($c->host_id === $user->id) {
                $next = $c->players()->whereIn('status', CompetitionPlayer::ACTIVE)->orderBy('id')->first();
                if ($next) {
                    $c->forceFill(['host_id' => $next->user_id])->save();
                    $next->forceFill(['status' => 'ready'])->save();
                }
            }
            $this->refreshLobbyStatus($c);
        } else {
            $this->sync($c);
        }
    }

    public function cancel(Competition $c, User $user): void
    {
        $this->sync($c);
        if ($c->host_id !== $user->id) {
            throw new GameException('Only the host can cancel this competition.', 403);
        }
        if (! $c->isLobby()) {
            throw new GameException('A competition that has started cannot be cancelled.', 409);
        }
        $this->markCancelled($c, 'cancelled_by_host');
    }

    /** Host invites a co-waiting participant, identified only by their ticket in the same service. */
    public function invite(Competition $c, User $host, int $ticketId): CompetitionInvitation
    {
        $this->sync($c);
        if ($c->host_id !== $host->id) {
            throw new GameException('Only the host can send invitations.', 403);
        }
        if (! $c->isLobby()) {
            throw new GameException('Invitations can only be sent from the lobby.', 409);
        }
        $ticket = Ticket::with('service')->whereKey($ticketId)->where('service_id', $c->service_id)
            ->where('status', 'waiting')->whereNotNull('user_id')->first();
        if (! $ticket || $ticket->user_id === $host->id) {
            throw new GameException('That person is not waiting in this queue.', 422);
        }
        if ($c->players()->where('user_id', $ticket->user_id)->whereIn('status', CompetitionPlayer::ACTIVE)->exists()) {
            throw new GameException('That person is already in this competition.', 409);
        }
        if ($c->players()->whereIn('status', CompetitionPlayer::ACTIVE)->count() >= $c->max_players) {
            throw new GameException('This competition is already full.', 409);
        }
        if ($c->invitations()->where('invitee_id', $ticket->user_id)->exists()) {
            throw new GameException('You have already invited '.$ticket->token().'.', 409);
        }

        $inv = new CompetitionInvitation();
        $inv->forceFill([
            'competition_id' => $c->id, 'inviter_id' => $host->id, 'invitee_id' => $ticket->user_id,
            'ticket_id' => $ticket->id, 'status' => 'pending',
        ])->save();

        $hostLabel = $c->playerFor($host)?->token_label ?? 'Someone';
        $ticket->user->notify(new InvitationReceived($c, "{$hostLabel} invited you to a Quick Brain Battle while you wait. Accept it from the game hub."));
        AuditLog::record('games.invitation_sent', $c, ['invitation_id' => $inv->id]);

        return $inv;
    }

    public function respond(CompetitionInvitation $inv, User $user, bool $accept): void
    {
        if ($inv->invitee_id !== $user->id) {
            throw new GameException('This invitation is not for you.', 403);
        }
        if ($inv->status !== 'pending') {
            throw new GameException('This invitation has already been answered.', 409);
        }
        $c = $inv->competition;
        $this->sync($c);
        if (! $c->isLobby()) {
            $inv->forceFill(['status' => 'expired', 'responded_at' => now()])->save();
            throw new GameException('That competition is no longer open.', 409);
        }
        if (! $accept) {
            $inv->forceFill(['status' => 'declined', 'responded_at' => now()])->save();

            return;
        }
        $this->join($c, $user, true); // marks the invitation accepted
    }

    // ------------------------------------------------------------------ game

    public function start(Competition $c, User $user): void
    {
        $this->sync($c);
        if ($c->host_id !== $user->id) {
            throw new GameException('Only the host can start the competition.', 403);
        }
        if ($c->status !== 'ready') {
            throw new GameException('At least '.Competition::MIN_PLAYERS.' players must be ready to start.', 409);
        }

        $picked = $this->pickQuestions($c);
        if ($picked->count() < $c->question_count) {
            throw new GameException('Not enough approved questions are available. Ask an admin to approve more.', 409);
        }

        $started = DB::transaction(function () use ($c, $picked) {
            // Compare-and-set: only one request can move ready → starting.
            $startsAt = self::nowMs() + self::COUNTDOWN_MS;
            $moved = Competition::whereKey($c->id)->where('status', 'ready')->update([
                'status' => 'starting', 'starts_at_ms' => $startsAt, 'started_at' => now(), 'updated_at' => now(),
            ]);
            if (! $moved) {
                return false;
            }
            $windowMs = $c->seconds_per_question * 1000;
            foreach ($picked->values() as $i => $q) {
                $opens = $startsAt + $i * ($windowMs + self::REVEAL_MS);
                $cq = new CompetitionQuestion();
                $cq->forceFill([
                    'competition_id' => $c->id, 'game_question_id' => $q->id, 'position' => $i,
                    'opens_at_ms' => $opens, 'closes_at_ms' => $opens + $windowMs,
                ])->save();
                $q->increment('times_used');
            }
            $c->players()->where('status', 'ready')->update(['status' => 'playing', 'updated_at' => now()]);
            $c->players()->where('status', 'joined')->update(['status' => 'left', 'left_reason' => 'not_ready', 'updated_at' => now()]);
            $c->invitations()->where('status', 'pending')->update(['status' => 'expired', 'responded_at' => now()]);

            return true;
        });
        if (! $started) {
            throw new GameException('This competition has already started.', 409);
        }

        $c->refresh();
        foreach ($c->players()->with('user')->where('status', 'playing')->get() as $p) {
            $p->user->notify(new CompetitionStarting($c, "Your Quick Brain Battle is starting — {$c->question_count} questions, {$c->seconds_per_question}s each."));
        }
        AuditLog::record('games.competition_started', $c);
    }

    /**
     * Record one answer. Server decides correctness, timing and points.
     *
     * @return array{correct: bool, correct_index: int, points: int, score: int, explanation: string}
     */
    public function answer(Competition $c, User $user, int $competitionQuestionId, int $choice): array
    {
        $this->sync($c);
        $player = $c->playerFor($user);
        if (! $player) {
            throw new GameException('You are not a player in this competition.', 403);
        }
        if ($c->status !== 'in_progress') {
            throw new GameException('This competition is not accepting answers right now.', 409);
        }
        if ($player->status !== 'playing') {
            throw new GameException($player->status === 'paused'
                ? 'You were called to the counter, so your game is paused. Your score is saved.'
                : 'You are no longer playing in this competition.', 409);
        }
        if ($choice < 0 || $choice > 3) {
            throw new GameException('Invalid choice.', 422);
        }
        $cq = $c->questions()->with('question')->whereKey($competitionQuestionId)->first();
        if (! $cq) {
            throw new GameException('That question is not part of this competition.', 404);
        }

        if ($cq->answers()->where('user_id', $user->id)->exists()) {
            throw new GameException('You have already answered this question.', 409); // (unique index also guards races)
        }
        $now = self::nowMs();
        if ($now < $cq->opens_at_ms) {
            throw new GameException('This question is not open yet.', 422);
        }
        if ($now > $cq->closes_at_ms + self::GRACE_MS) {
            throw new GameException('Too late — time is up for this question.', 422);
        }

        $q = $cq->question;
        $correct = $choice === (int) $q->answer_index;
        $window = max(1, $cq->closes_at_ms - $cq->opens_at_ms);
        $remaining = max(0, $cq->closes_at_ms - $now);
        $points = $correct ? self::BASE_POINTS + (int) round(self::SPEED_BONUS * min(1, $remaining / $window)) : 0;
        $responseMs = max(0, $now - $cq->opens_at_ms);

        try {
            DB::transaction(function () use ($c, $cq, $user, $choice, $correct, $points, $responseMs, $player) {
                $a = new CompetitionAnswer();
                $a->forceFill([
                    'competition_id' => $c->id, 'competition_question_id' => $cq->id, 'user_id' => $user->id,
                    'choice' => $choice, 'is_correct' => $correct, 'points' => $points, 'response_ms' => $responseMs,
                ])->save();
                CompetitionPlayer::whereKey($player->id)->update([
                    'score' => DB::raw('score + '.(int) $points),
                    'correct_count' => DB::raw('correct_count + '.($correct ? 1 : 0)),
                    'total_response_ms' => DB::raw('total_response_ms + '.(int) $responseMs),
                    'updated_at' => now(),
                ]);
            });
        } catch (QueryException $e) {
            throw new GameException('You have already answered this question.', 409);
        }

        $this->advanceIfAllAnswered($c);

        return [
            'correct' => $correct,
            'correct_index' => (int) $q->answer_index,
            'points' => $points,
            'score' => (int) CompetitionPlayer::whereKey($player->id)->value('score'),
            'explanation' => $q->explanation,
        ];
    }

    // ------------------------------------------------------------------ lifecycle (lazy, idempotent)

    /** Apply any due transitions. Safe to call on every request/poll. */
    public function sync(Competition $c): Competition
    {
        if ($c->isFinished()) {
            return $c;
        }

        // 1. Lobby expiry.
        if ($c->isLobby() && $c->expires_at && $c->expires_at->isPast()) {
            $this->markCancelled($c, 'expired');

            return $c->refresh();
        }

        // 2. Ticket checks: being called pauses/removes the player from the game (never the reverse).
        $this->syncPlayerTickets($c);

        if ($c->isLobby()) {
            $this->refreshLobbyStatus($c);

            return $c->refresh();
        }

        $now = self::nowMs();
        if ($c->status === 'starting' && $c->starts_at_ms && $now >= $c->starts_at_ms) {
            Competition::whereKey($c->id)->where('status', 'starting')->update(['status' => 'in_progress', 'updated_at' => now()]);
            $c->refresh();
        }

        if (in_array($c->status, ['starting', 'in_progress'], true)) {
            $playing = $c->players()->where('status', 'playing')->count();
            $lastClose = (int) $c->questions()->max('closes_at_ms');
            if ($playing === 0) {
                $this->complete($c, 'no_active_players');
            } elseif ($c->status === 'in_progress' && $lastClose && $now >= $lastClose) {
                $this->complete($c, 'finished');
            } elseif ($c->status === 'in_progress') {
                $this->advanceIfAllAnswered($c);
            }
        }

        return $c->refresh();
    }

    /** Expire stale lobbies and finish overdue games (used by the hub and `games:expire-stale`). */
    public function expireStale(): int
    {
        $n = 0;
        Competition::whereIn('status', Competition::LIVE)
            ->where(function ($q) {
                $q->where(fn ($l) => $l->whereIn('status', Competition::LOBBY)->where('expires_at', '<', now()))
                    ->orWhere(fn ($g) => $g->whereIn('status', ['starting', 'in_progress'])->where('updated_at', '<', now()->subMinutes(2)));
            })
            ->limit(50)->get()
            ->each(function (Competition $c) use (&$n) {
                $before = $c->status;
                $this->sync($c);
                if ($c->status !== $before) {
                    $n++;
                }
            });

        return $n;
    }

    private function syncPlayerTickets(Competition $c): void
    {
        $players = $c->players()->with('ticket')->whereIn('status', ['joined', 'ready', 'playing', 'paused'])->get();
        foreach ($players as $p) {
            $status = $p->ticket?->status ?? 'left';
            if ($status === 'waiting') {
                if ($p->status === 'paused' && in_array($c->status, ['starting', 'in_progress'], true)) {
                    $p->forceFill(['status' => 'playing', 'left_reason' => null])->save(); // back in the waiting line
                }

                continue;
            }
            if ($c->isLobby()) {
                $p->forceFill(['status' => 'left', 'left_reason' => 'ticket_'.$status])->save();
                if ($c->host_id === $p->user_id) {
                    $next = $c->players()->whereIn('status', CompetitionPlayer::ACTIVE)->orderBy('id')->first();
                    if ($next) {
                        $c->forceFill(['host_id' => $next->user_id])->save();
                        $next->forceFill(['status' => 'ready'])->save();
                    }
                }
            } elseif (in_array($status, ['called', 'serving'], true)) {
                if ($p->status !== 'paused') {
                    $p->forceFill(['status' => 'paused', 'left_reason' => 'called_to_counter'])->save();
                }
            } elseif ($p->status !== 'left') {
                $p->forceFill(['status' => 'left', 'left_reason' => 'ticket_'.$status])->save();
            }
        }
    }

    private function refreshLobbyStatus(Competition $c): void
    {
        $c->refresh();
        if (! $c->isLobby()) {
            return;
        }
        $active = $c->players()->whereIn('status', CompetitionPlayer::ACTIVE)->count();
        if ($active === 0) {
            $this->markCancelled($c, 'everyone_left');

            return;
        }
        $ready = $c->players()->where('status', 'ready')->count();
        $hostReady = $c->players()->where('user_id', $c->host_id)->where('status', 'ready')->exists();
        $target = ($ready >= Competition::MIN_PLAYERS && $hostReady) ? 'ready' : 'waiting_for_players';
        if ($c->status !== $target) {
            Competition::whereKey($c->id)->whereIn('status', Competition::LOBBY)->update(['status' => $target, 'updated_at' => now()]);
            $c->refresh();
        }
    }

    /** When every active player has answered the open question, close it now and pull the schedule forward. */
    private function advanceIfAllAnswered(Competition $c): void
    {
        $now = self::nowMs();
        $cq = $c->questions()->where('opens_at_ms', '<=', $now)->where('closes_at_ms', '>', $now)->first();
        if (! $cq) {
            return;
        }
        $playingIds = $c->players()->where('status', 'playing')->pluck('user_id');
        if ($playingIds->isEmpty()) {
            return;
        }
        $answered = $cq->answers()->whereIn('user_id', $playingIds)->count();
        if ($answered < $playingIds->count()) {
            return;
        }
        DB::transaction(function () use ($c, $cq, $now) {
            $shift = $cq->closes_at_ms - $now;
            // CAS on the old deadline so two concurrent requests shift only once.
            $moved = CompetitionQuestion::whereKey($cq->id)->where('closes_at_ms', $cq->closes_at_ms)->update(['closes_at_ms' => $now]);
            if ($moved && $shift > 0) {
                CompetitionQuestion::where('competition_id', $c->id)->where('position', '>', $cq->position)->update([
                    'opens_at_ms' => DB::raw('opens_at_ms - '.(int) $shift),
                    'closes_at_ms' => DB::raw('closes_at_ms - '.(int) $shift),
                ]);
            }
        });
    }

    private function complete(Competition $c, string $reason): void
    {
        // Compare-and-set: exactly one caller finalises and pays out.
        $won = Competition::whereKey($c->id)->whereIn('status', ['starting', 'in_progress'])->where('rewards_processed', false)
            ->update(['status' => 'completed', 'completed_at' => now(), 'end_reason' => $reason, 'rewards_processed' => true, 'updated_at' => now()]);
        if (! $won) {
            return;
        }
        $c->refresh();

        $answered = CompetitionAnswer::where('competition_id', $c->id)->selectRaw('user_id, count(*) as n')->groupBy('user_id')->pluck('n', 'user_id');

        // Everyone who took part: still playing, paused (called to the counter — score kept), or left mid-game after answering.
        $participants = $c->players()->with('user')->get()
            ->filter(fn (CompetitionPlayer $p) => in_array($p->status, ['playing', 'paused', 'finished'], true)
                || ($p->status === 'left' && ($answered[$p->user_id] ?? 0) > 0))
            ->sortBy([['score', 'desc'], ['correct_count', 'desc'], ['total_response_ms', 'asc'], ['id', 'asc']])
            ->values();
        $count = $participants->count();

        foreach ($participants as $i => $p) {
            $rank = $i + 1;
            $points = 0;
            if (($answered[$p->user_id] ?? 0) > 0) {
                $points = 5 + 2 * $p->correct_count;
                if ($count >= 2 && $p->score > 0) {
                    $points += $rank === 1 ? 15 : ($rank === 2 ? 5 : 0);
                }
            }
            $awarded = 0;
            if ($points > 0) {
                $key = "competition:{$c->id}:user:{$p->user_id}";
                $tx = $this->rewards->award($p->user, $points, 'competition', "Quick Brain Battle #{$c->id} — rank {$rank} of {$count}", $key, $c);
                $awarded = $tx?->points ?? (int) RewardTransaction::where('reference_key', $key)->value('points');
            }
            $p->forceFill([
                'rank' => $rank,
                'reward_points' => $awarded,
                'status' => $p->status === 'playing' ? 'finished' : $p->status,
            ])->save();

            $msg = "Quick Brain Battle results: you placed #{$rank} of {$count} with {$p->score} points"
                .($awarded > 0 ? " and earned {$awarded} WaitPoints (approved)." : '.');
            $p->user->notify(new CompetitionResult($c, $msg));
        }
        AuditLog::record('games.competition_completed', $c, ['reason' => $reason, 'players' => $count]);
    }

    private function markCancelled(Competition $c, string $reason): void
    {
        $moved = Competition::whereKey($c->id)->whereIn('status', Competition::LOBBY)
            ->update(['status' => 'cancelled', 'end_reason' => $reason, 'completed_at' => now(), 'updated_at' => now()]);
        if ($moved) {
            $c->invitations()->where('status', 'pending')->update(['status' => 'expired', 'responded_at' => now()]);
        }
        $c->refresh();
    }

    // ------------------------------------------------------------------ helpers

    private function seat(Competition $c, User $user, Ticket $ticket, string $status, ?CompetitionPlayer $existing = null): CompetitionPlayer
    {
        $p = $existing ?? new CompetitionPlayer();
        $p->forceFill([
            'competition_id' => $c->id, 'user_id' => $user->id, 'ticket_id' => $ticket->id,
            'token_label' => $ticket->token(), 'status' => $status, 'left_reason' => null,
        ])->save();

        return $p;
    }

    private function requireSeat(Competition $c, User $user): CompetitionPlayer
    {
        $p = $c->players()->where('user_id', $user->id)->first();
        if (! $p) {
            throw new GameException('You are not a player in this competition.', 403);
        }

        return $p;
    }

    private function assertNoOtherSeat(User $user, ?int $exceptCompetitionId = null): void
    {
        $seat = $this->activeSeat($user);
        if ($seat && $seat->competition_id !== $exceptCompetitionId) {
            throw new GameException('You are already in another live competition. Finish or leave it first.', 409);
        }
    }

    private function pickQuestions(Competition $c): Collection
    {
        QuestionBank::ensureSeeded($c->question_count);
        $base = GameQuestion::approved()->when($c->category, fn ($q) => $q->where('category', $c->category));
        $picked = (clone $base)->inRandomOrder()->limit($c->question_count)->get();
        if ($picked->count() < $c->question_count) { // top up from any category
            $more = GameQuestion::approved()->whereNotIn('id', $picked->pluck('id'))->inRandomOrder()
                ->limit($c->question_count - $picked->count())->get();
            $picked = $picked->concat($more);
        }

        return $picked;
    }

    // ------------------------------------------------------------------ read model

    /** JSON state for one viewer. Others are only ever identified by token label. */
    public function stateFor(Competition $c, User $viewer): array
    {
        $c->loadMissing('service');
        $now = self::nowMs();
        $players = $c->players()->orderBy('id')->get();
        $me = $players->firstWhere('user_id', $viewer->id);
        $lobby = $c->isLobby();

        $current = null;
        $next = null;
        if (in_array($c->status, ['starting', 'in_progress', 'completed'], true)) {
            $questions = $c->questions()->with('question')->get();
            $current = $questions->filter(fn ($q) => $q->opens_at_ms <= $now && $now < $q->closes_at_ms + self::REVEAL_MS)->last();
            $next = $questions->first(fn ($q) => $q->opens_at_ms > $now);
            if ($c->status === 'completed') {
                $current = null;
            }
        }

        $answeredIds = $current ? $current->answers()->pluck('user_id')->all() : [];
        $myAnswer = $current ? $current->answers()->where('user_id', $viewer->id)->first() : null;

        $visible = $players->filter(fn ($p) => $lobby ? $p->isActive() : $p->left_reason !== 'not_ready');
        $maxScore = max(1, (int) $visible->max('score'));

        $questionData = null;
        if ($current) {
            $closed = $now >= $current->closes_at_ms;
            $reveal = $closed || $myAnswer;
            $questionData = [
                'id' => $current->id,
                'number' => $current->position + 1,
                'total' => $c->question_count,
                'text' => $current->question->question,
                'options' => $current->question->options,
                'category' => $current->question->categoryLabel(),
                'difficulty' => $current->question->difficulty,
                'opens_at' => $current->opens_at_ms,
                'closes_at' => $current->closes_at_ms,
                'phase' => $closed ? 'reveal' : 'open',
                'your_choice' => $myAnswer?->choice,
                'your_points' => $myAnswer?->points,
                'correct_index' => $reveal ? (int) $current->question->answer_index : null,
                'explanation' => $reveal ? $current->question->explanation : null,
                'answered_count' => count($answeredIds),
            ];
        }

        $isHost = $c->host_id === $viewer->id;
        $candidates = [];
        $invites = [];
        if ($isHost && $lobby) {
            $taken = $players->filter->isActive()->pluck('user_id')->push($viewer->id);
            $invitedIds = $c->invitations()->pluck('invitee_id');
            $candidates = Ticket::with('service')->where('service_id', $c->service_id)->where('status', 'waiting')
                ->whereNotNull('user_id')->whereNotIn('user_id', $taken)->whereNotIn('user_id', $invitedIds)
                ->orderBy('number')->limit(12)->get()
                ->map(fn (Ticket $t) => ['ticket_id' => $t->id, 'label' => $t->token()])->values()->all();
            $invites = $c->invitations()->with('ticket.service')->latest('id')->get()
                ->map(fn ($i) => ['label' => $i->ticket ? $i->ticket->token() : 'Guest', 'status' => $i->status])->all();
        }

        return [
            'id' => $c->id,
            'status' => $c->status,
            'status_label' => $c->statusLabel(),
            'service' => $c->service->name,
            'category' => $c->categoryLabel(),
            'visibility' => $c->visibility,
            'max_players' => $c->max_players,
            'question_count' => $c->question_count,
            'seconds_per_question' => $c->seconds_per_question,
            'server_time' => $now,
            'starts_at' => $c->starts_at_ms,
            'next_opens_at' => $next?->opens_at_ms,
            'is_host' => $isHost,
            'can_start' => $isHost && $c->status === 'ready',
            'ready_count' => $players->where('status', 'ready')->count(),
            'me' => $me ? [
                'status' => $me->status, 'label' => $me->token_label, 'score' => $me->score,
                'correct' => $me->correct_count, 'rank' => $me->rank, 'reward_points' => $me->reward_points,
            ] : null,
            'players' => $visible->map(fn (CompetitionPlayer $p) => [
                'id' => $p->id,
                'label' => $p->token_label,
                'is_you' => $p->user_id === $viewer->id,
                'is_host' => $p->user_id === $c->host_id,
                'status' => $p->status,
                'score' => $p->score,
                'correct' => $p->correct_count,
                'rank' => $p->rank,
                'bar' => (int) round($p->score / $maxScore * 100),
                'answered' => in_array($p->user_id, $answeredIds, true),
            ])->values()->all(),
            'question' => $questionData,
            'candidates' => $candidates,
            'invites' => $invites,
            'end_reason' => $c->end_reason,
            'results_url' => $c->status === 'completed' ? route('games.competitions.results', $c) : null,
        ];
    }
}
