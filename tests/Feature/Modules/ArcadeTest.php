<?php

namespace Tests\Feature\Modules;

use App\Models\ArcadeSession;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\Arcade\AiQuestionSource;
use App\Services\Arcade\Games\SudokuGame;
use App\Services\Arcade\Games\UnoGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArcadeTest extends TestCase
{
    use RefreshDatabase;

    private function startGame(User $user, string $game, array $body = []): array
    {
        $res = $this->actingAs($user)->postJson(route('arcade.start', $game), $body)->assertCreated();

        return $res->json('data');
    }

    /** Pretend the session started $seconds ago (minimum-time checks). */
    private function age(int $sessionId, int $seconds): ArcadeSession
    {
        $s = ArcadeSession::findOrFail($sessionId);
        $s->forceFill(['started_at' => now()->subSeconds($seconds)])->save();

        return $s;
    }

    public function test_pages_render_and_registry_routes_exist(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('arcade.index'))->assertOk()->assertSee('Memory Match');
        foreach (config('games.arcade') as $entry) {
            $this->actingAs($user)->get(route($entry['route']))->assertOk()->assertSee($entry['title']);
        }
        $this->get(route('arcade.play', 'nope'))->assertNotFound();
    }

    public function test_guests_cannot_start(): void
    {
        $this->postJson(route('arcade.start', 'memory'))->assertUnauthorized();
    }

    public function test_start_hides_server_answers(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'quiz');
        $this->assertCount(8, $data['game']['questions']);
        $this->assertArrayNotHasKey('answer', $data['game']['questions'][0]);
        $this->assertArrayNotHasKey('explain', $data['game']['questions'][0]);
        $this->assertArrayNotHasKey('payload', ArcadeSession::first()->toArray());

        $sudoku = $this->startGame($user, 'sudoku', ['variant' => 'easy']);
        $this->assertArrayNotHasKey('solution', $sudoku['game']);
    }

    public function test_cannot_act_on_or_finish_another_users_session(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $data = $this->startGame($owner, 'reaction');

        $this->actingAs($other)->postJson($data['urls']['finish'], ['times' => [250, 260, 270, 280, 290]])->assertForbidden();
        $quiz = $this->startGame($owner, 'quiz');
        $this->actingAs($other)->postJson($quiz['urls']['action'], ['index' => 0, 'choice' => 0])->assertForbidden();
        $this->assertSame(0, ArcadeSession::where('status', '!=', 'active')->count());
    }

    public function test_quiz_score_is_computed_server_side_and_answers_lock(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'quiz');
        $session = ArcadeSession::findOrFail($data['session']);
        $answers = array_column($session->payload['questions'], 'answer');

        // Answer the first 5 correctly, the last 3 wrong.
        foreach ($answers as $i => $correct) {
            $choice = $i < 5 ? $correct : ($correct + 1) % 4;
            $r = $this->actingAs($user)->postJson($data['urls']['action'], ['index' => $i, 'choice' => $choice])->assertOk();
            $this->assertSame($i < 5, $r->json('data.correct'));
        }
        // Re-answering a locked question is refused.
        $this->actingAs($user)->postJson($data['urls']['action'], ['index' => 0, 'choice' => $answers[0]])->assertStatus(409);

        $this->age($data['session'], 60);
        // The client-claimed score is ignored: the server computes it from locked answers.
        $res = $this->actingAs($user)->postJson($data['urls']['finish'], ['score' => 99999])->assertOk();
        // 5-streak: 100 + 120 + 140 + 160 + 180 = 700
        $this->assertSame(700, $res->json('data.score'));
        $this->assertSame(700, ArcadeSession::find($data['session'])->score);
        $this->assertTrue($res->json('data.verified'));
    }

    public function test_duplicate_finish_rejected_and_reward_granted_once(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'reaction');
        $this->age($data['session'], 40);
        $times = ['times' => [200, 210, 220, 230, 240]];   // avg 220 ms → 10 points

        $first = $this->actingAs($user)->postJson($data['urls']['finish'], $times)->assertOk();
        $this->assertSame(10, $first->json('data.points'));
        $this->actingAs($user)->postJson($data['urls']['finish'], $times)->assertStatus(409);

        $this->assertSame(1, RewardTransaction::where('reference_key', 'arcade:'.$data['session'])->count());
        $this->assertSame(10, (int) RewardTransaction::where('user_id', $user->id)->sum('points'));
        $this->assertSame(1, ArcadeSession::where('status', 'finished')->count());
    }

    public function test_impossible_reaction_time_rejected(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'reaction');
        $this->age($data['session'], 40);

        $this->actingAs($user)->postJson($data['urls']['finish'], ['times' => [40, 250, 250, 250, 250]])
            ->assertStatus(422)->assertJsonPath('data.final', true);
        $this->assertSame('rejected', ArcadeSession::find($data['session'])->status);
        $this->assertSame(0, RewardTransaction::count());
    }

    public function test_reaction_submitted_too_fast_is_rejected(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'reaction');   // started "now": delays alone take ≥ 6 s
        $this->actingAs($user)->postJson($data['urls']['finish'], ['times' => [250, 250, 250, 250, 250]])->assertStatus(422);
        $this->assertSame(0, RewardTransaction::count());
    }

    public function test_invalid_sudoku_rejected_and_valid_accepted(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'sudoku', ['variant' => 'medium']);
        $session = $this->age($data['session'], 300);
        $solution = $session->payload['solution'];
        $puzzle = $data['game']['puzzle'];

        // Swap two non-given cells in the same row: still 1-9 per row, but columns break.
        $broken = $solution;
        for ($r = 0; $r < 9; $r++) {
            $free = array_values(array_filter(range($r * 9, $r * 9 + 8), fn ($i) => $puzzle[$i] === 0 && true));
            if (count($free) >= 2) {
                [$a, $b] = [$free[0], $free[1]];
                [$broken[$a], $broken[$b]] = [$broken[$b], $broken[$a]];
                break;
            }
        }
        $this->actingAs($user)->postJson($data['urls']['finish'], ['grid' => $broken])->assertStatus(422)->assertJsonPath('data.final', false);
        // Changing a given is also refused.
        $tampered = $solution;
        $given = array_search(true, array_map(fn ($v) => $v > 0, $puzzle), true);
        $tampered[$given] = $tampered[$given] % 9 + 1;
        $this->actingAs($user)->postJson($data['urls']['finish'], ['grid' => $tampered])->assertStatus(422);

        $this->actingAs($user)->postJson($data['urls']['finish'], ['grid' => $solution])->assertOk()
            ->assertJsonPath('data.verified', true)->assertJsonPath('data.points', 25);
    }

    public function test_sudoku_generator_produces_unique_puzzles(): void
    {
        [$puzzle, $solution] = SudokuGame::generate(12345, 32);
        $this->assertTrue(SudokuGame::isValidSolution($puzzle, $solution));
        $this->assertSame(1, SudokuGame::countSolutions($puzzle));
        $this->assertLessThanOrEqual(36, count(array_filter($puzzle)));
        $this->assertSame([$puzzle, $solution], SudokuGame::generate(12345, 32));  // seeded = reproducible
    }

    public function test_memory_replay_validation(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'memory');
        $deck = $data['game']['deck'];
        $pairs = [];
        foreach ($deck as $i => $face) {
            $pairs[$face][] = $i;
        }
        $moves = array_values($pairs);

        // Too few moves / forged list are rejected outright (session closes as rejected).
        $this->age($data['session'], 60);
        $this->actingAs($user)->postJson($data['urls']['finish'], ['moves' => array_slice($moves, 0, 3)])->assertStatus(422);

        $data = $this->startGame($user, 'memory');
        $deck = $data['game']['deck'];
        $pairs = [];
        foreach ($deck as $i => $face) {
            $pairs[$face][] = $i;
        }
        $this->age($data['session'], 60);
        $this->actingAs($user)->postJson($data['urls']['finish'], ['moves' => array_values($pairs)])->assertOk()
            ->assertJsonPath('data.points', 15);
    }

    public function test_word_answers_checked_server_side(): void
    {
        $user = User::factory()->create();
        $data = $this->startGame($user, 'word');
        $words = array_column(ArcadeSession::find($data['session'])->payload['words'], 'word');
        foreach ($words as $i => $w) {
            $r = $this->actingAs($user)->postJson($data['urls']['action'], ['index' => $i, 'guess' => $i === 0 ? 'wrong' : strtolower($w)])->assertOk();
            $this->assertSame($i !== 0, $r->json('data.correct'));
        }
        $this->age($data['session'], 60);
        $this->actingAs($user)->postJson($data['urls']['finish'])->assertOk()->assertJsonPath('data.points', 15);
    }

    public function test_uno_is_server_authoritative(): void
    {
        // This test plays a whole random game through the API; a long game can exceed the
        // per-minute action throttle meant for humans, so the throttle is lifted here only.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $user = User::factory()->create();
        $data = $this->startGame($user, 'uno', ['variant' => '2']);
        $this->assertCount(7, $data['game']['hand']);
        $this->assertCount(3, $data['game']['players']);
        $this->assertCount(108, UnoGame::freshDeck());

        // Cannot claim a win while the game is running.
        $this->actingAs($user)->postJson($data['urls']['finish'])->assertStatus(422)->assertJsonPath('data.final', false);

        // Playing an unplayable card is refused.
        $hand = $data['game']['hand'];
        $bad = collect($hand)->search(fn ($c) => ! $c['playable']);
        if ($bad !== false) {
            $this->actingAs($user)->postJson($data['urls']['action'], ['action' => 'play', 'card' => $bad])->assertStatus(422);
        }

        // Play the game to the end with a simple strategy; the server runs the CPUs.
        $state = $data['game'];
        for ($guard = 0; $guard < 400 && $state['winner'] === null; $guard++) {
            $idx = collect($state['hand'])->search(fn ($c) => $c['playable']);
            $body = $idx === false ? ['action' => $state['drawn'] !== null ? 'pass' : 'draw']
                : ['action' => 'play', 'card' => $idx, 'color' => 'R', 'uno' => count($state['hand']) === 2];
            $state = $this->actingAs($user)->postJson($data['urls']['action'], $body)->assertOk()->json('data');
        }
        $this->assertNotNull($state['winner']);
        $this->age($data['session'], 120);
        $res = $this->actingAs($user)->postJson($data['urls']['finish'])->assertOk();
        $this->assertSame($state['winner'] === 0 ? 20 : 3, $res->json('data.points'));
    }

    public function test_daily_arcade_cap_applies(): void
    {
        config(['arcade.daily_points_cap' => 12]);
        $user = User::factory()->create();
        $points = [];
        for ($k = 0; $k < 2; $k++) {
            $data = $this->startGame($user, 'reaction');
            $this->age($data['session'], 40);
            $points[] = $this->actingAs($user)->postJson($data['urls']['finish'], ['times' => [200, 210, 220, 230, 240]])->json('data.points');
        }
        $this->assertSame([10, 2], $points);
    }

    public function test_ai_question_schema_validation(): void
    {
        $src = app(AiQuestionSource::class);
        $ok = ['questions' => [['q' => 'Which city is the capital of Nepal?', 'options' => ['Kathmandu', 'Pokhara', 'Butwal', 'Dharan'], 'answer' => 0, 'explain' => 'Kathmandu.']]];
        $this->assertTrue($src->valid($ok));
        $bad = $ok;
        $bad['questions'][0]['answer'] = 7;
        $this->assertFalse($src->valid($bad));
        $bad = $ok;
        $bad['questions'][0]['options'] = ['A', 'A', 'B', 'C'];
        $this->assertFalse($src->valid($bad));
        // AI is off in tests → local bank only.
        $this->assertSame([], $src->questions('quiz', 'Nepal'));
    }
}
