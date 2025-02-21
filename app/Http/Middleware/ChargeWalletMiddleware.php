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
                    $xtotal = floatval($convertedAmount + $transaction_fee);

                    // Convert the total amount charged back to the debit wallet currency
                    $totalAmountInDebitCurrency = round($xtotal / $exchange_rate, 4);
                    $transactionFeeInDebitCurrency = round($transaction_fee / $exchange_rate, 4);

                    session([
                        'transaction_fee' => $transaction_fee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_charged' => $xtotal,
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
                        'transaction_fee' => $transaction_fee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_charged' => $xtotal,
                        'total_amount_charged_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);

                    if($request->has('debug')) {
                        var_dump([
                            "exchange_rate" => $exchange_rate,
                            "transaction_fee" => $transaction_fee,
                            "payout_amount" => $convertedAmount,
                            'total_amount_charged' => $xtotal,
                            'error' => $chargeNow['error'] ?? 'Insufficient wallet balance',
                            "amount_to_be_charged" => $totalAmountInDebitCurrency,
                            "feeInWalletCurrency" => $feeInWalletCurrency,
                            "fee_breakdown" => [
                                "fixed_fee_in_local_currency" => session()->get("fixed_fee_in_local_currency"),
                                "floating_fee_in_local_currency" => session()->get("floating_fee_in_local_currency"),
                                "total_charge" => session()->get("total_charge"),
                                "minimum_charge" => session()->get("minimum_charge"),
                                "maximum_charge" => session()->get("maximum_charge"),
                                "fixed_charge" => session()->get("fixed_charge"),
                                "float_charge" => session()->get("float_charge"),
                                "base_exchange_rate" => session("base_exchange_rate"),
                                "exchange_rate" => session("exchange_rate"),
                            ]
                        ]); exit;
                    }

                    session()->forget([
                        "fixed_fee_in_local_currency",
                        "floating_fee_in_local_currency",
                        "total_charge",
                        "minimum_charge",
                        "maximum_charge",
                        "fixed_charge",
                        "float_charge",
                        "base_exchange_rate",
                        "exchange_rate",
                    ]);


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
