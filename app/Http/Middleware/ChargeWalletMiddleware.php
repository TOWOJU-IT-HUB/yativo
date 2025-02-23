<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\payoutMethods;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Illuminate\Support\Facades\Log;

class ChargeWalletMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->has('amount')) {
                $user = $request->user();

                if ($request->has('payment_method_id')) {
                    // Fetch Beneficiary & Payout Method
                    $beneficiary = BeneficiaryPaymentMethod::find($request->payment_method_id);
                    if (!$beneficiary) {
                        return get_error_response(['error' => 'Beneficiary not found']);
                    }

                    $payoutMethod = payoutMethods::find($beneficiary->gateway_id);
                    if (!$payoutMethod) {
                        return get_error_response(['error' => 'Invalid payout method selected']);
                    }

                    // ✅ Define Currency Pair
                    $fromCurrency = strtoupper($request->debit_wallet);
                    $toCurrency = strtoupper($beneficiary->currency);
                    $exchangeRate = $this->getLiveExchangeRate($fromCurrency, $toCurrency);
                    if (!$exchangeRate || $exchangeRate <= 0) {
                        return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
                    }

                    // ✅ Define Charges (percentage & fixed)
                    $floatChargePercent = floatval($payoutMethod->float_charge ?? 0) / 100;
                    $fixedCharge = floatval($payoutMethod->fixed_charge ?? 0);
                    $amount = floatval($request->amount);

                    if ($fromCurrency === $toCurrency) {
                        // ✅ Case 1: Same Currency Pair
                        $floatFee = round($amount * $floatChargePercent, 6);
                        $transactionFee = round($floatFee + $fixedCharge, 6);
                        $totalAmountDue = round($amount + $transactionFee, 6);
                    } else {
                        // ✅ Case 2: Different Currency Pair
                        // Convert fees from USD to `fromCurrency`
                        $floatFeeConverted = round(($amount * $floatChargePercent) * $exchangeRate, 6);
                        $fixedChargeConverted = round($fixedCharge * $exchangeRate, 6);
                        $transactionFee = round($floatFeeConverted + $fixedChargeConverted, 6);

                        // Convert transaction amount
                        $convertedAmount = round($amount * $exchangeRate, 6);
                        $totalAmountDueInToCurrency = round($convertedAmount + $transactionFee, 6);

                        // Convert back to FromCurrency if necessary
                        $totalAmountDue = round($totalAmountDueInToCurrency / $exchangeRate, 6);
                    }

                    // ✅ Store in Session
                    session([
                        'transaction_fee' => $transactionFee,
                        'total_amount_due' => $totalAmountDue
                    ]);

                    // ✅ Debugging Mode (Optional)
                    if ($request->has('debug')) {
                        dd([
                            "from_currency" => $fromCurrency,
                            "to_currency" => $toCurrency,
                            "exchange_rate" => $exchangeRate,
                            "original_amount" => $amount,
                            
                            // Fees before conversion
                            "float_charge_percent" => $floatChargePercent * 100,
                            "fixed_charge_usd" => $fixedCharge,
                            
                            // Fees after conversion (for different currencies)
                            "converted_float_fee" => isset($floatFeeConverted) ? $floatFeeConverted : "N/A",
                            "converted_fixed_charge" => isset($fixedChargeConverted) ? $fixedChargeConverted : "N/A",
                            
                            // Final amounts
                            "transaction_fee" => $transactionFee,
                            "converted_amount" => isset($convertedAmount) ? $convertedAmount : "N/A",
                            "total_amount_due_in_to_currency" => isset($totalAmountDueInToCurrency) ? $totalAmountDueInToCurrency : "N/A",
                            "final_total_amount_due" => $totalAmountDue
                        ]);
                    }
                    

                    // ✅ Validate Allowed Currencies
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($fromCurrency, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "The selected wallet is not supported for the selected gateway. Allowed currencies: " . $payoutMethod->base_currency
                        ], 400);
                    }

                    // ✅ Deduct from User's Wallet
                    $chargeNow = debit_user_wallet(floatval($totalAmountDue * 100), $fromCurrency, "Payout transaction", [
                        'transaction_fee' => $transactionFee,
                        'total_amount_due' => $totalAmountDue
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

    private function getLiveExchangeRate($from, $to)
    {
        if ($from === $to) return 1.0;

        $cacheKey = "exchange_rate_{$from}_{$to}";
        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($from, $to) {
            $client = new \GuzzleHttp\Client();
            $apis = [
                "https://min-api.cryptocompare.com/data/price" => ['query' => ['fsym' => $from, 'tsyms' => $to]],
                "https://api.coinbase.com/v2/exchange-rates" => ['query' => ['currency' => $from]]
            ];

            foreach ($apis as $url => $params) {
                try {
                    $response = json_decode($client->get($url, ['query' => $params])->getBody(), true);
                    $rate = $url === "https://min-api.cryptocompare.com/data/price"
                        ? ($response[$to] ?? null)
                        : ($response['data']['rates'][$to] ?? null);

                    if ($rate) return (float) $rate;
                } catch (\Exception $e) {
                    Log::error("Error fetching exchange rate from $url: " . $e->getMessage());
                }
            }

            return 0;
        });
    }
}
