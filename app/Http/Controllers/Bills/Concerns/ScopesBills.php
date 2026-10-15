<?php

namespace App\Http\Controllers\Bills\Concerns;

use App\Models\BillParticipant;
use App\Models\GroupBill;
use Illuminate\Http\Request;

/** IDOR guard: non-participants get a 404 (the bill's existence is not revealed); non-owners get 403 on owner actions. */
trait ScopesBills
{
    protected function participantOr404(Request $request, GroupBill $bill): BillParticipant
    {
        $me = $bill->participantFor($request->user());
        abort_unless($me, 404);

        return $me;
    }

    protected function ownerOrAbort(Request $request, GroupBill $bill): BillParticipant
    {
        $me = $this->participantOr404($request, $bill);
        abort_unless($bill->isOwner($request->user()) && $me->isOwner(), 403, 'Only the bill owner can do that.');

        return $me;
    }
}
