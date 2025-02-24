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
        try {
            $request->validate([
                "amount" => "required",
                "debit_wallet" => "required",
                "payment_method_id" => "required",
            ]);

            $calculator = new PayoutCalculator();
            
            $result = $calculator->calculate(
                floatval($request->amount),
                $request->debit_wallet,
                $request->payment_method_id,
                floatval($request->exchange_rate_float ?? 0)
            );

            // Validate allowed currencies
            if (!in_array($request->debit_wallet, $result['base_currency'])) {
                return get_error_response(['error' => 'Currency pair error.'], 400);
            }

            if($request->has('debug')) {
                // $array = array_merge($re)
                dd($result);
            }

            // Deduct from wallet
            debit_user_wallet(
                $result['debit_amount'] * 100,
                $request->debit_wallet,
                "Payout transaction",
                $result
            );

            return $next($request);

        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
}
