<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\payoutMethods;
use App\Models\Withdraw;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Customer\app\Models\Customer;

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
        $payouts = $query->cursorPaginate(10)->withQueryString();;


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

            if(isset($checkout['error'])) {
                return redirect()->back()->with('error', $checkout['error']);
            }
            return back()->with('success', 'Payout approved successfully');
            // return response()->json($checkout);
            // if (!is_array($checkout)) {
            //     $checkout = (array)$checkout; 
            // }

            // if (isset($checkout['error'])) {
            //     return get_error_response(['error' => $checkout['error']]);
            // }
            // $create->raw_data = $checkout;
            // $create->save();
            // // user()->notify(new WithdrawalNotification($create));
            // $payout = Withdraw::whereId($create->id)->with('beneficiary')->first();


            // $webhook_url = Webhook::whereUserId($payout->user_id)->first();
            // if ($webhook_url) {
            //     WebhookCall::create()->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
            //         "event.type" => "withdrawal",
            //         "payload" => $payout
            //     ])->dispatchSync();
            // }

            // return redirect()->back()->with('success', 'Payout approved successfully');
        }
    }
}
