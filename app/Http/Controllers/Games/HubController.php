<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\CompetitionInvitation;
use App\Models\GameQuestion;
use App\Models\Ticket;
use App\Services\Games\CompetitionService;
use App\Services\Games\LeaderboardService;
use App\Services\Rewards\RewardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class HubController extends Controller
{
    public function __construct(private CompetitionService $games, private LeaderboardService $boards)
    {
    }

    public function hub(Request $request, RewardService $rewards)
    {
        $user = $request->user();
        $this->games->expireStale();

        $ticket = Ticket::with('service.location', 'counter')->where('user_id', $user->id)
            ->whereIn('status', Ticket::ACTIVE)->latest('id')->first();
        $ticketState = $ticket?->stateArray();

        $seat = $this->games->activeSeat($user);
        if ($seat) {
            $this->games->sync($seat->competition);
            $seat = $this->games->activeSeat($user); // may have changed (e.g. finished)
        }

        $open = collect();
        if ($ticket && $ticket->status === 'waiting') {
            $open = $this->games->openIn($ticket->service_id, 6)
                ->reject(fn ($c) => $seat && $c->id === $seat->competition_id);
        }

        $invitations = CompetitionInvitation::with(['competition.service', 'competition.players' => fn ($q) => $q->whereIn('status', ['joined', 'ready'])])
            ->where('invitee_id', $user->id)->where('status', 'pending')
            ->whereHas('competition', fn ($q) => $q->whereIn('status', ['waiting_for_players', 'ready']))
            ->latest('id')->get();

        // Solo games owned by the arcade module. Only list entries whose route really exists.
        $arcade = collect(config('games.arcade', []))
            ->filter(fn ($g) => is_array($g) && ! empty($g['route']) && Route::has($g['route']))
            ->values();

        return view('games.hub', [
            'ticket' => $ticket,
            'ticketState' => $ticketState,
            'seat' => $seat,
            'open' => $open,
            'invitations' => $invitations,
            'balance' => $rewards->balance($user),
            'weekly' => $this->boards->standings('weekly', $user, 5),
            'arcade' => $arcade,
            'categories' => GameQuestion::CATEGORIES,
        ]);
    }

    public function leaderboard(Request $request)
    {
        $period = $request->query('period') === 'all' ? 'all' : 'weekly';

        return view('games.leaderboard', [
            'period' => $period,
            'rows' => $this->boards->standings($period, $request->user(), 25),
        ]);
    }
}
