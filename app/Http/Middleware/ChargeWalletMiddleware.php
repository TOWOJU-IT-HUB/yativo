<?php

namespace App\Http\Middleware;

use App\Models\BeneficiaryFoems;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Models\payoutMethods;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;

class ChargeWalletMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->has('amount')) {
                $user = $request->user();
                if ($request->has('payment_method_id')) {
                    // Withdrawal to beneficiary
                    $beneficiary = BeneficiaryPaymentMethod::whereId($request->payment_method_id)->first();
                    if (!$beneficiary) {
                        return get_error_response(['error' => 'Beneficiary not found']);
                    }

                    $payoutMethod = payoutMethods::whereId($beneficiary->gateway_id)->first();
                    if (!$payoutMethod) {
                        return get_error_response(['error' => 'Invalid payout method selected']);
                    }

                    // Get exchange rate
                    $exchange_rate = get_transaction_rate($request->debit_wallet, $beneficiary->currency, $payoutMethod->id, "payout");
                    if (!$exchange_rate || $exchange_rate <= 0) {
                        return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
                    }

                    $exchange_rate = floatval($exchange_rate);
                    $deposit_float = floatval($payoutMethod->exchange_rate_float ?? 0);
                    $exchange_rate -= ($exchange_rate * $deposit_float / 100);

                    // Convert amount to beneficiary's currency
                    $convertedAmount = $exchange_rate * floatval($request->amount);

                    // Calculate transaction fee
                    $transaction_fee = get_transaction_fee($payoutMethod->id, $request->amount, "payout", "payout");
                    $feeInWalletCurrency = $transaction_fee * $exchange_rate;

                    // Calculate final amount
                    $finalAmount = round($feeInWalletCurrency, 4);

                    // Store values in session
                    session([
                        'transaction_fee' => $transaction_fee,
                        'total_amount_charged' => floatval($convertedAmount + $transaction_fee)
                    ]);

                    // Validate allowed currencies
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($request->debit_wallet, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "The selected wallet is not supported for the selected gateway. Allowed currencies: " . $payoutMethod->base_currency
                        ], 400);
                    }

                    // Deduct from user's wallet
                    $chargeNow = debit_user_wallet(floatval($convertedAmount + $transaction_fee), $request->debit_wallet, "Payout transaction", [
                        "transaction_fee" => $transaction_fee,
                        "payout_amount" => $convertedAmount
                    ]);

                    if (!$chargeNow || isset($chargeNow['error'])) {
                        return get_error_response(['error' => 'Insufficient wallet balance']);
                    }

                    // return response()->json([
                    //     "rate" => $exchange_rate,
                    //     "amount" => $request->amount,
                    //     "converted_amount" => $convertedAmount,
                    //     "final_amount" => $finalAmount,
                    //     "fee" => $feeInWalletCurrency,
                    //     "transaction_fee" => $transaction_fee,
                    //     "chargeNow" => $chargeNow, 
                    // ]);
                } 

                return $next($request);
            }

            return get_error_response(['error' => "Sorry, we're currently unable to process your transaction"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }
}
