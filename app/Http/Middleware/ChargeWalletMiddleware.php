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
                "amount" => "required|numeric",
                "debit_wallet" => "required|string",
                "payment_method_id" => "required|integer",
            ]);

            $calculator = new PayoutCalculator();
            
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

            // Debugging: Check the types of the values
            Log::debug('Debit Amount:', ['amount' => $result['amount_due']]);
            Log::debug('Amount:', ['amount' => $request->amount]);
            Log::debug('Exchange Rate:', ['rate' => $result['exchange_rate']]);

            $amount_due = $result['amount_due'];

            if ($request->has('debug')) {
                dd($result); exit;
            }

            // Deduct from wallet
            $chargeNow = debit_user_wallet(
                floatval($amount_due * 100),
                $request->debit_wallet,
                "Payout transaction",
                $result
            );

            if (!$chargeNow || isset($chargeNow['error'])) {
                return get_error_response(['error' => 'Insufficient wallet balance']);
            }

            return $next($request);

        } catch (\Throwable $th) {
            // Log the error or notify
            \Log::error("Error processing payout: ", ['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
           
            // Safe check for chargeNow['amount_charged']
            if (isset($chargeNow) && (is_array($chargeNow) && isset($chargeNow['amount_charged']) || property_exists($chargeNow, 'amount_charged'))) {
                // Refund the user
                $user = auth()->user();
                $wallet = $user->getWallet($request->debit_wallet); 

                // Define a description for the refund
                $description = "Refund for failed payout transaction: " . (is_array($chargeNow) ? $chargeNow['transaction_id'] : $chargeNow->transaction_id); 
                
                // Credit the wallet back (refund)
                $refundResult = $wallet->credit(floatval(is_array($chargeNow) ? $chargeNow['amount_charged'] : $chargeNow->amount_charged), $description);

                // Check if the refund was successful
                if ($refundResult) {
                    return get_error_response(['error' => $th->getMessage()]);
                } else {
                    return get_error_response(['error' => $th->getMessage(), 'message' => 'Transaction failed and refund could not be processed.']);
                }
            }

            return get_error_response(['error' => $th->getMessage()]);
        }
    }
}