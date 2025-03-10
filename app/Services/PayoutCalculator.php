<?php
// app/Services/PayoutCalculator.php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\payoutMethods as PayoutMethods;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;

class PayoutCalculator
{
    // Main calculation method
    public function calculate(
        float $amount,
        string $walletCurrency,
        int $paymentMethodId,
        float $exchangeRateFloat = 0
    ): array {
        $request = request();

        if ($request->has('method_id') && !empty($request->method_id)) {
            // Direct gateway mode - use request currencies
            $gatewayId = $paymentMethodId;
            $targetCurrency = strtoupper($request->to_currency);
            $walletCurrency = strtoupper($request->from_currency); // Override parameter
        } else {
            // Beneficiary mode - get from stored beneficiary
            $beneficiary = BeneficiaryPaymentMethod::findOrFail($paymentMethodId);
            $gatewayId = $beneficiary->gateway_id;
            $targetCurrency = $beneficiary->currency;
        }

        $payoutMethod = PayoutMethods::findOrFail($gatewayId);

        // Get exchange rates
        $rates = $this->getExchangeRates($walletCurrency, $targetCurrency);

        // Calculate fees
        $fees = $this->calculateFees(
            $amount,
            $walletCurrency,
            $targetCurrency,
            $payoutMethod->float_charge,
            $payoutMethod->fixed_charge,
            $payoutMethod
        );

        // Calculate adjusted exchange rate
        $adjustedRate = $this->applyExchangeRateFloat(
            $rates['wallet_to_target'],
            $payoutMethod->exchange_rate_float // Use the exchange_rate_float from the payout method
        );

        // Calculate final amounts
        return $this->compileResults(
            $amount,
            $fees,
            $rates['wallet_to_target'],
            $adjustedRate,
            $targetCurrency,
            $payoutMethod,
            $walletCurrency
        );
    }

    // Exchange rate handling
    private function getExchangeRates(string $walletCurrency, string $targetCurrency): array
    {
        return [
            'wallet_to_usd' => $walletCurrency === 'USD' 
                ? 1.0 
                : $this->getLiveExchangeRate('USD', $walletCurrency),
                
            'usd_to_target' => $this->getLiveExchangeRate('USD', $targetCurrency),
            'wallet_to_target' => $walletCurrency === 'USD' && $targetCurrency === 'USD' 
                ? 1.0 
                : $this->getLiveExchangeRate($walletCurrency, $targetCurrency)
        ];
    }

    // Fee calculation
    private function calculateFees(
        float $amount,
        string $walletCurrency,
        string $targetCurrency,
        float $floatPercent,
        float $fixedFeeUSD,
        PayoutMethods $payoutMethod
    ): array {
        $rates = $this->getExchangeRates($walletCurrency, $targetCurrency);
        $amountUSD = $amount / $rates['wallet_to_usd'];

        // Calculate float and fixed fees
        $floatFee = $amountUSD * ($floatPercent / 100) * $rates['usd_to_target'];
        $fixedFee = $fixedFeeUSD * $rates['usd_to_target'];

        // Calculate total fee
        $totalFee = $floatFee + $fixedFee;

        // Ensure fee is within min/max boundaries
        $totalFee = max($totalFee, $payoutMethod->minimum_charge);
        $totalFee = min($totalFee, $payoutMethod->maximum_charge);

        return [
            'float_fee' => $floatFee,
            'fixed_fee' => $fixedFee,
            'total_fee' => $totalFee
        ];
    }

    // Exchange rate adjustment
    private function applyExchangeRateFloat(float $rate, float $floatPercent): float
    {
        return round($rate - ($rate * ($floatPercent / 100)), 6);
    }

    // Live exchange rate fetch
    public function getLiveExchangeRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) return 1.0;

        return Cache::remember("exchange_rate_{$from}_{$to}", now()->addMinutes(30), 
            function () use ($from, $to) {
                $client = new Client();
                $apis = [
                    "https://min-api.cryptocompare.com/data/price" => ['fsym' => $from, 'tsyms' => $to],
                    // Uncomment if Coinbase API is working
                    // "https://api.coinbase.com/v2/exchange-rates" => ['currency' => $from]
                ];

                foreach ($apis as $url => $params) {
                    try {
                        $response = json_decode($client->get($url, ['query' => $params])->getBody(), true);
                        if (isset($response['Response']) && $response['Response'] === 'Error') {
                            Log::error("API Error: " . $response['Message']);
                            continue;
                        }
                        $rate = match(str_contains($url, 'cryptocompare')) {
                            true => $response[$to] ?? null,
                            false => $response['data']['rates'][$to] ?? null
                        };

                        if ($rate) return (float) $rate;
                    } catch (\Exception $e) {
                        Log::error("Exchange rate error: {$e->getMessage()}");
                    }
                }

                throw new \RuntimeException("Failed to fetch exchange rate for {$from}->{$to}");
            }
        );
    }

    // Compile final results
    private function compileResults(
        float $amount,
        array $fees,
        float $exchangeRate,
        float $adjustedRate,
        string $targetCurrency,
        PayoutMethods $payoutMethod,
        string $walletCurrency
    ): array {
        $amountInTarget = $amount * $adjustedRate;
        $totalAmount = $amountInTarget + $fees['total_fee'];

        // Calculate fees in payout method currency
        $feesInPayoutCurrency = [
            'float_fee' => round($fees['float_fee'] / $exchangeRate, 6),
            'fixed_fee' => round($fees['fixed_fee'] / $exchangeRate, 6),
            'total_fee' => round($fees['total_fee'] / $exchangeRate, 6)
        ];

        // Calculate debit amount and customer receive amount in both currencies
        $debitAmountInWalletCurrency = round($totalAmount * $exchangeRate, 6);
        $debitAmountInPayoutCurrency = round($totalAmount, 6);

        $customerReceiveAmountInWalletCurrency = round($amount, 6);
        $customerReceiveAmountInPayoutCurrency = round($amount / $exchangeRate, 6);

        return [
            'total_fee' => [
                'wallet_currency' => round($fees['total_fee'], 6),
                'payout_currency' => $feesInPayoutCurrency['total_fee']
            ],
            'total_amount' => [
                'wallet_currency' => $debitAmountInWalletCurrency,
                'payout_currency' => $debitAmountInPayoutCurrency
            ],
            'exchange_rate' => $exchangeRate,
            'adjusted_rate' => $adjustedRate,
            'target_currency' => $targetCurrency,
            'base_currencies' => explode(',', $payoutMethod->base_currency),
            'debit_amount' => [
                'wallet_currency' => $debitAmountInWalletCurrency,
                'payout_currency' => $debitAmountInPayoutCurrency
            ],
            'customer_receive_amount' => [
                'wallet_currency' => $customerReceiveAmountInWalletCurrency,
                'payout_currency' => $customerReceiveAmountInPayoutCurrency
            ],
            'fee_breakdown' => [
                'float' => [
                    'wallet_currency' => round($fees['float_fee'], 6),
                    'payout_currency' => $feesInPayoutCurrency['float_fee']
                ],
                'fixed' => [
                    'wallet_currency' => round($fees['fixed_fee'], 6),
                    'payout_currency' => $feesInPayoutCurrency['fixed_fee']
                ]
            ],
            "PayoutMethod" => $payoutMethod
        ];
    }
}