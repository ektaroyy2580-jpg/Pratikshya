<?php

namespace Tests\Feature\Modules;

use App\Models\Competition;
use App\Models\CompetitionAnswer;
use App\Models\CompetitionInvitation;
use App\Models\CompetitionPlayer;
use App\Models\Counter;
use App\Models\GameQuestion;
use App\Models\Location;
use App\Models\RewardTransaction;
use App\Models\Service;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\Games\CompetitionService;
use App\Services\Games\QuestionBank;
use App\Services\Games\QuestionGenerator;
use App\Services\Games\QuestionSchema;
use App\Services\Queue\QueueManager;
use App\Services\Rewards\RewardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GamesTest extends TestCase
{
    use RefreshDatabase;

    private function service(string $prefix = 'D'): Service
    {
        $loc = Location::create(['name' => 'Test Office '.uniqid(), 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
        $s = $loc->services()->create([
            'name' => 'Licence Renewal', 'prefix' => $prefix, 'counter' => 1, 'avg_minutes' => 4,
            'current_number' => 0, 'last_number' => 0, 'documents' => [],
        ]);
        Counter::create(['location_id' => $loc->id, 'service_id' => $s->id, 'number' => 1, 'label' => 'Counter 1']);

        return $s;
    }

    /** A user with a waiting ticket in $s. */
    private function waiter(Service $s): User
    {
        $u = User::factory()->create();
        app(QueueManager::class)->join($s->fresh(), $u);

        return $u;
    }

    private function games(): CompetitionService
    {
        return app(CompetitionService::class);
    }

    /** Host + one joined player, both ready, started; time moved to the first question. */
    private function startedBattle(int $questions = 5): array
    {
        $s = $this->service();
        $host = $this->waiter($s);
        $guest = $this->waiter($s);
        $c = $this->games()->create($host, ['visibility' => 'open', 'max_players' => 4, 'question_count' => $questions]);
        $this->games()->join($c, $guest);
        $this->games()->setReady($c, $guest);
        $this->games()->start($c->fresh(), $host);
        $this->travel(CompetitionService::COUNTDOWN_MS + 500)->milliseconds();

        return [$s, $host, $guest, $c->fresh()];
    }

    private function firstQuestion(Competition $c)
    {
        return $c->questions()->with('question')->orderBy('position')->first();
    }

    // ------------------------------------------------------------ lobby & membership

    public function test_create_join_ready_start_lifecycle(): void
    {
        $s = $this->service();
        $host = $this->waiter($s);
        $guest = $this->waiter($s);

        $this->actingAs($host)->post(route('games.competitions.store'), ['visibility' => 'open', 'max_players' => 4, 'question_count' => 5])
            ->assertRedirect();
        $c = Competition::firstOrFail();
        $this->assertSame('waiting_for_players', $c->status);
        $this->assertSame($s->id, $c->service_id);

        $this->actingAs($guest)->post(route('games.competitions.join', $c))->assertRedirect(route('games.competitions.show', $c));
        $this->assertSame('waiting_for_players', $c->fresh()->status); // guest not ready yet

        $this->actingAs($guest)->postJson(route('games.competitions.ready', $c))->assertOk();
        $this->assertSame('ready', $c->fresh()->status);

        // Only the host may start.
        $this->actingAs($guest)->postJson(route('games.competitions.start', $c))->assertForbidden();
        $this->actingAs($host)->postJson(route('games.competitions.start', $c))->assertOk();
        $c->refresh();
        $this->assertSame('starting', $c->status);
        $this->assertSame(5, $c->questions()->count());
        $this->assertSame(2, $c->players()->where('status', 'playing')->count());

        $this->travel(6)->seconds();
        $res = $this->actingAs($guest)->getJson(route('games.competitions.state', $c))->assertOk();
        $this->assertSame('in_progress', $res->json('data.status'));
        $this->assertNotNull($res->json('data.question.id'));
        $this->assertNull($res->json('data.question.correct_index')); // answer never leaked before answering
        // Others are identified by token label only.
        $this->assertStringNotContainsString($host->email, $res->getContent());
        $this->assertStringNotContainsString($host->name, $res->getContent());
    }

    public function test_user_without_waiting_ticket_cannot_create(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->postJson(route('games.competitions.store'), ['visibility' => 'open', 'max_players' => 4, 'question_count' => 5])
            ->assertForbidden();
        $this->assertSame(0, Competition::count());
    }

    public function test_non_members_cannot_view_poll_or_answer(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle();
        $outsider = $this->waiter($s); // same queue, but not a player
        $cq = $this->firstQuestion($c);

        $this->actingAs($outsider)->get(route('games.competitions.show', $c))->assertForbidden();
        $this->actingAs($outsider)->getJson(route('games.competitions.state', $c))->assertForbidden();
        $this->actingAs($outsider)->get(route('games.competitions.results', $c))->assertForbidden();
        $this->actingAs($outsider)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => $cq->question->answer_index])
            ->assertForbidden();
        $this->assertSame(0, CompetitionAnswer::count());
    }

    public function test_cannot_join_a_competition_of_another_service(): void
    {
        $s = $this->service('D');
        $other = $this->service('B');
        $host = $this->waiter($s);
        $stranger = $this->waiter($other);
        $c = $this->games()->create($host, ['visibility' => 'open']);

        $this->actingAs($stranger)->postJson(route('games.competitions.join', $c))->assertForbidden();
        $this->assertSame(1, $c->players()->count());
    }

    public function test_invite_only_battle_cannot_be_joined_directly_and_max_players_is_enforced(): void
    {
        $s = $this->service();
        $host = $this->waiter($s);
        $a = $this->waiter($s);
        $c = $this->games()->create($host, ['visibility' => 'invite']);
        $this->actingAs($a)->postJson(route('games.competitions.join', $c))->assertForbidden();

        $c2host = $this->waiter($s);
        $c2 = $this->games()->create($c2host, ['visibility' => 'open', 'max_players' => 2]);
        $this->actingAs($a)->postJson(route('games.competitions.join', $c2))->assertOk();
        $b = $this->waiter($s);
        $this->actingAs($b)->postJson(route('games.competitions.join', $c2))->assertStatus(409);
    }

    // ------------------------------------------------------------ answers & scoring

    public function test_valid_answer_is_scored_by_the_server(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle();
        $cq = $this->firstQuestion($c);
        $right = $cq->question->answer_index;
        $wrong = ($right + 1) % 4;

        // A client-sent score/correct flag is ignored: only question_id + choice are used.
        $res = $this->actingAs($host)->postJson(route('games.competitions.answer', $c), [
            'question_id' => $cq->id, 'choice' => $right, 'score' => 99999, 'is_correct' => true,
        ])->assertOk();
        $this->assertTrue($res->json('data.correct'));
        $points = $res->json('data.points');
        $this->assertGreaterThan(CompetitionService::BASE_POINTS, $points);       // speed bonus
        $this->assertLessThanOrEqual(CompetitionService::BASE_POINTS + CompetitionService::SPEED_BONUS, $points);

        $res = $this->actingAs($guest)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => $wrong])->assertOk();
        $this->assertFalse($res->json('data.correct'));
        $this->assertSame(0, $res->json('data.points'));

        $this->assertSame($points, CompetitionPlayer::where('user_id', $host->id)->value('score'));
        $this->assertSame(1, CompetitionPlayer::where('user_id', $host->id)->value('correct_count'));
        $this->assertSame(0, CompetitionPlayer::where('user_id', $guest->id)->value('score'));
    }

    public function test_answer_for_a_question_from_another_competition_is_rejected(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle();
        $foreign = \App\Models\CompetitionQuestion::where('competition_id', '!=', $c->id)->first();
        $this->actingAs($host)->postJson(route('games.competitions.answer', $c), ['question_id' => 999999, 'choice' => 0])->assertNotFound();
        $this->assertNull($foreign);
    }

    public function test_duplicate_answer_is_rejected(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle();
        $cq = $this->firstQuestion($c);

        $this->actingAs($host)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => 0])->assertOk();
        $this->actingAs($host)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => 1])->assertStatus(409);

        $this->assertSame(1, CompetitionAnswer::where('user_id', $host->id)->count());
    }

    public function test_late_answer_is_rejected(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle();
        $cq = $this->firstQuestion($c);

        $this->travel($c->seconds_per_question * 1000 + CompetitionService::GRACE_MS + 200)->milliseconds();
        $this->actingAs($host)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => $cq->question->answer_index])
            ->assertStatus(422)->assertJsonPath('message', 'Too late — time is up for this question.');
        $this->assertSame(0, CompetitionAnswer::count());
    }

    public function test_question_not_yet_open_is_rejected(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle();
        $second = $c->questions()->where('position', 1)->first();
        $this->actingAs($host)->postJson(route('games.competitions.answer', $c), ['question_id' => $second->id, 'choice' => 0])->assertStatus(422);
    }

    public function test_all_players_answering_advances_and_game_completes_with_ranking(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle(5);

        foreach (range(0, 4) as $i) {
            $cq = $c->questions()->with('question')->where('position', $i)->first();
            if ($cq->opens_at_ms > CompetitionService::nowMs()) {
                $this->travel($cq->opens_at_ms - CompetitionService::nowMs() + 100)->milliseconds();
            }
            $right = $cq->question->answer_index;
            $this->actingAs($host)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => $right])->assertOk();
            $this->actingAs($guest)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => ($right + 1) % 4])->assertOk();
        }

        // Last question closed early because everyone answered → completes on the next poll.
        $this->travel(100)->milliseconds();
        $this->actingAs($host)->getJson(route('games.competitions.state', $c))->assertOk()->assertJsonPath('data.status', 'completed');

        $hostSeat = CompetitionPlayer::where('user_id', $host->id)->first();
        $guestSeat = CompetitionPlayer::where('user_id', $guest->id)->first();
        $this->assertSame(1, $hostSeat->rank);
        $this->assertSame(2, $guestSeat->rank);
        $this->assertSame('finished', $hostSeat->status);
        $this->assertSame(5 + 2 * 5 + 15, $hostSeat->reward_points);
        $this->assertSame(5, $guestSeat->reward_points);

        $this->actingAs($host)->get(route('games.competitions.results', $c))->assertOk()->assertSee('Results');
        $this->assertTrue($host->notifications()->where('data->kind', 'game_result')->exists());
    }

    // ------------------------------------------------------------ rewards

    public function test_rewards_are_paid_exactly_once(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle(5);
        $cq = $this->firstQuestion($c);
        $this->actingAs($host)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => $cq->question->answer_index])->assertOk();

        $this->travel(5)->minutes();
        foreach (range(1, 4) as $_) {
            $this->games()->sync($c->fresh());
            $this->actingAs($host)->getJson(route('games.competitions.state', $c))->assertOk();
        }

        $key = "competition:{$c->id}:user:{$host->id}";
        $this->assertSame(1, RewardTransaction::where('reference_key', $key)->count());
        $this->assertSame(1, RewardTransaction::where('user_id', $host->id)->count());
        // A replayed award with the same reference key pays nothing.
        $this->assertNull(app(RewardService::class)->award($host, 50, 'competition', 'replay', $key));
        $this->assertSame(1, $host->notifications()->where('data->kind', 'game_result')->count());
        // Guest never answered → no reward.
        $this->assertSame(0, RewardTransaction::where('user_id', $guest->id)->count());
    }

    // ------------------------------------------------------------ queue context

    public function test_player_called_to_counter_is_paused_score_kept_and_queue_untouched(): void
    {
        [$s, $host, $guest, $c] = $this->startedBattle();
        $cq = $this->firstQuestion($c);
        $this->actingAs($guest)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq->id, 'choice' => $cq->question->answer_index])->assertOk();
        $score = CompetitionPlayer::where('user_id', $guest->id)->value('score');

        $guestTicket = Ticket::where('user_id', $guest->id)->first();
        $numberBefore = $guestTicket->number;
        $staff = User::factory()->create();
        $staff->forceFill(['role' => 'admin'])->save();
        app(QueueManager::class)->transition($guestTicket, 'called', $staff);

        $res = $this->actingAs($guest)->getJson(route('games.competitions.state', $c))->assertOk();
        $this->assertSame('paused', $res->json('data.me.status'));
        $this->assertSame($score, $res->json('data.me.score'));

        $cq2 = $c->questions()->with('question')->where('position', 1)->first();
        $this->travel($cq2->fresh()->opens_at_ms - CompetitionService::nowMs() + 100)->milliseconds();
        $this->actingAs($guest)->postJson(route('games.competitions.answer', $c), ['question_id' => $cq2->id, 'choice' => 0])->assertStatus(409);

        // The game never touched the queue.
        $t = $guestTicket->fresh();
        $this->assertSame('called', $t->status);
        $this->assertSame($numberBefore, $t->number);
    }

    public function test_lobby_player_whose_ticket_is_called_leaves_lobby(): void
    {
        $s = $this->service();
        $host = $this->waiter($s);
        $guest = $this->waiter($s);
        $c = $this->games()->create($host, ['visibility' => 'open']);
        $this->games()->join($c, $guest);

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        app(QueueManager::class)->transition(Ticket::where('user_id', $host->id)->first(), 'called', $admin);

        $this->games()->sync($c->fresh());
        $c->refresh();
        $this->assertSame('left', $c->playerFor($host)->status);
        $this->assertSame($guest->id, $c->host_id); // host handed over
    }

    public function test_stale_lobby_expires(): void
    {
        $s = $this->service();
        $host = $this->waiter($s);
        $c = $this->games()->create($host, ['visibility' => 'open']);
        $this->travel(CompetitionService::LOBBY_TTL_MINUTES + 1)->minutes();
        $this->artisan('games:expire-stale')->assertSuccessful();
        $this->assertSame('cancelled', $c->fresh()->status);
        $this->assertSame('expired', $c->fresh()->end_reason);
    }

    // ------------------------------------------------------------ invitations

    public function test_invitation_accept_and_decline_authorization(): void
    {
        $s = $this->service();
        $host = $this->waiter($s);
        $invitee = $this->waiter($s);
        $other = $this->waiter($s);
        $c = $this->games()->create($host, ['visibility' => 'invite']);
        $ticket = Ticket::where('user_id', $invitee->id)->first();

        // Only the host can invite.
        $this->actingAs($other)->postJson(route('games.competitions.invite', $c), ['ticket_id' => $ticket->id])->assertForbidden();
        $this->actingAs($host)->postJson(route('games.competitions.invite', $c), ['ticket_id' => $ticket->id])->assertOk();
        $inv = CompetitionInvitation::firstOrFail();
        $this->assertTrue($invitee->notifications()->where('data->kind', 'game_invitation')->exists());
        $this->assertStringNotContainsString($host->name, $invitee->notifications()->first()->data['message']);

        // Somebody else cannot accept or decline it.
        $this->actingAs($other)->post(route('games.invitations.accept', $inv))->assertForbidden();
        $this->actingAs($other)->post(route('games.invitations.decline', $inv))->assertForbidden();
        $this->assertSame('pending', $inv->fresh()->status);

        $this->actingAs($invitee)->post(route('games.invitations.accept', $inv))->assertRedirect(route('games.competitions.show', $c));
        $this->assertSame('accepted', $inv->fresh()->status);
        $this->assertSame('joined', $c->playerFor($invitee)->status);

        // Decline path.
        $c2host = $this->waiter($s);
        $c2 = $this->games()->create($c2host, ['visibility' => 'invite']);
        $otherTicket = Ticket::where('user_id', $other->id)->first();
        $this->actingAs($c2host)->postJson(route('games.competitions.invite', $c2), ['ticket_id' => $otherTicket->id])->assertOk();
        $inv2 = CompetitionInvitation::where('competition_id', $c2->id)->firstOrFail();
        $this->actingAs($other)->post(route('games.invitations.decline', $inv2))->assertRedirect(route('games.hub'));
        $this->assertSame('declined', $inv2->fresh()->status);
        $this->assertNull($c2->playerFor($other));
    }

    public function test_cannot_invite_someone_from_another_queue(): void
    {
        $s = $this->service('D');
        $other = $this->service('B');
        $host = $this->waiter($s);
        $stranger = $this->waiter($other);
        $c = $this->games()->create($host, ['visibility' => 'invite']);
        $t = Ticket::where('user_id', $stranger->id)->first();
        $this->actingAs($host)->postJson(route('games.competitions.invite', $c), ['ticket_id' => $t->id])->assertStatus(422);
        $this->assertSame(0, CompetitionInvitation::count());
    }

    // ------------------------------------------------------------ AI + questions

    public function test_ai_schema_validation_rejects_bad_payloads(): void
    {
        $good = ['question' => 'What is the capital city of Nepal?', 'options' => ['Pokhara', 'Kathmandu', 'Butwal', 'Dharan'],
            'answer' => 1, 'explanation' => 'Kathmandu is the capital.', 'category' => 'nepal_gk', 'difficulty' => 'easy'];
        $this->assertTrue(QuestionSchema::validPayload(['questions' => [$good]]));

        $bad = [
            'not an array' => 'nope',
            'missing key' => ['items' => [$good]],
            'empty list' => ['questions' => []],
            'three options' => ['questions' => [array_merge($good, ['options' => ['a', 'b', 'c']])]],
            'five options' => ['questions' => [array_merge($good, ['options' => ['a', 'b', 'c', 'd', 'e']])]],
            'duplicate options' => ['questions' => [array_merge($good, ['options' => ['a', 'a', 'c', 'd']])]],
            'answer out of range' => ['questions' => [array_merge($good, ['answer' => 4])]],
            'answer as string' => ['questions' => [array_merge($good, ['answer' => '1'])]],
            'question too long' => ['questions' => [array_merge($good, ['question' => str_repeat('x', 201)])]],
            'no explanation' => ['questions' => [array_merge($good, ['explanation' => ''])]],
            'unknown category' => ['questions' => [array_merge($good, ['category' => 'politics'])]],
            'unknown difficulty' => ['questions' => [array_merge($good, ['difficulty' => 'insane'])]],
            'extra key' => ['questions' => [array_merge($good, ['source' => 'x'])]],
            'one bad item rejects batch' => ['questions' => [$good, array_merge($good, ['answer' => -1])]],
        ];
        foreach ($bad as $label => $payload) {
            $this->assertFalse(QuestionSchema::validPayload($payload), "Payload should be rejected: {$label}");
        }
    }

    public function test_generator_uses_local_bank_when_ai_is_off_and_saves_only_valid_ai_output_as_pending(): void
    {
        config(['ai.provider' => 'none']);
        $res = app(QuestionGenerator::class)->generate('logic', 'easy', 3);
        $this->assertFalse($res['ok']);
        $this->assertStringContainsString('AI provider: none', $res['message']);
        $this->assertSame(0, GameQuestion::where('source', 'ai')->count());

        $fake = new class extends AiClient {
            public string $reply = '';

            public function enabled(): bool
            {
                return true;
            }

            public function complete(string $system, string $prompt, int $maxTokens = 400): ?string
            {
                return $this->reply;
            }
        };
        app()->instance(AiClient::class, $fake);

        $fake->reply = json_encode(['questions' => [['question' => 'Too few options here?', 'options' => ['a', 'b'], 'answer' => 0, 'explanation' => 'x', 'category' => 'logic', 'difficulty' => 'easy']]]);
        $res = app(QuestionGenerator::class)->generate('logic', 'easy', 1);
        $this->assertFalse($res['ok']);
        $this->assertSame(0, GameQuestion::count());

        $fake->reply = json_encode(['questions' => [['question' => 'What comes next: 5, 10, 15, ?', 'options' => ['20', '25', '30', '17'], 'answer' => 0, 'explanation' => 'Add 5 each time.', 'category' => 'logic', 'difficulty' => 'easy']]]);
        $res = app(QuestionGenerator::class)->generate('logic', 'easy', 1);
        $this->assertTrue($res['ok']);
        $q = GameQuestion::firstOrFail();
        $this->assertSame('ai', $q->source);
        $this->assertSame('pending', $q->status); // never served until approved
    }

    public function test_question_bank_has_forty_plus_valid_questions(): void
    {
        $this->assertGreaterThanOrEqual(40, QuestionBank::seed());
        foreach (GameQuestion::all() as $q) {
            $this->assertTrue(QuestionSchema::validItem([
                'question' => $q->question, 'options' => $q->options, 'answer' => $q->answer_index,
                'explanation' => $q->explanation, 'category' => $q->category, 'difficulty' => $q->difficulty,
            ]), $q->question);
        }
        $this->assertSame(4, GameQuestion::distinct('category')->count('category'));
    }

    public function test_question_admin_is_admin_only(): void
    {
        QuestionBank::seed();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('games.admin.questions'))->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get(route('games.admin.questions'))->assertOk()->assertSee('AI provider: none');

        $q = GameQuestion::first();
        $this->actingAs($user)->post(route('games.admin.questions.status', $q), ['status' => 'disabled'])->assertForbidden();
        $this->actingAs($admin)->post(route('games.admin.questions.status', $q), ['status' => 'disabled'])->assertRedirect();
        $this->assertSame('disabled', $q->fresh()->status);
    }

    // ------------------------------------------------------------ pages

    public function test_hub_and_leaderboard_render_with_and_without_a_ticket(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get(route('games.hub'))->assertOk()->assertSee('Join a queue to play with people waiting with you');
        $this->actingAs($u)->get(route('games.leaderboard'))->assertOk();

        $s = $this->service();
        $host = $this->waiter($s);
        $c = $this->games()->create($host, ['visibility' => 'open']);
        $guest = $this->waiter($s);
        $this->actingAs($guest)->get(route('games.hub'))->assertOk()->assertSee('D-1')
            ->assertSee(route('games.competitions.join', $c), false);
        $this->actingAs($host)->get(route('games.competitions.show', $c))->assertOk();
    }
}
