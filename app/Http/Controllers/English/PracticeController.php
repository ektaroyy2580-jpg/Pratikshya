<?php

namespace App\Http\Controllers\English;

use App\Http\Controllers\Controller;
use App\Http\Requests\English\StartSessionRequest;
use App\Http\Requests\English\SubmitTurnRequest;
use App\Models\EnglishPracticeSession;
use App\Models\EnglishScenario;
use App\Services\Ai\AiClient;
use App\Services\English\PracticeException;
use App\Services\English\PracticeService;
use Illuminate\Http\Request;

class PracticeController extends Controller
{
    public function __construct(private PracticeService $practice) {}

    public function home(Request $request)
    {
        $user = $request->user();
        $suggestion = $this->practice->suggestion($user);
        $active = EnglishPracticeSession::where('user_id', $user->id)->where('status', 'active')
            ->with('scenario')->withCount('turns')->latest('id')->first();
        $doneIds = EnglishPracticeSession::where('user_id', $user->id)->where('status', 'completed')->pluck('english_scenario_id')->unique();

        // One scenario per type (not-yet-practised first) so recommendations stay varied.
        $recommended = EnglishScenario::active()->orderBy('sort')->get()
            ->sortBy(fn ($s) => [$doneIds->contains($s->id) ? 1 : 0, $s->sort])
            ->unique('type')->take(3)->values();

        return view('english.home', [
            'progress' => $this->practice->progress($user),
            'suggestion' => $suggestion,
            'active' => $active,
            'recommended' => $recommended,
            'aiLabel' => app(AiClient::class)->enabled() ? 'AI tutor + local checks' : 'Local rule-based coach',
        ]);
    }

    public function scenarios(Request $request)
    {
        $suggestion = $this->practice->suggestion($request->user());
        $minutes = (int) $request->query('minutes', $suggestion['suggested']);
        if (! in_array($minutes, EnglishPracticeSession::DURATIONS, true)) {
            $minutes = $suggestion['suggested'];
        }
        $type = $request->query('type');

        return view('english.scenarios', [
            'scenarios' => EnglishScenario::active()->orderBy('sort')->get(),
            'suggestion' => $suggestion,
            'minutes' => $minutes,
            'type' => array_key_exists((string) $type, EnglishScenario::TYPES) ? $type : 'all',
            'aiEnabled' => app(AiClient::class)->enabled(),
        ]);
    }

    public function start(StartSessionRequest $request)
    {
        $data = $request->validated();
        $scenario = EnglishScenario::active()->findOrFail($data['scenario_id']);
        $session = $this->practice->start($request->user(), $scenario, (int) $data['minutes'], $data['mode'], (bool) ($data['consent_audio'] ?? false));

        return redirect()->route('english.room', $session);
    }

    public function room(Request $request, EnglishPracticeSession $practice)
    {
        $this->authorize('view', $practice);
        if (! $practice->isActive()) {
            return redirect()->route('english.summary', $practice);
        }
        $practice->load('scenario', 'turns');
        $next = $practice->nextPrompt($practice->turns->count());

        return view('english.room', [
            'session' => $practice,
            'scenario' => $practice->scenario,
            'next' => $next,
            'aiEnabled' => app(AiClient::class)->enabled(),
            'boot' => [
                'turns' => $practice->turns->map(fn ($t) => $this->turnJson($t))->values(),
                'next' => $next ? $this->promptJson($next, $practice->turns->count()) : null,
                'answered' => $practice->turns->count(),
                'planned' => $practice->plannedCount(),
                'mode' => $practice->mode,
                'consent' => (bool) $practice->consent_audio,
                'started_at' => $practice->started_at?->toIso8601String(),
                'target_minutes' => $practice->duration_target_minutes,
                'type' => $practice->scenario->type,
                'vocab' => $practice->scenario->target_vocabulary ?? [],
                'urls' => [
                    'turn' => route('english.turn', $practice),
                    'mode' => route('english.mode', $practice),
                    'complete' => route('english.complete', $practice),
                ],
                // Offline speech recognition assets (served from this app; loaded only in voice mode).
                'speech' => [
                    'script' => asset('vendor/vosk/vosk.js'),
                    'model' => asset('vendor/vosk/models/'.config('english.speech_model', 'vosk-model-small-en-us-0.15').'.tar.gz'),
                    'model_bytes' => $this->speechModelBytes(),
                ],
            ],
        ]);
    }

