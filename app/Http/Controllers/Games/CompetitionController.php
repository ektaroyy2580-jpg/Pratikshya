<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Http\Requests\Games\AnswerRequest;
use App\Http\Requests\Games\InviteRequest;
use App\Http\Requests\Games\StoreCompetitionRequest;
use App\Models\Competition;
use App\Services\Games\CompetitionService;
use App\Services\Games\GameException;
use Closure;
use Illuminate\Http\Request;

/** Multiplayer Quick Brain Battle. Every state change is validated by CompetitionService. */
class CompetitionController extends Controller
{
    public function __construct(private CompetitionService $games)
    {
    }

    public function store(StoreCompetitionRequest $request)
    {
        return $this->attempt($request, function () use ($request) {
            $c = $this->games->create($request->user(), $request->validated());

            return [$c, 'Battle created. Invite people waiting with you, or wait for someone to join.'];
        }, fn ($c) => route('games.competitions.show', $c));
    }

    public function show(Request $request, Competition $competition)
    {
        $this->authorize('view', $competition);
        $this->games->sync($competition);
        if ($competition->status === 'completed') {
            return redirect()->route('games.competitions.results', $competition);
        }
        $competition->load('service.location');

        return view('games.room', [
            'competition' => $competition,
            'state' => $this->games->stateFor($competition, $request->user()),
        ]);
    }

    /** Polled every ~2.5s by the room. Also where called-to-counter checks and lifecycle transitions happen. */
    public function state(Request $request, Competition $competition)
    {
        $this->authorize('view', $competition);
        $this->games->sync($competition);

        return response()->json(['message' => 'Competition state retrieved', 'data' => $this->games->stateFor($competition, $request->user())]);
    }

    public function join(Request $request, Competition $competition)
    {
        return $this->attempt($request, function () use ($request, $competition) {
            $this->games->join($competition, $request->user());

            return [$competition, 'You joined the battle. Tap "I\'m ready" when you are set.'];
        }, fn ($c) => route('games.competitions.show', $c));
    }

    public function ready(Request $request, Competition $competition)
    {
        $this->authorize('play', $competition);
        $ready = $request->boolean('ready', true);

        return $this->attempt($request, function () use ($request, $competition, $ready) {
            $this->games->setReady($competition, $request->user(), $ready);

            return [$competition, $ready ? 'You are ready.' : 'Marked as not ready.'];
        }, fn ($c) => route('games.competitions.show', $c));
    }

    public function start(Request $request, Competition $competition)
    {
        $this->authorize('host', $competition);

        return $this->attempt($request, function () use ($request, $competition) {
            $this->games->start($competition, $request->user());

            return [$competition, 'Starting — get ready!'];
        }, fn ($c) => route('games.competitions.show', $c));
    }

    public function leave(Request $request, Competition $competition)
    {
        $this->authorize('play', $competition);

        return $this->attempt($request, function () use ($request, $competition) {
            $this->games->leave($competition, $request->user());

            return [$competition, 'You left the battle. Your queue position is unchanged.'];
        }, fn () => route('games.hub'));
    }

    public function cancel(Request $request, Competition $competition)
    {
        $this->authorize('host', $competition);

        return $this->attempt($request, function () use ($request, $competition) {
            $this->games->cancel($competition, $request->user());

            return [$competition, 'Battle cancelled.'];
        }, fn () => route('games.hub'));
    }

    public function invite(InviteRequest $request, Competition $competition)
    {
        return $this->attempt($request, function () use ($request, $competition) {
            $inv = $this->games->invite($competition, $request->user(), (int) $request->validated('ticket_id'));

            return [$competition, 'Invitation sent to '.$inv->ticket->token().'.'];
        }, fn ($c) => route('games.competitions.show', $c));
    }

    public function answer(AnswerRequest $request, Competition $competition)
    {
        try {
            $result = $this->games->answer($competition, $request->user(), (int) $request->validated('question_id'), (int) $request->validated('choice'));
        } catch (GameException $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => null], $e->status);
        }

        return response()->json([
            'message' => $result['correct'] ? 'Correct!' : 'Not quite.',
            'data' => $result,
        ]);
    }

    public function results(Request $request, Competition $competition)
    {
        $this->authorize('view', $competition);
        $this->games->sync($competition);
        if (! $competition->isFinished()) {
            return redirect()->route('games.competitions.show', $competition);
        }
        $competition->load(['service.location', 'questions.question', 'players' => fn ($q) => $q->orderByRaw('rank IS NULL')->orderBy('rank')->orderBy('id')]);
        $me = $competition->players->firstWhere('user_id', $request->user()->id);
        $myAnswers = $competition->answers()->where('user_id', $request->user()->id)->get()->keyBy('competition_question_id');

        return view('games.results', compact('competition', 'me', 'myAnswers'));
    }

    /**
     * Run an action and answer as JSON (`{message, data}`) for fetch() callers or redirect with a flash
     * for normal form posts. GameException messages are safe to show.
     */
    private function attempt(Request $request, Closure $action, Closure $redirectTo)
    {
        try {
            [$competition, $message] = $action();
        } catch (GameException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'data' => null], $e->status)
                : back()->withErrors(['queue' => $e->getMessage()]);
        }

        $url = $redirectTo($competition);

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'data' => ['id' => $competition->id, 'redirect' => $url]])
            : redirect($url)->with('ok', $message);
    }
}
