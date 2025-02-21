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
                    $exchange_rate_float = floatval($payoutMethod->exchange_rate_float ?? 0);
                    $exchange_rate -= ($exchange_rate * $exchange_rate_float / 100);
    
                    // Convert amount to beneficiary's currency
                    $convertedAmount = $exchange_rate * floatval($request->amount);
    
                    // Calculate transaction fee: (amount * float charge%) + (fixed charge in USD converted)
                    $float_charge = 0.2; // 0.2%
                    $fixed_charge_usd = 1.0; // $1 USD
    
                    $float_fee = (floatval($request->amount) * $float_charge) / 100;
                    $fixed_fee_converted = $fixed_charge_usd * $exchange_rate;
                    $total_fee = $float_fee + $fixed_fee_converted;
    
                    // Store values in session
                    $totalAmountCharged = $convertedAmount + $total_fee;
                    $totalAmountInDebitCurrency = round($totalAmountCharged / $exchange_rate, 4);
                    $transactionFeeInDebitCurrency = round($total_fee / $exchange_rate, 4);
    
                    session([
                        'transaction_fee' => $total_fee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_charged' => $totalAmountCharged,
                        'total_amount_charged_in_debit_currency' => $totalAmountInDebitCurrency
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
                        'transaction_fee' => $total_fee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_charged' => $totalAmountCharged,
                        'total_amount_charged_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);
    
                    if ($request->has('debug')) {
                        var_dump([
                            "exchange_rate" => $exchange_rate,
                            "float_fee" => $float_fee,
                            "fixed_fee_converted" => $fixed_fee_converted,
                            "transaction_fee" => $total_fee,
                            "payout_amount" => $convertedAmount,
                            "total_amount_charged" => $totalAmountCharged,
                            "error" => $chargeNow['error'] ?? 'Insufficient wallet balance',
                            "amount_to_be_charged" => $totalAmountInDebitCurrency
                        ]);
                        exit;
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
