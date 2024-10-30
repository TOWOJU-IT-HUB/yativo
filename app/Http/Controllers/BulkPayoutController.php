<?php

namespace App\Http\Controllers;

use App\Models\payoutMethods;
use Illuminate\Http\Request;
use Validator;

class BulkPayoutController extends Controller
{
    public function request(Request $request, $gatewayId) 
    {
        $gateway = payoutMethods::whereId($gatewayId)->first();
        if(!$gateway) {
            return get_error_response(["error" => "Unknown gateway provided"], 404);
        }

        if(strtolower($gateway->gateway) == "local_payment") {
            // validate buld payout requirements for local payments
            $validator = Validator::make($request->all(), [
                //
            ]);
        }

        if(strtolower($gateway->gateway) == "vitawallet") {
            // validate buld payout requirements for local payments
            $validator = Validator::make($request->all(), [
                //
            ]);
        }

        if(strtolower($gateway->gateway) == "Advcash") {
            // validate buld payout requirements for local payments
            $validator = Validator::make($request->all(), [
                //
            ]);
        }

        if(strtolower($gateway->gateway) == "coinpayments") {
            // validate buld payout requirements for local payments
            $validator = Validator::make($request->all(), [
                //
            ]);
        }

        if(strtolower($gateway->gateway) == "binance_pay") {
            // validate buld payout requirements for local payments
            $validator = Validator::make($request->all(), [
                //
            ]);
        }

        if(strtolower($gateway->gateway) == "bitso") {
            // validate buld payout requirements for local payments
            $validator = Validator::make($request->all(), [
                //
            ]);
        }

        if(strtolower($gateway->gateway) == "monnet") {
            // validate buld payout requirements for local payments
            $validator = Validator::make($request->all(), [
                //
            ]);
        }
    }
}
