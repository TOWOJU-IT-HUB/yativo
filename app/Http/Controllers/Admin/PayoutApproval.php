<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PayoutApproval extends Controller
{
    //
}


// $payout = new PayoutService();
// $checkout = $payout->makePayment(payoutId: $create->id, payoutGateway: $payoutMethod->gateway);
// // return response()->json($checkout);
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

