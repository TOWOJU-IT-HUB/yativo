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
            if (!in_array($request->debit_wallet, $result['base_currencies'])) {
                return get_error_response(['error' => 'Currency pair error. Supported are: '.json_encode($result['base_currencies'])], 400);
            }

            // Fetch payment method details
            $payoutMethod = payoutMethods::findOrFail($request->payment_method_id);

            // Convert min and max limits to debit currency
            $minLimitInDebitCurrency = $payoutMethod->minimum_charge / $result['exchange_rate'];
            $maxLimitInDebitCurrency = $payoutMethod->maximum_charge / $result['exchange_rate'];

            // Validate transaction amount against limits
            if (floatval($request->amount) < $minLimitInDebitCurrency) {
                return get_error_response(['error' => 'Transaction amount is below the minimum allowed limit.'], 400);
            }

            if (floatval($request->amount) > $maxLimitInDebitCurrency) {
                return get_error_response(['error' => 'Transaction amount exceeds the maximum allowed limit.'], 400);
            }

            // Deduct from wallet
            $chargeNow = debit_user_wallet(
                floatval($result['debit_amount'] * 100),
                $request->debit_wallet,
                "Payout transaction",
                $result
            );

            if ($request->has('debug')) {
                dd($result);
            }

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