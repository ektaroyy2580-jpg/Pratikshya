<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Bills\Concerns\ScopesBills;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BillParticipant;
use App\Models\GroupBill;
use App\Services\Bills\BillManager;
use Illuminate\Http\Request;

class BillInviteController extends Controller
{
    use ScopesBills;

    /** Identical for every email so nobody can probe which addresses have accounts. */
    public const EMAIL_INVITE_RESPONSE = 'Invitation sent — if that email belongs to a Pratikshya account, they will see it under Bills.';

    public function __construct(private BillManager $bills) {}

    /** Link landing page: shows a minimal preview and an explicit "Join this bill" button. Never auto-joins. */
    public function show(Request $request, string $token)
    {
        $bill = GroupBill::findByInviteToken($token);
        if (! $bill) {
            return response()->view('bills.join-invalid', [], 404);
        }
        if ($bill->participantFor($request->user())) {
            return redirect()->route('bills.show', $bill)->with('ok', 'You are already part of this bill.');
        }

        return view('bills.join', ['bill' => $bill, 'token' => $token, 'people' => $bill->joinedParticipants()->count(), 'owner' => $bill->owner]);
    }

    public function join(Request $request, string $token)
    {
        $bill = GroupBill::findByInviteToken($token);
        if (! $bill) {
            return response()->view('bills.join-invalid', [], 404);
        }
        $this->bills->join($bill, $request->user());

        return redirect()->route('bills.show', $bill)->with('ok', 'You joined "'.$bill->title.'".');
    }

    public function accept(Request $request, BillParticipant $participant)
    {
        $p = $this->bills->acceptInvitation($participant, $request->user());

        return redirect()->route('bills.show', $p->group_bill_id)->with('ok', 'Invitation accepted.');
    }

    public function decline(Request $request, BillParticipant $participant)
    {
        $this->bills->declineInvitation($participant, $request->user());

        return redirect()->route('bills.index')->with('ok', 'Invitation declined.');
    }

    public function rotate(Request $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        $bill->rotateInviteToken();
        AuditLog::record('bill_invite_rotated', $bill);

        return redirect()->to(route('bills.show', $bill).'#invite')->with('ok', 'New invite link created — the old link no longer works.');
    }

    public function revoke(Request $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        $bill->revokeInviteToken();
        AuditLog::record('bill_invite_revoked', $bill);

        return redirect()->to(route('bills.show', $bill).'#invite')->with('ok', 'Invite link revoked.');
    }

    public function email(Request $request, GroupBill $bill)
    {
        $this->ownerOrAbort($request, $bill);
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);
        $this->bills->inviteByEmail($bill, $request->user(), $data['email']);

        return redirect()->to(route('bills.show', $bill).'#invite')->with('ok', self::EMAIL_INVITE_RESPONSE);
    }
}
