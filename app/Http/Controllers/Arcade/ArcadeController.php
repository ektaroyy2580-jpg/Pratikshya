<?php

namespace App\Http\Controllers\Arcade;

use App\Http\Controllers\Controller;
use App\Http\Requests\Arcade\ArcadeActionRequest;
use App\Http\Requests\Arcade\StartArcadeRequest;
use App\Models\ArcadeSession;
use App\Services\Arcade\ArcadeManager;
use App\Services\Arcade\ArcadeRejected;
use App\Services\Rewards\RewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class ArcadeController extends Controller
{
    /** Page template per game type: the three question games share one page. */
    private const VIEWS = ['memory' => 'memory', 'word' => 'word', 'quiz' => 'quiz', 'cyber' => 'quiz', 'logic' => 'quiz', 'reaction' => 'reaction', 'sudoku' => 'sudoku', 'uno' => 'uno'];

    public function __construct(private ArcadeManager $arcade) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $games = collect(config('games.arcade', []))->filter(fn ($g) => $this->arcade->has($g['key']))
            ->map(fn ($g) => $g + ['best' => $this->arcade->best($user, $g['key']), 'played' => $this->arcade->playedCount($user, $g['key'])]);

        return view('arcade.index', [
            'games' => $games,
            'hubUrl' => Route::has('games.hub') ? route('games.hub') : null,
            'earnedToday' => $this->arcade->earnedToday($user),
            'cap' => (int) config('arcade.daily_points_cap'),
        ]);
    }

    public function play(Request $request, string $game, RewardService $rewards)
    {
        abort_unless($this->arcade->has($game), 404);
        $user = $request->user();
        $ticket = $user->tickets()->with('service')->whereIn('status', ['waiting', 'called', 'serving'])->latest()->first();

        return view('arcade.games.'.self::VIEWS[$game], [
            'game' => $this->arcade->meta($game),
            'gameKey' => $game,
            'variants' => $this->arcade->game($game)->variants(),
            'best' => $this->arcade->best($user, $game),
            'played' => $this->arcade->playedCount($user, $game),
            'earnedToday' => $this->arcade->earnedToday($user),
            'cap' => (int) config('arcade.daily_points_cap'),
            'balance' => $rewards->balance($user),
            'ticket' => $ticket,
            'hubUrl' => Route::has('games.hub') ? route('games.hub') : route('arcade.index'),
        ]);
    }

    public function start(StartArcadeRequest $request, string $game): JsonResponse
    {
        [$session, $client] = $this->arcade->start($request->user(), $game, $request->validated('variant'));

        return response()->json(['message' => 'Game started.', 'data' => [
            'session' => $session->id,
            'variant' => $session->variant,
            'game' => $client,
            'urls' => [
                'action' => route('arcade.action', $session),
                'finish' => route('arcade.finish', $session),
            ],
        ]], 201);
    }

    public function action(ArcadeActionRequest $request, ArcadeSession $arcadeSession): JsonResponse
    {
        try {
            $data = $this->arcade->interact($arcadeSession, $request->validated());
        } catch (ArcadeRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => null], $e->status);
        }

        return response()->json(['message' => 'ok', 'data' => $data]);
    }

    public function finish(ArcadeActionRequest $request, ArcadeSession $arcadeSession): JsonResponse
    {
        try {
            $data = $this->arcade->finish($arcadeSession, $request->user(), $request->validated());
        } catch (ArcadeRejected $e) {
            return response()->json(['message' => $e->getMessage(), 'data' => ['final' => $e->final]], $e->status);
        }

        return response()->json(['message' => $data['points'] > 0 ? 'Result verified. +'.$data['points'].' WaitPoints.' : 'Result saved.', 'data' => $data]);
    }
}
