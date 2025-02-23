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

                    // ✅ Get Live Exchange Rates
                    $fromCurrency = strtoupper($request->debit_wallet);
                    $toCurrency = strtoupper($beneficiary->currency);
                    
                    // Rate from debit currency to beneficiary currency
                    $exchangeRate = $this->getLiveExchangeRate($fromCurrency, $toCurrency);
                    if (!$exchangeRate || $exchangeRate <= 0) {
                        return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
                    }

                    // Rate from USD to debit currency (for fee conversion)
                    $usdToDebitRate = $this->getLiveExchangeRate('USD', $fromCurrency);
                    if (!$usdToDebitRate || $usdToDebitRate <= 0) {
                        return get_error_response(['error' => 'Unable to fetch USD exchange rate.'], 400);
                    }

                    // ✅ Exchange Rate Float Adjustment (Only if currencies differ)
                    $exchangeRateFloat = ($fromCurrency !== $toCurrency) 
                        ? (floatval($payoutMethod->exchange_rate_float ?? 0) / 100) 
                        : 0;
                    $adjustedExchangeRate = round($exchangeRate - ($exchangeRate * $exchangeRateFloat), 6);

                    // ✅ Convert Fees from USD to Debit Currency
                    $gatewayFloatChargeUSD = floatval(($payoutMethod->float_charge ?? 0) / 100) * $request->amount; // 0.2% -> 0.002
                    $gatewayFixedChargeUSD = floatval($payoutMethod->fixed_charge ?? 0); // 1.0 USD

                    $floatFee = round($gatewayFloatChargeUSD * $usdToDebitRate, 6);
                    $fixedCharge = round($gatewayFixedChargeUSD * $usdToDebitRate, 6);
                    $transactionFee = round($floatFee + $fixedCharge, 6);

                    // ✅ Compute Total Amount to Deduct (in debit currency)
                    $amount = floatval($request->amount);
                    $totalAmountInDebitCurrency = round($amount + $transactionFee, 6);

                    // ✅ Amount sent to beneficiary (in their currency)
                    $beneficiaryAmount = round($amount * $adjustedExchangeRate, 6);

                    // ✅ Store in Session
                    session([
                        'transaction_fee' => $transactionFee,
                        'total_amount_due' => $totalAmountInDebitCurrency,
                        'beneficiary_amount' => $beneficiaryAmount,
                        'exchange_rate' => $adjustedExchangeRate
                    ]);

                    // ✅ Debug Mode - Dump All Parameters
                    if ($request->has('debug')) {
                        dd([
                            "from_currency" => $fromCurrency,
                            "to_currency" => $toCurrency,
                            "exchange_rate" => $exchangeRate,
                            "usd_to_debit_rate" => $usdToDebitRate,
                            "exchange_rate_float_applied" => ($fromCurrency !== $toCurrency) ? 'Yes' : 'No',
                            "exchange_rate_float (%)" => $exchangeRateFloat * 100,
                            "adjusted_exchange_rate" => $adjustedExchangeRate,
                            "gateway_float_charge (%)" => $gatewayFloatChargeUSD * 100,
                            "gateway_fixed_charge (USD)" => $gatewayFixedChargeUSD,
                            "float_fee (converted)" => $floatFee,
                            "fixed_charge (converted)" => $fixedCharge,
                            "transaction_fee (converted)" => $transactionFee,
                            "amount_in_debit_currency" => $amount,
                            "total_amount_due" => $totalAmountInDebitCurrency,
                            "beneficiary_amount" => $beneficiaryAmount
                        ]);
                    }

                    // ✅ Validate Allowed Currencies
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($fromCurrency, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "Wallet currency not supported. Allowed: " . $payoutMethod->base_currency
                        ], 400);
                    }

                    // ✅ Deduct from User's Wallet
                    $chargeNow = debit_user_wallet(
                        floatval($totalAmountInDebitCurrency * 100), // Convert to cents if needed
                        $fromCurrency,
                        "Payout transaction",
                        [
                            'transaction_fee' => $transactionFee,
                            'total_amount_due' => $totalAmountInDebitCurrency,
                            'beneficiary_amount' => $beneficiaryAmount
                        ]
                    );

                    if (!$chargeNow || isset($chargeNow['error'])) {
                        return get_error_response(['error' => 'Insufficient wallet balance']);
                    }
                }

                return $next($request);
            }

            return get_error_response(['error' => "Transaction processing failed"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
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
                    Log::error("Exchange rate fetch error from $url: " . $e->getMessage());
                }
            }
            return 0;
        });
    }
}