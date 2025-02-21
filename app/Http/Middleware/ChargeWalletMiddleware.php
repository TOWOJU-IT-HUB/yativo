<?php

namespace App\Http\Middleware;

use App\Models\BeneficiaryFoems;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Plan;
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
        // cancell all users subscriptions. 
        foreach(User::all() as $u) {
            $plan = Plan::whereId(1)->first();
            $subscription = $u->subscribeTo($plan, 30, false); // 30 days, non-recurrent
        }

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


                    $new_total_fees = $this->calculateTotalFees();

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
                            "new_total_fees" => $new_total_fees['total_fees'],
                            "transaction_fee" => $transaction_fee,
                            "payout_amount" => $convertedAmount,
                            'total_amount_charged' => $xtotal,
                            'error' => $chargeNow['error'] ?? 'Insufficient wallet balance',
                            "amount_to_be_charged" => $totalAmountInDebitCurrency,
                            "feeInWalletCurrency" => $feeInWalletCurrency
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


    private function calculateTotalFees() {
        $request = request();
        $beneficiary = BeneficiaryPaymentMethod::whereId($request['payment_method_id'])->first();
        if (!$beneficiary) {
            return get_error_response(['error' => 'Beneficiary not found']);
        }
        
        $payoutMethod = PayoutMethods::whereId($beneficiary->gateway_id)->first();
        if (!$payoutMethod) {
            return get_error_response(['error' => 'Invalid payout method selected']);
        }

        echo json_encode($payoutMethod); exit;
        
        // Get exchange rate
        echo $exchange_rate = get_transaction_rate($request['debit_wallet'], $beneficiary->currency, $payoutMethod->id, "payout");
        if (!$exchange_rate || $exchange_rate <= 0) {
            return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
        }
        
        // Calculate Fees
        $amount = $request['amount'];
        echo $float_fee = ($amount * ($payoutMethod->float_fee / 100)); // 0.2% of amount
        echo $fixed_fee = $payoutMethod->fixed_fee; // Fixed fee in USD
        
        // Convert Fees to CLP
        $total_fee = $float_fee + ($fixed_fee * $exchange_rate);
        
        return ['total_fees' => floatval($total_fee)];
    }
}
