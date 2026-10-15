<?php

namespace App\Http\Controllers\Games;

use App\Http\Controllers\Controller;
use App\Models\CompetitionInvitation;
use App\Services\Games\CompetitionService;
use App\Services\Games\GameException;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function __construct(private CompetitionService $games)
    {
    }

    public function accept(Request $request, CompetitionInvitation $invitation)
    {
        return $this->respond($request, $invitation, true);
    }

    public function decline(Request $request, CompetitionInvitation $invitation)
    {
        return $this->respond($request, $invitation, false);
    }

    private function respond(Request $request, CompetitionInvitation $invitation, bool $accept)
    {
        $this->authorize('respond', $invitation);
        try {
            $this->games->respond($invitation, $request->user(), $accept);
        } catch (GameException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage(), 'data' => null], $e->status)
                : redirect()->route('games.hub')->withErrors(['queue' => $e->getMessage()]);
        }

        $message = $accept ? 'Invitation accepted — you are in the battle lobby.' : 'Invitation declined.';
        $url = $accept ? route('games.competitions.show', $invitation->competition_id) : route('games.hub');

        return $request->expectsJson()
            ? response()->json(['message' => $message, 'data' => ['redirect' => $url]])
            : redirect($url)->with('ok', $message);
    }
}
