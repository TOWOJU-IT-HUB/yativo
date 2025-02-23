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
            $calculator = new PayoutCalculator();
            
            $result = $calculator->calculate(
                floatval($request->amount),
                $request->debit_wallet,
                $request->payment_method_id,
                floatval($request->exchange_rate_float ?? 0)
            );

            // Validate allowed currencies
            if (!in_array($request->debit_wallet, $result['base_currencies'])) {
                // Return error
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
            // Handle exceptions
        }
    }
}

// In a controller
// public function preview(Request $request, PayoutCalculator $calculator)
// {
//     try {
//         $result = $calculator->calculate(
//             $request->amount,
//             $request->wallet_currency,
//             $request->payment_method_id
//         );

//         return response()->json($result);
//     } catch (\Exception $e) {
//         return response()->json(['error' => $e->getMessage()], 400);
//     }
// }
// }