    public function turn(SubmitTurnRequest $request, EnglishPracticeSession $practice)
    {
        if (! $practice->isActive()) {
            return response()->json(['message' => 'This session is already finished.', 'data' => null], 409);
        }
        $data = $request->validated();
        try {
            [$turn, $next] = $this->practice->submitTurn($practice, $data['user_text'], $data['input_mode'], isset($data['elapsed_seconds']) ? (int) $data['elapsed_seconds'] : null, $data['stt_engine'] ?? null);
        } catch (PracticeException $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => null], 409);
        }
        $answered = $turn->turn_index + 1;

        return response()->json([
            'message' => 'Feedback ready.',
            'data' => [
                'turn' => $this->turnJson($turn),
                'next' => $next ? $this->promptJson($next, $answered) : null,
                'answered' => $answered,
                'planned' => $practice->plannedCount(),
            ],
        ]);
    }

    /** Switch between text and voice. Voice requires acknowledging the microphone notice. */
    public function mode(Request $request, EnglishPracticeSession $practice)
    {
        $this->authorize('update', $practice);
        $data = $request->validate([
            'mode' => ['required', 'in:text,voice'],
            'consent_audio' => ['nullable', 'boolean', 'accepted_if:mode,voice'],
            'audio' => ['prohibited'],
        ], ['consent_audio.accepted_if' => 'Please confirm the microphone notice to use voice mode.']);

        $practice->mode = $data['mode'];
        if ($data['mode'] === 'voice') {
            $practice->consent_audio = true;
        }
        $practice->save();

        return response()->json(['message' => $data['mode'] === 'voice' ? 'Microphone mode on.' : 'Text mode on.', 'data' => ['mode' => $practice->mode, 'consent' => (bool) $practice->consent_audio]]);
    }

    public function complete(Request $request, EnglishPracticeSession $practice)
    {
        $this->authorize('update', $practice);
        $practice = $this->practice->complete($practice);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Session complete.', 'data' => ['redirect' => route('english.summary', $practice)]]);
        }

        return redirect()->route('english.summary', $practice)->with('ok', 'Session complete — here is your summary.');
    }

    public function summary(Request $request, EnglishPracticeSession $practice)
    {
        $this->authorize('view', $practice);
        if ($practice->isActive()) {
            return redirect()->route('english.room', $practice);
        }
        $practice->load('scenario', 'turns');

        return view('english.summary', [
            'session' => $practice,
            'scenario' => $practice->scenario,
            'summary' => $practice->summary ?? [],
            'scores' => $practice->scores ?? [],
            'progress' => $this->practice->progress($request->user()),
        ]);
    }

    private function speechModelBytes(): int
    {
        $path = public_path('vendor/vosk/models/'.config('english.speech_model', 'vosk-model-small-en-us-0.15').'.tar.gz');

        return is_file($path) ? (int) filesize($path) : 0;
    }

    private function promptJson(array $p, int $index): array
    {
        return [
            'index' => $index,
            'prompt' => (string) ($p['prompt'] ?? ''),
            'hint' => $p['hint'] ?? null,
            'target' => $p['target'] ?? null,
        ];
    }

    private function turnJson($t): array
    {
        return [
            'id' => $t->id,
            'index' => $t->turn_index,
            'prompt' => $t->prompt,
            'user_text' => $t->user_text,
            'input_mode' => $t->input_mode,
            'stt_engine' => $t->input_mode === 'voice' ? ($t->feedback['stt_engine'] ?? null) : null,
            'feedback' => $t->feedback,
        ];
    }
}
