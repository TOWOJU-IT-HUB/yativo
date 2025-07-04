<?php
// app/Services/PayoutCalculator.php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Models\CustomPricing;
use App\Models\payoutMethods as PayoutMethods;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Creatydev\Plans\Models\PlanModel;
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
            $gatewayId = $paymentMethodId;
            $targetCurrency = strtoupper($request->to_currency);
            $walletCurrency = strtoupper($request->from_currency);
        } else {
            $beneficiary = BeneficiaryPaymentMethod::findOrFail($paymentMethodId);
            $gatewayId = $beneficiary->gateway_id;
            $targetCurrency = strtoupper($beneficiary->currency);
        }

        $payoutMethod = PayoutMethods::findOrFail($gatewayId);
        $user = auth()->user();

        if (!$user->hasActiveSubscription()) {
            $newPlan = PlanModel::findOrFail(1);
            $user->upgradeCurrentPlanTo($newPlan, $newPlan->duration, false, true);
        }

        $subscription = $user->activeSubscription();
        $user_plan = (int) $subscription->plan_id;

        $customPricing = CustomPricing::where('user_id', $user->id)
            ->where('gateway_id', $payoutMethod->id)
            ->first();

        if ($customPricing) {
            $fixed_charge = $customPricing->fixed_charge;
            $float_charge = $customPricing->float_charge;
        } else {
            $fixed_charge = $user_plan === 2 ? $payoutMethod->pro_fixed_charge : $payoutMethod->fixed_charge;
            $float_charge = $user_plan === 2 ? $payoutMethod->pro_float_charge : $payoutMethod->float_charge;
        }

        [$walletCurrency, $targetCurrency] = $this->normalizeCurrencyDirection(
            $walletCurrency,
            $targetCurrency,
            $payoutMethod->currency
        );

        $rates = $this->getExchangeRates($walletCurrency, $targetCurrency);

        $fees = $this->calculateFees(
            $amount,
            $walletCurrency,
            $targetCurrency,
            $float_charge,
            $fixed_charge,
            $payoutMethod,
            $rates
        );

        $adjustedRate = $this->applyExchangeRateFloat(
            $rates['wallet_to_target'],
            $payoutMethod->exchange_rate_float
        );

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

    private function normalizeCurrencyDirection(string $walletCurrency, string $targetCurrency, string $methodCurrency): array
    {
        $walletCurrency = strtoupper($walletCurrency);
        $targetCurrency = strtoupper($targetCurrency);
        $methodCurrency = strtoupper($methodCurrency);

        if ($walletCurrency === $methodCurrency) {
            [$walletCurrency, $targetCurrency] = [$targetCurrency, $walletCurrency];
        }

        return [$walletCurrency, $targetCurrency];
    }

    private function getExchangeRates(string $walletCurrency, string $targetCurrency): array
    {
        if ($walletCurrency === $targetCurrency) {
            return [
                'wallet_to_usd' => $walletCurrency === 'USD' ? 1.0 : $this->getLiveExchangeRate($walletCurrency, 'USD'),
                'usd_to_target' => 1.0,
                'wallet_to_target' => 1.0
            ];
        }

        return [
            'wallet_to_usd' => $walletCurrency === 'USD' ? 1.0 : $this->getLiveExchangeRate($walletCurrency, 'USD'),
            'usd_to_target' => $targetCurrency === 'USD' ? 1.0 : $this->getLiveExchangeRate('USD', $targetCurrency),
            'wallet_to_target' => $walletCurrency === 'USD' && $targetCurrency === 'USD'
                ? 1.0
                : $this->getLiveExchangeRate($walletCurrency, $targetCurrency)
        ];
    }

    private function calculateFees(
        float $amount,
        string $walletCurrency,
        string $targetCurrency,
        float $floatPercent,
        float $fixedFeeUSD,
        PayoutMethods $payoutMethod,
        array $rates
    ): array {
        $amountUSD = $walletCurrency === 'USD' ? $amount : $amount * $rates['wallet_to_usd'];

        $floatFee = $amountUSD * ($floatPercent / 100) * $rates['usd_to_target'];
        $fixedFee = $fixedFeeUSD * $rates['usd_to_target'];

        $totalFee = $floatFee + $fixedFee;
        $totalFee = max($totalFee, $payoutMethod->minimum_charge);
        $totalFee = min($totalFee, $payoutMethod->maximum_charge);

        return [
            'float_fee' => $floatFee,
            'fixed_fee' => $fixedFee,
            'total_fee' => $totalFee
        ];
    }

    private function applyExchangeRateFloat(float $rate, float $floatPercent): float
    {
        return round($rate - ($rate * ($floatPercent / 100)), 6);
    }

    public function getLiveExchangeRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) return 1.0;

        return Cache::remember("exchange_rate_{$from}_{$to}", now()->addMinutes(30), function () use ($from, $to) {
            $client = new Client();
            $apis = [
                "https://min-api.cryptocompare.com/data/price" => ['fsym' => $from, 'tsyms' => $to],
                "https://api.coinbase.com/v2/exchange-rates" => ['currency' => $from]
            ];

            foreach ($apis as $url => $params) {
                try {
                    $response = json_decode($client->get($url, ['query' => $params])->getBody(), true);
                    if (isset($response['Response']) && $response['Response'] === 'Error') {
                        Log::error("API Error: " . $response['Message']);
                        continue;
                    }
                    $rate = str_contains($url, 'cryptocompare')
                        ? ($response[$to] ?? null)
                        : ($response['data']['rates'][$to] ?? null);

                    if ($rate) return (float) $rate;
                } catch (\Exception $e) {
                    Log::error("Exchange rate error: {$e->getMessage()}");
                }
            }

            throw new \RuntimeException("Failed to fetch exchange rate for {$from}->{$to}");
        });
    }

    private function compileResults(
        float $amount,
        array $fees,
        float $exchangeRate,
        float $adjustedRate,
        string $targetCurrency,
        PayoutMethods $payoutMethod,
        string $walletCurrency
    ): array {
        if (strtolower($payoutMethod->currency) === strtolower(request()->debit_wallet)) {
            $adjustedRate = 1;
        }

        $amountInTarget = $amount * $adjustedRate;
        $totalAmount = $amountInTarget + $fees['total_fee'];

        $feesInPayoutCurrency = [
            'float_fee' => round($fees['float_fee'] / $exchangeRate, 6),
            'fixed_fee' => round($fees['fixed_fee'] / $exchangeRate, 6),
            'total_fee' => round($fees['total_fee'] / $exchangeRate, 6)
        ];

        $debitAmountInWalletCurrency = round($totalAmount * $exchangeRate, 6);
        $debitAmountInPayoutCurrency = round($totalAmount, 6);

        $customerReceiveAmountInWalletCurrency = round($amount, 6);
        $customerReceiveAmountInPayoutCurrency = round($amount * $adjustedRate, 6);

        $total_fee_due = $feesInPayoutCurrency['float_fee'] + $feesInPayoutCurrency['fixed_fee'];
        $total_fee_due = max($total_fee_due, $payoutMethod->minimum_charge);
        $total_fee_due = min($total_fee_due, $payoutMethod->maximum_charge);

        return [
            'total_fee' => [
                'payout_currency' => $total_fee_due,
                'wallet_currency' => round($fees['total_fee'], 6)
            ],
            'total_amount' => [
                'wallet_currency' => $customerReceiveAmountInWalletCurrency + $total_fee_due,
                'payout_currency' => $debitAmountInWalletCurrency / $adjustedRate
            ],
            'amount_due' => $customerReceiveAmountInWalletCurrency + $total_fee_due,
            'exchange_rate' => $exchangeRate,
            'adjusted_rate' => $adjustedRate,
            'target_currency' => $targetCurrency,
            'base_currencies' => explode(',', $payoutMethod->base_currency),
            'debit_amount' => [
                'wallet_currency' => $debitAmountInWalletCurrency / $adjustedRate,
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
                ],
                'total' => $total_fee_due
            ],
            "PayoutMethod" => $payoutMethod
        ];
    }
}
