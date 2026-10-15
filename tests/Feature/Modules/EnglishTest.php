<?php

namespace Tests\Feature\Modules;

use App\Models\Counter;
use App\Models\EnglishPracticeSession;
use App\Models\EnglishPracticeTurn;
use App\Models\EnglishScenario;
use App\Models\Location;
use App\Models\RewardTransaction;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\English\FeedbackService;
use App\Services\English\PracticeService;
use App\Services\Queue\QueueManager;
use Database\Seeders\Modules\EnglishSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EnglishTest extends TestCase
{
    use RefreshDatabase;

    private const MISTAKES = 'i am having two brothers and my father don\'t like cricket. I am working here since 3 years. Where you work? I want a apple. Please do the needful.';

    private function scenario(array $overrides = []): EnglishScenario
    {
        return EnglishScenario::create(array_merge([
            'slug' => 'test-'.uniqid(), 'type' => 'interview', 'level' => 'beginner', 'title' => 'Test interview',
            'description' => 'Demo', 'icon' => 'bi-briefcase',
            'turns' => [
                ['prompt' => 'Introduce yourself.'], ['prompt' => 'Why this job?'], ['prompt' => 'Your strengths?'],
                ['prompt' => 'A weakness?'], ['prompt' => 'Teamwork example?'], ['prompt' => 'Five-year plan?'],
            ],
            'target_vocabulary' => ['experience', 'customer', 'teamwork'],
        ], $overrides));
    }

    private function beginPractice(User $user, ?EnglishScenario $sc = null, int $minutes = 3, string $mode = 'text'): EnglishPracticeSession
    {
        $sc ??= $this->scenario();

        return app(PracticeService::class)->start($user, $sc, $minutes, $mode, $mode === 'voice');
    }

    private function turnUrl(EnglishPracticeSession $s): string
    {
        return route('english.turn', $s);
    }

    /** Swap the AI client for a fake that is "enabled" and returns $raw. */
    private function fakeAi(?string $raw): object
    {
        $fake = new class($raw) extends AiClient
        {
            public array $prompts = [];

            public function __construct(private ?string $raw) {}

            public function enabled(): bool
            {
                return true;
            }

            public function complete(string $system, string $prompt, int $maxTokens = 400): ?string
            {
                $this->prompts[] = $prompt;

                return $this->raw;
            }
        };
        $this->app->instance(AiClient::class, $fake);

        return $fake;
    }

    // ---------------- Ownership (IDOR) ----------------

    public function test_users_cannot_access_or_change_another_users_session(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $s = $this->beginPractice($owner);

        foreach ([$other, $admin] as $intruder) {
            $this->actingAs($intruder)->get(route('english.room', $s))->assertForbidden();
            $this->actingAs($intruder)->postJson($this->turnUrl($s), ['user_text' => 'Hello there', 'input_mode' => 'text'])->assertForbidden();
            $this->actingAs($intruder)->postJson(route('english.mode', $s), ['mode' => 'text'])->assertForbidden();
            $this->actingAs($intruder)->post(route('english.complete', $s))->assertForbidden();
        }
        $this->assertSame(0, EnglishPracticeTurn::count());
        $this->assertSame('active', $s->fresh()->status);

        app(PracticeService::class)->complete($s);
        $this->actingAs($other)->get(route('english.summary', $s))->assertForbidden();
        $this->actingAs($owner)->get(route('english.summary', $s))->assertOk();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('english.home'))->assertRedirect(route('login'));
        $this->post(route('english.start'), [])->assertRedirect(route('login'));
    }

    // ---------------- Validation ----------------

    public function test_turn_submission_is_validated(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user);
        $url = $this->turnUrl($s);
        $this->actingAs($user);

        $this->postJson($url, ['user_text' => '', 'input_mode' => 'text'])->assertStatus(422)->assertJsonValidationErrors('user_text');
        $this->postJson($url, ['user_text' => '12345 !!!', 'input_mode' => 'text'])->assertStatus(422)->assertJsonValidationErrors('user_text');
        $this->postJson($url, ['user_text' => str_repeat('word ', 400), 'input_mode' => 'text'])->assertStatus(422)->assertJsonValidationErrors('user_text');
        $this->postJson($url, ['user_text' => 'Hello', 'input_mode' => 'video'])->assertStatus(422)->assertJsonValidationErrors('input_mode');
        $this->postJson($url, ['user_text' => 'Hello', 'input_mode' => 'text', 'elapsed_seconds' => 5000])->assertStatus(422)->assertJsonValidationErrors('elapsed_seconds');
        // Voice input needs the mic notice to have been accepted for this session.
        $this->postJson($url, ['user_text' => 'Hello there', 'input_mode' => 'voice'])->assertStatus(422)->assertJsonValidationErrors('input_mode');

        $this->assertSame(0, EnglishPracticeTurn::count());

        $this->postJson($url, ['user_text' => '<b>Hello</b>, I have experience with customers.', 'input_mode' => 'text', 'elapsed_seconds' => 20])
            ->assertOk()->assertJsonPath('data.answered', 1)->assertJsonPath('data.next.prompt', 'Why this job?');
        $this->assertSame('Hello, I have experience with customers.', EnglishPracticeTurn::first()->user_text); // tags stripped
    }

    public function test_start_validates_scenario_duration_and_voice_consent(): void
    {
        $user = User::factory()->create();
        $sc = $this->scenario();
        $inactive = $this->scenario(['is_active' => false]);
        $this->actingAs($user);

        $this->post(route('english.start'), ['scenario_id' => $sc->id, 'minutes' => 7, 'mode' => 'text'])->assertSessionHasErrors('minutes');
        $this->post(route('english.start'), ['scenario_id' => $inactive->id, 'minutes' => 3, 'mode' => 'text'])->assertSessionHasErrors('scenario_id');
        $this->post(route('english.start'), ['scenario_id' => $sc->id, 'minutes' => 3, 'mode' => 'voice'])->assertSessionHasErrors('consent_audio');
        $this->assertSame(0, EnglishPracticeSession::count());

        $this->post(route('english.start'), ['scenario_id' => $sc->id, 'minutes' => 5, 'mode' => 'voice', 'consent_audio' => '1'])->assertRedirect();
        $s = EnglishPracticeSession::firstOrFail();
        $this->assertSame($user->id, $s->user_id);
        $this->assertTrue($s->consent_audio);
        $this->assertSame(5, $s->plannedCount());

        // consent defaults to false in text mode, even if the client sends it.
        $this->post(route('english.start'), ['scenario_id' => $sc->id, 'minutes' => 3, 'mode' => 'text', 'consent_audio' => '1'])->assertRedirect();
        $this->assertFalse(EnglishPracticeSession::latest('id')->first()->consent_audio);
    }

    public function test_mode_switch_to_voice_requires_consent(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user);
        $this->actingAs($user);

        $this->postJson(route('english.mode', $s), ['mode' => 'voice'])->assertStatus(422);
        $this->assertFalse($s->fresh()->consent_audio);
        $this->postJson(route('english.mode', $s), ['mode' => 'voice', 'consent_audio' => true])->assertOk()->assertJsonPath('data.mode', 'voice');
        $this->assertTrue($s->fresh()->consent_audio);
        $this->postJson($this->turnUrl($s), ['user_text' => 'I have experience', 'input_mode' => 'voice', 'elapsed_seconds' => 4])->assertOk();
    }

    // ---------------- No audio, ever ----------------

    public function test_audio_fields_and_files_are_rejected_and_never_stored(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user);
        $this->actingAs($user);

        foreach (['audio', 'audio_data', 'recording', 'blob'] as $field) {
            $this->postJson($this->turnUrl($s), ['user_text' => 'Hello there', 'input_mode' => 'text', $field => 'UklGRiQAAABXQVZF'])
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $file = UploadedFile::fake()->create('answer.webm', 20, 'audio/webm');
        $this->post($this->turnUrl($s), ['user_text' => 'Hello there', 'input_mode' => 'text', 'clip' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->post(route('english.start'), ['scenario_id' => $s->english_scenario_id, 'minutes' => 3, 'mode' => 'text', 'audio' => 'x'])
            ->assertSessionHasErrors('audio');
        $this->assertSame(0, EnglishPracticeTurn::count());

        // Schema: no column can hold audio (consent_audio is only a boolean acknowledgement).
        foreach (['english_practice_sessions', 'english_practice_turns', 'english_scenarios'] as $table) {
            foreach (Schema::getColumnListing($table) as $col) {
                $this->assertFalse(str_contains($col, 'audio') && $col !== 'consent_audio', "$table.$col looks like an audio column");
                $this->assertNotContains($col, ['recording', 'blob', 'voice_file']);
            }
        }
        $this->assertSame(['english_practice_session_id', 'turn_index', 'prompt', 'user_text', 'input_mode', 'elapsed_seconds', 'feedback'], array_values(array_diff(Schema::getColumnListing('english_practice_turns'), ['id', 'created_at'])));
    }

    // ---------------- Speech engines (on-device Vosk / browser Web Speech) ----------------

    public function test_voice_turns_record_the_speech_engine_as_metadata_only(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user, null, 5, 'voice');
        $this->actingAs($user);

        $this->postJson($this->turnUrl($s), ['user_text' => 'I would like to renew my driving licence', 'input_mode' => 'voice', 'elapsed_seconds' => 3, 'stt_engine' => 'ondevice'])
            ->assertOk()->assertJsonPath('data.turn.input_mode', 'voice')->assertJsonPath('data.turn.stt_engine', 'ondevice');
        $this->postJson($this->turnUrl($s), ['user_text' => 'Because I like helping customers', 'input_mode' => 'voice', 'stt_engine' => 'browser'])
            ->assertOk()->assertJsonPath('data.turn.stt_engine', 'browser');
        // Engine is optional (older clients) and the transcript text is the only content stored.
        $this->postJson($this->turnUrl($s), ['user_text' => 'My strength is teamwork', 'input_mode' => 'voice'])
            ->assertOk()->assertJsonPath('data.turn.stt_engine', null);

        $turns = EnglishPracticeTurn::orderBy('turn_index')->get();
        $this->assertSame(['ondevice', 'browser', null], $turns->map(fn ($t) => $t->feedback['stt_engine'] ?? null)->all());
        $this->assertSame('I would like to renew my driving licence', $turns[0]->user_text);

        // The room re-renders past turns with their engine label.
        $this->get(route('english.room', $s))->assertOk()->assertSee('"stt_engine":"ondevice"', false);
    }

    public function test_speech_engine_field_is_validated_and_never_accepts_audio(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user, null, 5, 'voice');
        $url = $this->turnUrl($s);
        $this->actingAs($user);

        $this->postJson($url, ['user_text' => 'Hello there', 'input_mode' => 'voice', 'stt_engine' => 'google-cloud'])
            ->assertStatus(422)->assertJsonValidationErrors('stt_engine');
        // A typed answer cannot claim a speech engine.
        $this->postJson($url, ['user_text' => 'Hello there', 'input_mode' => 'text', 'stt_engine' => 'ondevice'])
            ->assertStatus(422)->assertJsonValidationErrors('stt_engine');
        // Raw samples / WAV data are refused even alongside a valid engine.
        foreach (['pcm', 'samples', 'wav', 'audio'] as $field) {
            $this->postJson($url, ['user_text' => 'Hello there', 'input_mode' => 'voice', 'stt_engine' => 'ondevice', $field => [0.1, -0.2, 0.3]])
                ->assertStatus(422)->assertJsonValidationErrors($field);
        }
        $file = UploadedFile::fake()->create('speech.wav', 20, 'audio/wav');
        $this->post($url, ['user_text' => 'Hello there', 'input_mode' => 'voice', 'stt_engine' => 'ondevice', 'clip' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422);
        $this->assertSame(0, EnglishPracticeTurn::count());
    }

    public function test_room_boots_offline_speech_assets_from_this_app(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user, null, 3, 'voice');

        $res = $this->actingAs($user)->get(route('english.room', $s))->assertOk();
        $res->assertSee('js/modules/english-speech.js', false)
            ->assertSee('vendor\/vosk\/vosk.js', false)
            ->assertSee('vendor\/vosk\/models\/vosk-model-small-en-us-0.15.tar.gz', false)
            ->assertSee('On-device (offline)')
            ->assertSee('audio isn’t stored or uploaded', false);
        // No third-party speech or CDN URLs are referenced by the room.
        $res->assertDontSee('unpkg.com', false)->assertDontSee('jsdelivr', false)->assertDontSee('alphacephei.com', false);
        $this->assertFileExists(public_path('vendor/vosk/vosk.js'));
        $this->assertFileExists(public_path('vendor/vosk/models/vosk-model-small-en-us-0.15.tar.gz'));
    }

    // ---------------- Feedback: rule-based fallback ----------------

    public function test_fallback_feedback_detects_seeded_mistakes(): void
    {
        config(['ai.provider' => 'none']);
        $fb = app(FeedbackService::class)->analyse(self::MISTAKES, ['type' => 'interview', 'input_mode' => 'text']);
        $rules = array_column($fb['corrections'], 'rule');

        foreach (['capital_i', 'stative_having', 'subject_verb', 'since_for', 'question_aux', 'articles'] as $expected) {
            $this->assertContains($expected, $rules, "Expected the '$expected' check to fire");
        }
        $bySuggestion = array_column($fb['corrections'], 'suggestion', 'original');
        $this->assertSame('for 3 years', $bySuggestion['since 3 years']);
        $this->assertSame('an apple', $bySuggestion['a apple']);
        $this->assertSame("my father doesn't", $bySuggestion["my father don't"]);
        $this->assertSame('Where do you work?', $bySuggestion['Where you work']);
        foreach ($fb['corrections'] as $c) {
            $this->assertNotEmpty($c['explanation']); // every mistake is explained
        }
        $this->assertSame('rules', $fb['source']);
        $this->assertNotEmpty($fb['summary']);
        $this->assertNotEmpty($fb['follow_up']);

        $more = app(FeedbackService::class)->grammarIssues("um I don't have nothing. He go to office. I didn't went there. Myself Ram. I am agree.");
        $rules = array_column($more, 'rule');
        foreach (['double_negative', 'subject_verb', 'tense_after_aux', 'needful', 'agree'] as $expected) {
            $this->assertContains($expected, $rules);
        }

        // No false positives on correct English.
        $this->assertSame([], app(FeedbackService::class)->grammarIssues('I have two brothers. Does he like tea? Let it go. I am having lunch now. An hour is enough. A university is near.'));
    }

    public function test_metrics_are_heuristics_fillers_pace_variety_and_target_words(): void
    {
        $f = app(FeedbackService::class);
        $m = $f->analyse('Um, I have experience with customer service. Uh, basically I like teamwork, like, a lot.', [
            'type' => 'interview', 'target_vocabulary' => ['experience', 'customer', 'teamwork', 'deadline'], 'input_mode' => 'voice', 'elapsed_seconds' => 8,
        ])['metrics'];

        $this->assertSame(15, $m['word_count']);
        $this->assertGreaterThanOrEqual(4, $m['filler_count']);
        $this->assertArrayHasKey('basically', $m['fillers']);
        $this->assertSame(113, $m['speaking_pace_wpm']); // 15 words in 8 s
        $this->assertSame(['experience', 'customer', 'teamwork'], $m['target_vocab_used']);
        $this->assertSame(['deadline'], $m['target_vocab_missing']);
        $this->assertNotNull($m['type_token_ratio']);

        // Typed answers never report a "speaking pace".
        $typed = $f->analyse('I have experience with customer service and teamwork.', ['input_mode' => 'text', 'elapsed_seconds' => 8]);
        $this->assertNull($typed['metrics']['speaking_pace_wpm']);
    }

    public function test_pronunciation_drill_compares_transcript_and_is_not_a_score(): void
    {
        $sc = $this->scenario(['type' => 'pronunciation', 'turns' => [
            ['prompt' => 'Say: “I think this is the right place.”', 'target' => 'I think this is the right place.'],
            ['prompt' => 'Say: “Thank you”', 'target' => 'Thank you'],
        ]]);
        $user = User::factory()->create();
        $s = $this->beginPractice($user, $sc, 3, 'voice');

        $res = $this->actingAs($user)->postJson($this->turnUrl($s), ['user_text' => 'I sink this is the right place', 'input_mode' => 'voice', 'elapsed_seconds' => 3])->assertOk();
        $p = $res->json('data.turn.feedback.pronunciation');
        $this->assertSame(6, $p['matched_words']);
        $this->assertSame(7, $p['total_words']);
        $this->assertSame(['think'], $p['missed']);
        $this->assertStringContainsString('NOT a pronunciation score', $p['disclaimer']);
        $this->assertStringContainsString('th', implode(' ', $p['tips']));
        $this->assertSame('I sink this is the right place', $p['heard']);
    }

    // ---------------- AI path ----------------

    public function test_ai_disabled_path_works_end_to_end(): void
    {
        config(['ai.provider' => 'none']);
        $user = User::factory()->create();
        $s = $this->beginPractice($user);

        $res = $this->actingAs($user)->postJson($this->turnUrl($s), ['user_text' => self::MISTAKES, 'input_mode' => 'text'])->assertOk();
        $this->assertSame('rules', $res->json('data.turn.feedback.source'));
        $this->assertNotEmpty($res->json('data.turn.feedback.corrections'));
    }

    public function test_ai_enabled_valid_payload_is_used_and_personal_data_is_redacted(): void
    {
        $fake = $this->fakeAi(json_encode([
            'summary' => 'Great start! Your answer is clear.',
            'corrections' => [['original' => 'I am working here since 3 years', 'suggestion' => 'I have been working here for 3 years', 'explanation' => 'Use present perfect continuous with “for”.']],
            'vocabulary' => [['suggestion' => 'dedicated', 'note' => 'Shows commitment.']],
            'fluency' => ['Nice pace.'],
            'follow_up' => 'What do you enjoy most about your work?',
        ]));
        $user = User::factory()->create();
        $s = $this->beginPractice($user);

        $res = $this->actingAs($user)->postJson($this->turnUrl($s), ['user_text' => 'I am working here since 3 years. Email me at ram@example.com or 9841234567.', 'input_mode' => 'text'])->assertOk();
        $fb = $res->json('data.turn.feedback');
        $this->assertSame('ai', $fb['source']);
        $this->assertSame('Great start! Your answer is clear.', $fb['summary']);
        $this->assertSame('ai', $fb['corrections'][0]['rule']);
        $this->assertSame('What do you enjoy most about your work?', $fb['follow_up']);
        $this->assertArrayHasKey('word_count', $fb['metrics']); // metrics always computed locally

        $this->assertCount(1, $fake->prompts);
        $this->assertStringNotContainsString('ram@example.com', $fake->prompts[0]);
        $this->assertStringNotContainsString('9841234567', $fake->prompts[0]);
        $this->assertStringContainsString('[email]', $fake->prompts[0]);
    }

    public function test_ai_enabled_invalid_payload_falls_back_to_rules(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user);
        $this->actingAs($user);

        $invalidPayloads = [
            'not json at all',
            json_encode(['summary' => 'Hi']),                                                     // missing keys
            json_encode(['summary' => 'Hi', 'corrections' => 'none', 'vocabulary' => [], 'fluency' => [], 'follow_up' => 'Q?']), // wrong type
            json_encode(['summary' => str_repeat('x', 700), 'corrections' => [], 'vocabulary' => [], 'fluency' => [], 'follow_up' => 'Q?']), // too long
            json_encode(['summary' => 'Hi', 'corrections' => [['original' => 'a']], 'vocabulary' => [], 'fluency' => [], 'follow_up' => 'Q?']), // bad item
        ];
        foreach ($invalidPayloads as $i => $raw) {
            $this->assertFalse(FeedbackService::validAiPayload(json_decode($raw, true)), "payload $i should be invalid");
        }

        $this->fakeAi($invalidPayloads[2]);
        $res = $this->postJson($this->turnUrl($s), ['user_text' => self::MISTAKES, 'input_mode' => 'text'])->assertOk();
        $this->assertSame('rules', $res->json('data.turn.feedback.source'));
        $this->assertContains('since_for', array_column($res->json('data.turn.feedback.corrections'), 'rule'));

        $this->fakeAi(null); // provider down / timeout
        $res = $this->postJson($this->turnUrl($s), ['user_text' => 'He go to the bank every day.', 'input_mode' => 'text'])->assertOk();
        $this->assertSame('rules', $res->json('data.turn.feedback.source'));
    }

    // ---------------- Session flow & progress ----------------

    public function test_prompts_are_server_controlled_and_capped_by_duration(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user, null, 3);
        $this->assertSame(3, $s->plannedCount());
        $this->actingAs($user);

        foreach (['Introduce yourself.', 'Why this job?', 'Your strengths?'] as $i => $expected) {
            $this->postJson($this->turnUrl($s), ['user_text' => 'My answer number '.$i, 'input_mode' => 'text', 'prompt' => 'HACKED'])->assertOk();
            $this->assertSame($expected, EnglishPracticeTurn::where('turn_index', $i)->value('prompt'));
        }
        $this->postJson($this->turnUrl($s), ['user_text' => 'One more', 'input_mode' => 'text'])->assertStatus(409);
        $this->assertSame(3, EnglishPracticeTurn::count());

        $this->assertSame(8, $this->beginPractice($user, $this->scenario(['turns' => array_fill(0, 10, ['prompt' => 'Q'])]), 10)->plannedCount());
    }

    public function test_session_completion_records_progress_and_awards_points_once(): void
    {
        $user = User::factory()->create();
        $s = $this->beginPractice($user);
        $this->actingAs($user);
        $this->postJson($this->turnUrl($s), ['user_text' => 'I have experience with customer service at a bank.', 'input_mode' => 'text'])->assertOk();
        $this->postJson($this->turnUrl($s), ['user_text' => 'i like teamwork since 2 years', 'input_mode' => 'text'])->assertOk();

        $this->post(route('english.complete', $s))->assertRedirect(route('english.summary', $s));
        $this->post(route('english.complete', $s))->assertRedirect(route('english.summary', $s)); // idempotent

        $s->refresh();
        $this->assertSame('completed', $s->status);
        $this->assertNotNull($s->ended_at);
        $this->assertTrue($s->scores['heuristic']);
        $this->assertSame(2, $s->scores['turns_answered']);
        $this->assertSame(3, $s->scores['target_vocabulary_used']);
        $this->assertGreaterThan(0, $s->scores['grammar_points_flagged']);
        $this->assertArrayNotHasKey('pronunciation_score', $s->scores);
        $this->assertGreaterThan(0, $s->summary['minutes_practised']);
        $this->assertNotEmpty($s->summary['focus_next']);
        $this->assertEqualsCanonicalizing(['experience', 'customer', 'teamwork'], $s->summary['vocabulary_used']);
        $this->assertSame([], $s->summary['vocabulary_to_try']);

        $this->assertSame(1, RewardTransaction::where('user_id', $user->id)->where('source', 'english')->count());
        $this->assertSame(15, $s->summary['points_awarded']);

        $progress = app(PracticeService::class)->progress($user);
        $this->assertSame(1, $progress['sessions']);
        $this->assertSame(1, $progress['streak']);
        $this->assertSame(2, $progress['answers']);
        $this->assertTrue($progress['practised_today']);

        // Completed sessions accept no more turns and redirect the room to the summary.
        $this->postJson($this->turnUrl($s), ['user_text' => 'late answer', 'input_mode' => 'text'])->assertStatus(409);
        $this->get(route('english.room', $s))->assertRedirect(route('english.summary', $s));
        $this->get(route('english.summary', $s))->assertOk()->assertSee('not grades', false);
        $this->get(route('english.home'))->assertOk()->assertSee('Test interview');
    }

    public function test_streak_counts_consecutive_days(): void
    {
        $user = User::factory()->create();
        $sc = $this->scenario();
        foreach ([0, 1, 2, 4] as $daysAgo) {
            $s = $this->beginPractice($user, $sc);
            $s->forceFill(['status' => 'completed', 'ended_at' => now()->subDays($daysAgo), 'summary' => ['minutes_practised' => 3]])->save();
        }
        $p = app(PracticeService::class)->progress($user);
        $this->assertSame(3, $p['streak']);
        $this->assertSame(4, $p['sessions']);
        $this->assertEquals(12, $p['minutes']);
    }

    public function test_session_length_adapts_to_active_ticket_wait(): void
    {
        $user = User::factory()->create();
        $svc = app(PracticeService::class);
        $none = $svc->suggestion($user);
        $this->assertNull($none['ticket']);
        $this->assertSame(5, $none['suggested']);

        $loc = Location::create(['name' => 'Test Office', 'area' => 'Kathmandu', 'type' => 'Government', 'icon' => 'bi-building', 'lat' => 27.7, 'lng' => 85.3]);
        $service = $loc->services()->create(['name' => 'Licence', 'prefix' => 'D', 'counter' => 1, 'avg_minutes' => 4, 'current_number' => 10, 'last_number' => 20, 'documents' => []]);
        Counter::create(['location_id' => $loc->id, 'service_id' => $service->id, 'number' => 1, 'label' => 'Counter 1']);
        $ticket = app(QueueManager::class)->join($service, $user, []);

        $s = $svc->suggestion($user);
        $this->assertSame($ticket->id, $s['ticket']->id);
        $low = $ticket->fresh()->stateArray()['wait_low'];
        $this->assertSame($low, $s['wait_low']);
        $this->assertSame($low >= 12 ? 10 : ($low >= 6 ? 5 : 3), $s['suggested']);

        // The session is linked to the ticket, but practice never changes queue order.
        $before = $ticket->fresh()->only(['number', 'status']);
        $session = $this->beginPractice($user);
        $this->assertSame($ticket->id, $session->ticket_id);
        $this->assertSame($before, $ticket->fresh()->only(['number', 'status']));

        $this->actingAs($user)->get(route('english.scenarios'))->assertOk()->assertSee($ticket->token());
        $html = view('english.partials.ticket-widget', ['ticket' => $ticket->fresh()])->render();
        $this->assertStringContainsString('Practise English for '.$s['suggested'].' minutes', $html);
        $this->assertSame('', trim(view('english.partials.ticket-widget', ['ticket' => null])->render()));
    }

    public function test_pages_render_for_owner_and_seeder_is_idempotent(): void
    {
        $this->seed(EnglishSeeder::class);
        $this->seed(EnglishSeeder::class);
        $this->assertGreaterThanOrEqual(12, EnglishScenario::count());
        $this->assertSame(EnglishScenario::count(), EnglishScenario::distinct()->count('slug'));
        $this->assertGreaterThanOrEqual(3, EnglishScenario::where('type', 'pronunciation')->count());

        $user = User::factory()->create();
        $this->actingAs($user);
        $this->get(route('english.home'))->assertOk()->assertSee('No sessions yet');
        $this->get(route('english.scenarios', ['minutes' => 10, 'type' => 'interview']))->assertOk()->assertSee('Job interview: bank officer')->assertSee('never recorded or stored', false);
        $s = $this->beginPractice($user, EnglishScenario::where('slug', 'cafe-ordering')->first());
        $this->get(route('english.room', $s))->assertOk()->assertSee('Ordering at a café')->assertSee('Read prompts aloud');
    }
}
