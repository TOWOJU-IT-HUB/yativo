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
    
                    // Convert amount using exchange rate
                    $xchangeRate = exchange_rates($payoutMethod->currency, $request->debit_wallet);
                    if (!$xchangeRate || $xchangeRate <= 0) {
                        return get_error_response(['error' => 'Exchange rate unavailable']);
                    }
    
                    $amountInWalletCurrency = round(floatval($request->amount) * $xchangeRate, 4);
                    $transactionFee = floatval(get_transaction_fee($beneficiary->gateway_id, $request->amount, "payout", "payout"));
    
                    // Corrected total amount calculation
                    $feeInWalletCurrency = round($transactionFee * $xchangeRate, 4);
                    $finalAmount = round($amountInWalletCurrency + $feeInWalletCurrency, 4);
    
                    // Store values in session
                    session([
                        'transaction_fee' => $feeInWalletCurrency,
                        'total_amount_charged' => $finalAmount
                    ]);
                    
                    return response()->json([
                        "rate" => $xchangeRate,
                        "amount" => $request->amount,
                        "final_amount" => $finalAmount,
                        "fee" => $feeInWalletCurrency
                    ]);

                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($request->debit_wallet, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "The selected wallet is not supported for selected gateway. Allowed currencies: " . $payoutMethod->base_currency
                        ], 400);
                    }
    
                    $chargeNow = debit_user_wallet($finalAmount, $request->debit_wallet);
                    if (!$chargeNow || isset($chargeNow['error'])) {
                        return get_error_response(['error' => 'Insufficient wallet balance']);
                    }
                } 
    
                return $next($request);
            }
    
            return get_error_response(['error' => "Sorry, we're currently unable to process your transaction"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }
    
    
}
