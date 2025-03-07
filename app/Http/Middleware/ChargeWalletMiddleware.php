<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\payoutMethods;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Illuminate\Support\Facades\Log;
use App\Services\PayoutCalculator;

class ChargeWalletMiddleware 
{
    public function handle(Request $request, Closure $next)
    {
        try {$request->validate([
                "amount" => "required",
                "debit_wallet" => "required",
                "payment_method_id" => "required",
            ]);

            $calculator = new PayoutCalculator();
            // echo "I'm at pos: 1";
            
            $result = $calculator->calculate(
                floatval($request->amount),
                $request->debit_wallet,
                $request->payment_method_id,
                floatval($request->exchange_rate_float ?? 0)
            );

            // Validate allowed currencies
            if (!in_array($request->debit_wallet, $result['base_currencies'])) {
                return get_error_response(['error' => 'Currency pair error. Supported are: '.json_encode($result['base_currencies'])], 400);
            }

            // echo "I'm at pos: 2";
            // Deduct from wallet
            $chargeNow = debit_user_wallet(
                floatval($result['debit_amount'] * 100),
                $request->debit_wallet,
                "Payout transaction",
                $result
            );

            // echo "I'm at pos: 3";
            if($request->has('debug')) {
                // $array = array_merge($re)
                dd($result);
            }

            if (!$chargeNow || isset($chargeNow['error'])) {
                return get_error_response(['error' => 'Insufficient wallet balance']);
            }

            echo "I'm at pos: 4";
            return $next($request);

        } catch (\Throwable $th) {
            // var_dump($th->getTrace()); exit;
            // echo "I'm at pos: error";
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }
}