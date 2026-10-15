<?php

namespace App\Http\Controllers\Bills;

use App\Http\Controllers\Bills\Concerns\ScopesBills;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bills\ClaimPaymentRequest;
use App\Models\BillPayment;
use App\Models\GroupBill;
use App\Services\Bills\BillCalculator;
use App\Services\Bills\BillManager;
use Illuminate\Http\Request;

class BillSettlementController extends Controller
{
    use ScopesBills;

    public function __construct(private BillManager $bills, private BillCalculator $calc) {}

    public function show(Request $request, GroupBill $bill)
    {
        $me = $this->participantOr404($request, $bill);
        $bill->load(['joinedParticipants', 'owner:id,name']);
        $calc = $this->calc->forBill($bill);
        $balances = $this->bills->balances($bill, $calc);
        $payments = $bill->payments()->with('participant:id,display_name,user_id')->get();
        // Members see their own payment history; the owner sees everyone's.
        if (! $me->isOwner()) {
            $payments = $payments->where('bill_participant_id', $me->id)->values();
        }

        return view('bills.settle', compact('bill', 'me', 'calc', 'balances', 'payments'));
    }

    public function claim(ClaimPaymentRequest $request, GroupBill $bill)
    {
        $me = $this->participantOr404($request, $bill);
        $pay = $this->bills->claimPayment($bill, $me, $request->validated());

        return redirect()->route('bills.settle', $bill)->with('ok', 'Marked NPR '.BillCalculator::money(BillCalculator::toPaisa($pay->amount)).' as paid — waiting for '.$bill->owner->name.' to confirm.');
    }

    public function confirm(Request $request, GroupBill $bill, BillPayment $payment)
    {
        $this->ownerOrAbort($request, $bill);
        $this->bills->confirmPayment($bill, $payment, $request->user());

        return redirect()->route('bills.settle', $bill)->with('ok', 'Payment confirmed.');
    }

    public function dispute(Request $request, GroupBill $bill, BillPayment $payment)
    {
        $this->ownerOrAbort($request, $bill);
        $this->bills->disputePayment($bill, $payment);

        return redirect()->route('bills.settle', $bill)->with('ok', 'Marked as not received. They can submit it again.');
    }
}
