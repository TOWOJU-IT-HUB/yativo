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
    
                    $payoutMethod = PayoutMethods::whereId($beneficiary->gateway_id)->first();
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
    
                    // Convert amount to beneficiary's currency (Payout Amount)
                    $payoutAmount = $exchange_rate * floatval($request->amount);
    
                    // Get fee parameters
                    $feeData = get_transaction_fee($payoutMethod->id, $request->amount, "payout", "payout");
    
                    $floatFeePercentage = floatval($feeData['float_fee'] ?? 0);
                    $fixedFee = floatval($feeData['fixed_fee'] ?? 0);
    
                    // Convert fixed fee to payout currency
                    $fixedFeeInPayoutCurrency = $fixedFee * $exchange_rate;
    
                    // Calculate float fee
                    $floatFee = ($floatFeePercentage / 100) * $payoutAmount;
    
                    // Calculate total transaction fee
                    $transactionFee = $floatFee + $fixedFeeInPayoutCurrency;
    
                    // Calculate total amount to be charged
                    $amountToBeCharged = $payoutAmount + $transactionFee;
    
                    // Convert the total charge back to the debit wallet currency
                    $totalAmountInDebitCurrency = round($amountToBeCharged / $exchange_rate, 4);
                    $transactionFeeInDebitCurrency = round($transactionFee / $exchange_rate, 4);
    
                    // Store values in session
                    session([
                        'transaction_fee' => $transactionFee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'amount_to_be_charged' => $amountToBeCharged,
                        'amount_to_be_charged_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);
    
                    // Validate allowed currencies
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($request->debit_wallet, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "The selected wallet is not supported for the selected gateway. Allowed currencies: " . $payoutMethod->base_currency
                        ], 400);
                    }
    
                    // Deduct from user's wallet
                    $chargeNow = debit_user_wallet(floatval($totalAmountInDebitCurrency * 100), $request->debit_wallet, "Payout transaction", [
                        'transaction_fee' => $transactionFee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'amount_to_be_charged' => $amountToBeCharged,
                        'amount_to_be_charged_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);
    
                    if ($request->has('debug')) {
                        var_dump([
                            "exchange_rate" => $exchange_rate,
                            "float_fee_percentage" => $floatFeePercentage,
                            "fixed_fee" => $fixedFee,
                            "fixed_fee_in_payout_currency" => $fixedFeeInPayoutCurrency,
                            "float_fee" => $floatFee,
                            "transaction_fee" => $transactionFee,
                            "payout_amount" => $payoutAmount,
                            'amount_to_be_charged' => $amountToBeCharged,
                            'error' => $chargeNow['error'] ?? 'Insufficient wallet balance',
                            "amount_to_be_charged_in_debit_currency" => $totalAmountInDebitCurrency,
                            "gateway" => $payoutMethod
                        ]); exit;
                    }
    
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
