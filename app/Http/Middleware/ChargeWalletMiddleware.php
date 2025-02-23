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
                    
                    // Key rates for calculations
                    $exchangeRate = $this->getLiveExchangeRate($fromCurrency, $toCurrency);
                    $usdToDebitRate = $this->getLiveExchangeRate('USD', $fromCurrency);
                    $adjustedExchangeRate = $exchangeRate;

                    // ✅ Exchange Rate Float Adjustment
                    if ($fromCurrency !== $toCurrency) {
                        $exchangeRateFloat = floatval($payoutMethod->exchange_rate_float ?? 0) / 100;
                        $adjustedExchangeRate = round($exchangeRate - ($exchangeRate * $exchangeRateFloat), 6);
                    }

                    // ✅ Calculate Transaction Fee using formula: T = [(A × F/100) × E] + (FC × U)
                    $amount = floatval($request->amount);
                    $F = floatval($payoutMethod->float_charge ?? 0) / 100;  // Convert percentage to decimal
                    $FC = floatval($payoutMethod->fixed_charge ?? 0);
                    $U = $usdToDebitRate;
                    $E = $adjustedExchangeRate;

                    // Formula implementation
                    $floatFee = round(($amount * $F) * $E, 6);
                    $fixedCharge = round($FC * $U, 6);
                    $transactionFee = round($floatFee + $fixedCharge, 6);

                    // ✅ Total Amount Calculation
                    $totalAmountInDebitCurrency = round($amount + $transactionFee, 6);
                    $beneficiaryAmount = round($amount * $adjustedExchangeRate, 6);

                    // ✅ Store in Session
                    session([
                        'transaction_fee' => $transactionFee,
                        'total_amount_due' => $totalAmountInDebitCurrency,
                        'beneficiary_amount' => $beneficiaryAmount,
                        'exchange_rate' => $adjustedExchangeRate
                    ]);

                    // ✅ Debug Mode
                    if ($request->has('debug')) {
                        dd([
                            "formula_parameters" => [
                                "A (amount)" => $amount,
                                "F (float charge %)" => $F * 100,
                                "E (adjusted rate)" => $E,
                                "FC (fixed charge USD)" => $FC,
                                "U (USD->debit rate)" => $U,
                            ],
                            "calculations" => [
                                "float_fee" => $floatFee,
                                "fixed_charge" => $fixedCharge,
                                "transaction_fee" => $transactionFee,
                                "total_debit" => $totalAmountInDebitCurrency,
                                "beneficiary_receives" => $beneficiaryAmount
                            ]
                        ]);
                    }

                    // ✅ Validate Allowed Currencies
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($fromCurrency, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "Currency not supported. Allowed: " . $payoutMethod->base_currency
                        ], 400);
                    }

                    // ✅ Deduct from Wallet
                    $chargeNow = debit_user_wallet(
                        $totalAmountInDebitCurrency * 100,  // Convert to cents if needed
                        $fromCurrency,
                        "Payout transaction",
                        [
                            'transaction_fee' => $transactionFee,
                            'total_charged' => $totalAmountInDebitCurrency,
                            'beneficiary_amount' => $beneficiaryAmount
                        ]
                    );

                    if (!$chargeNow || isset($chargeNow['error'])) {
                        return get_error_response(['error' => 'Insufficient balance']);
                    }
                }

                return $next($request);
            }

            return get_error_response(['error' => "Transaction failed"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    private function getLiveExchangeRate($from, $to)
    {
        if ($from === $to) return 1.0;

        $cacheKey = "exrate_{$from}_{$to}";
        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($from, $to) {
            $client = new \GuzzleHttp\Client();
            $endpoints = [
                "https://min-api.cryptocompare.com/data/price?fsym={$from}&tsyms={$to}",
                "https://api.coinbase.com/v2/exchange-rates?currency={$from}"
            ];

            foreach ($endpoints as $url) {
                try {
                    $response = json_decode($client->get($url)->getBody(), true);
                    $rate = str_contains($url, 'cryptocompare') 
                        ? ($response[$to] ?? null)
                        : ($response['data']['rates'][$to] ?? null);

                    if ($rate) return (float) $rate;
                } catch (\Exception $e) {
                    Log::error("Exchange rate error: " . $e->getMessage());
                }
            }
            return 0;
        });
    }
}