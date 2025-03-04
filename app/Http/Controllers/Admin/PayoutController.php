<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\payoutMethods;
use App\Models\Withdraw;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Customer\app\Models\Customer;
use Log;

class PayoutController extends Controller
{
    public function index(Request $request)
    {
        $query = Withdraw::query();
        $query->with('user', 'payoutGateway', 'transactions');
        $query->when($request->has('status'), function ($query) use ($request) {
            $query->where('status', $request->status);
        });

        $query->orderBy('created_at', 'desc');
        $payouts = $query->cursorPaginate(10)->withQueryString();


        return view('admin.payouts.index', compact('payouts'));
    }

    public function show($id)
    {
        $payout = Withdraw::with('user', 'transactions', 'payoutGateway', 'beneficiary')->findOrFail($id);
        if(isset($payout->raw_data['customer_id']) && !empty($payout->raw_data['customer_id'])){
            $payout['customer'] = Customer::whereCustomerId($payout->raw_data['customer_id'])->first();
        }
        // return $payout;
        return view('admin.payouts.show', compact('payout'));
    }

    public function approvePayout(Request $request, $id)
    {
        $payout = Withdraw::findOrFail($id);
        if ($payout && $payout->status == 'pending') {

            $is_beneficiary = BeneficiaryPaymentMethod::where(['id' => $payout->beneficiary_id])->first();

            if (!$is_beneficiary) {
                return redirect()->back()->with('error', "Payment method not found");
            }

            // check if beneficiary is has a payout method
            if (!isset($is_beneficiary->gateway_id) or (!is_numeric($is_beneficiary->gateway_id))) {
                return redirect()->back()->with('error', "The selected beneficiary has no valid payout method");
            }

            // Get beneficiary payout method
            $payoutMethod = payoutMethods::where('id', $is_beneficiary->gateway_id)->first();

            $payout = new PayoutService();
            $checkout = $payout->makePayment($id, $payoutMethod);
            Log::info('Checkout final response', ['response' => $checkout]);

            var_dump($checkout); exit;
            // return response()->json($checkout); exit;

            if(is_array($checkout) && isset($checkout['error'])) {
                return redirect()->back()->with('error', $checkout['error']);
            }

            return $checkout;

            return back()->with('success', 'Payout approved successfully');
        }
    }

    /**
     * Process manual payout update/approval
     */
    public function manual(Request $request, $id) 
    {
        try {
            $payout = Withdraw::findOrFail($id);
            $payout->status = $request->status;
            $payout->save();

            // update transaction record also
            $txn = TransactionRecord::where(['transaction_id' => $id, 'transaction_memo' => 'payout'])->first();
            if($txn) {
                $txn->transaction_status = $request->status;
                $txn->save();
            }

            if($payout->save() && $txn->save()) {

                // if transaction is rejected refund customer
                if(strtolower($request->status) !== "complete") {
                    $user = User::whereId($payout->user_id)->first();
                    if($user) {
                        $wallet = $user->getWallet($payout->debit_wallet);
                        $wallet->deposit($payout->debit_amount, [
                            "description" => "refund",
                            "full_desc" => "Refund for payout {$payout->id}",
                            "payload" => $payout
                        ]);
                    }
                }


                return back()->with('success', 'Transaction updated successfully');
            }
        } catch(\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    } 
}
