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

                    // ✅ Get Live Exchange Rate
                    $fromCurrency = strtoupper($request->debit_wallet);
                    $toCurrency = strtoupper($beneficiary->currency);
                    $exchangeRate = $this->getLiveExchangeRate($fromCurrency, $toCurrency);
                    if (!$exchangeRate || $exchangeRate <= 0) {
                        return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
                    }

                    // ✅ Convert Charges from FromCurrency → ToCurrency
                    $floatCharge = floatval($payoutMethod->float_charge ?? 0) / 100;
                    $fixedCharge = floatval($payoutMethod->fixed_charge ?? 0);

                    if ($fromCurrency !== $toCurrency) {
                        // Convert fees to the beneficiary currency (ToCurrency)
                        $floatFeeConverted = round($floatCharge * $exchangeRate, 6);
                        $fixedChargeConverted = round($fixedCharge * $exchangeRate, 6);
                    } else {
                        $floatFeeConverted = round($floatCharge, 6);
                        $fixedChargeConverted = round($fixedCharge, 6);
                    }

                    $transactionFee = round($floatFeeConverted + $fixedChargeConverted, 6);

                    // ✅ Convert Amount and Fees
                    $amount = floatval($request->amount);
                    $convertedAmount = round($amount * $exchangeRate, 6);
                    $totalAmountDueInToCurrency = round($convertedAmount + $transactionFee, 6);

                    // ✅ Convert Back to FromCurrency if Needed
                    if ($fromCurrency !== $toCurrency) {
                        $totalAmountDueInFromCurrency = round($totalAmountDueInToCurrency / $exchangeRate, 6);
                    } else {
                        $totalAmountDueInFromCurrency = $totalAmountDueInToCurrency;
                    }

                    // ✅ Store in Session
                    session([
                        'transaction_fee' => $transactionFee,
                        'total_amount_due_in_to_currency' => $totalAmountDueInToCurrency,
                        'total_amount_due_in_from_currency' => $totalAmountDueInFromCurrency
                    ]);

                    // ✅ Debug Mode - Dump All Parameters
                    if ($request->has('debug')) {
                        dd([
                            "from_currency" => $fromCurrency,
                            "to_currency" => $toCurrency,
                            "exchange_rate" => $exchangeRate,
                            "float_charge (%)" => $floatCharge * 100,
                            "fixed_charge" => $fixedCharge,
                            "float_fee_converted" => $floatFeeConverted,
                            "fixed_charge_converted" => $fixedChargeConverted,
                            "transaction_fee" => $transactionFee,
                            "amount" => $amount,
                            "converted_amount" => $convertedAmount,
                            "total_amount_due_in_to_currency" => $totalAmountDueInToCurrency,
                            "total_amount_due_in_from_currency" => $totalAmountDueInFromCurrency
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
                    $chargeNow = debit_user_wallet(floatval($totalAmountDueInFromCurrency * 100), $fromCurrency, "Payout transaction", [
                        'transaction_fee' => $transactionFee,
                        'total_amount_due_in_to_currency' => $totalAmountDueInToCurrency,
                        'total_amount_due_in_from_currency' => $totalAmountDueInFromCurrency
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
