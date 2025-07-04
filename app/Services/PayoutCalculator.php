<?php

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
            $targetCurrency = $beneficiary->currency;
        }

        $payoutMethod = PayoutMethods::findOrFail($gatewayId);
        $user = auth()->user();

        if (!$user->hasActiveSubscription()) {
            $newPlan = PlanModel::findOrFail(1);
            $user->upgradeCurrentPlanTo($newPlan, $newPlan->duration, false, true);
        }

        $subscription = $user->activeSubscription();
        $user_plan = (int) $subscription->plan_id;

        $float_charge = $payoutMethod->float_charge;
        $fixed_charge = $payoutMethod->fixed_charge;

        $customPricing = CustomPricing::where('user_id', $user->id)
            ->where('gateway_id', $payoutMethod->id)
            ->first();

        if (!$customPricing) {
            if ($user_plan === 2) {
                $fixed_charge = $payoutMethod->pro_fixed_charge;
                $float_charge = $payoutMethod->pro_float_charge;
            }
        } else {
            $fixed_charge = $customPricing->fixed_charge;
            $float_charge = $customPricing->float_charge;
        }

        $rates = $this->getExchangeRates($walletCurrency, $targetCurrency);

        $fees = $this->calculateFees(
            $amount,
            $walletCurrency,
            $targetCurrency,
            $float_charge,
            $fixed_charge,
            $payoutMethod
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

    private function getExchangeRates(string $walletCurrency, string $targetCurrency): array
    {
        if ($walletCurrency === $targetCurrency) {
            return [
                'wallet_to_usd' => 1,
                'usd_to_target' => 1,
                'wallet_to_target' => 1
            ];
        }

        return [
            'wallet_to_usd' => $walletCurrency === 'USD'
                ? 1.0
                : $this->getLiveExchangeRate($walletCurrency, 'USD'),

            'usd_to_target' => $targetCurrency === 'USD'
                ? 1.0
                : $this->getLiveExchangeRate('USD', $targetCurrency),

            'wallet_to_target' => $walletCurrency === $targetCurrency
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
        PayoutMethods $payoutMethod
    ): array {
        $rates = $this->getExchangeRates($walletCurrency, $targetCurrency);
        $usdToTarget = $rates['usd_to_target'];

        // ✅ Correct float fee calculation
        $floatFee = ($floatPercent / 100) * $amount;

        // ✅ If from_currency is USD, do not convert fixed fee
        if (strtoupper($walletCurrency) === 'USD') {
            $fixedFee = $fixedFeeUSD;
        } else {
            $fixedFee = $fixedFeeUSD * $usdToTarget;
        }

        $totalFee = $floatFee + $fixedFee;

        // ✅ Enforce min/max using USD equivalent
        $totalFeeUSD = $totalFee / $usdToTarget;

        if ($payoutMethod->minimum_charge && $totalFeeUSD < $payoutMethod->minimum_charge) {
            $totalFee = $payoutMethod->minimum_charge * $usdToTarget;
        }

        if ($payoutMethod->maximum_charge && $totalFeeUSD > $payoutMethod->maximum_charge) {
            $totalFee = $payoutMethod->maximum_charge * $usdToTarget;
        }

        return [
            'float_fee' => round($floatFee, 4),
            'fixed_fee' => round($fixedFee, 4),
            'total_fee' => round($totalFee, 4),
        ];
    }


    private function applyExchangeRateFloat(float $rate, float $floatPercent): float
    {
        return max(round($rate - ($rate * ($floatPercent / 100)), 6), 0.0001);
    }

    public function getLiveExchangeRate(string $from, string $to): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) return 1.0;

        return Cache::remember(
            "exchange_rate_{$from}_{$to}",
            now()->addMinutes(30),
            function () use ($from, $to) {
                $client = new Client();
                $apis = [
                    "https://api.coinbase.com/v2/exchange-rates" => ['currency' => $from],
                    "https://min-api.cryptocompare.com/data/price" => ['fsym' => $from, 'tsyms' => $to],
                    "https://api.coinbase.com/v2/prices/{$from}-{$to}/spot"
                ];

                foreach ($apis as $url => $params) {
                    try {
                        $response = json_decode($client->get($url, ['query' => $params])->getBody(), true);
                        if (isset($response['Response']) && $response['Response'] === 'Error') {
                            Log::error("API Error: " . $response['Message']);
                            continue;
                        }

                        $rate = match (str_contains($url, 'cryptocompare')) {
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

        $debitAmountInWalletCurrency = round($totalAmount * $exchangeRate, 6);
        $debitAmountInPayoutCurrency = round($totalAmount, 6);

        $customerReceiveAmountInWalletCurrency = round($amount, 6);
        $customerReceiveAmountInPayoutCurrency = round($amount / $exchangeRate, 6);

        $fees['total_fee'] = number_format($fees['fixed_fee'] + $fees['float_fee'], 4);

        return [
            'total_fee' => [
                'payout_currency' => round($fees['total_fee'], 4),
                'wallet_currency' => round($fees['total_fee'] * $exchangeRate, 6)
            ],
            'total_amount' => [
                'wallet_currency' => $customerReceiveAmountInWalletCurrency + round($fees['total_fee'] * $exchangeRate, 6),
                'payout_currency' => $debitAmountInPayoutCurrency
            ],
            'amount_due' => $customerReceiveAmountInWalletCurrency + round($fees['total_fee'] * $exchangeRate, 6),
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
                'payout_currency' => $customerReceiveAmountInWalletCurrency * $adjustedRate,
            ],
            'fee_breakdown' => [
                'float' => [
                    'wallet_currency' => number_format($fees['float_fee'] * $exchangeRate, 4),
                    'payout_currency' => number_format($fees['float_fee'], 4)
                ],
                'fixed' => [
                    'wallet_currency' => number_format($fees['fixed_fee'] * $exchangeRate, 4),
                    'payout_currency' => number_format($fees['fixed_fee'], 4)
                ],
                'total' => number_format($fees['total_fee'], 4),
                'total_in_from_currency' => number_format($fees['float_fee'] * $exchangeRate, 4) + number_format($fees['fixed_fee'] * $exchangeRate, 4),
                'total_in_to_currency' => number_format($fees['fixed_fee'] + $fees['float_fee'], 4),
            ],
            "PayoutMethod" => $payoutMethod
        ];
    }
}





// use GuzzleHttp\Client;
// use Illuminate\Support\Facades\Cache;
// use Illuminate\Support\Facades\Log;
// use App\Models\CustomPricing;
// use App\Models\payoutMethods as PayoutMethods;
// use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
// use Creatydev\Plans\Models\PlanModel;

// class PayoutCalculator
// {
//     public function calculate(
//         float $amount,
//         string $walletCurrency,
//         int $paymentMethodId,
//         float $exchangeRateFloat = 0
//     ): array {
//         $request = request();

//         if ($request->has('method_id') && !empty($request->method_id)) {
//             // Direct gateway mode
//             $gatewayId = $paymentMethodId;
//             $targetCurrency = strtoupper($request->to_currency);
//             $walletCurrency = strtoupper($request->from_currency);
//         } else {
//             // Beneficiary mode
//             $beneficiary = BeneficiaryPaymentMethod::findOrFail($paymentMethodId);
//             $gatewayId = $beneficiary->gateway_id;
//             $targetCurrency = $beneficiary->currency;
//         }

//         $payoutMethod = PayoutMethods::findOrFail($gatewayId);
//         $user = auth()->user();

//         if (!$user->hasActiveSubscription()) {
//             $newPlan = PlanModel::findOrFail(1);
//             $user->upgradeCurrentPlanTo($newPlan, $newPlan->duration, false, true);
//         }

//         $subscription = $user->activeSubscription();
//         $user_plan = (int) $subscription->plan_id;

//         $float_charge = $payoutMethod->float_charge;
//         $fixed_charge = $payoutMethod->fixed_charge;

//         if ($user_plan === 3) {
//             $customPricing = CustomPricing::where('user_id', $user->id)
//                 ->where('gateway_id', $payoutMethod->id)
//                 ->first();

//             if (!$customPricing) {
//                 $user_plan = 2;
//             } else {
//                 $fixed_charge = $customPricing->fixed_charge;
//                 $float_charge = $customPricing->float_charge;
//             }
//         }

//         if ($user_plan === 1 || $user_plan === 2) {
//             $fixed_charge = $user_plan === 1 ? $payoutMethod->fixed_charge : $payoutMethod->pro_fixed_charge;
//             $float_charge = $user_plan === 1 ? $payoutMethod->float_charge : $payoutMethod->pro_float_charge;
//         }

//         $rates = $this->getExchangeRates($walletCurrency, $targetCurrency);

//         $fees = $this->calculateFees(
//             $amount,
//             $walletCurrency,
//             $targetCurrency,
//             $float_charge,
//             $fixed_charge,
//             $payoutMethod,
//             $rates
//         );

//         $adjustedRate = $this->applyExchangeRateFloat(
//             $rates['wallet_to_target'],
//             $payoutMethod->exchange_rate_float
//         );

//         return $this->compileResults(
//             $amount,
//             $fees,
//             $rates['wallet_to_target'],
//             $adjustedRate,
//             $targetCurrency,
//             $payoutMethod,
//             $walletCurrency
//         );
//     }

//     private function getExchangeRates(string $walletCurrency, string $targetCurrency): array
//     {
//         if ($walletCurrency === $targetCurrency) {
//             return [
//                 'wallet_to_usd' => 1,
//                 'usd_to_target' => 1,
//                 'wallet_to_target' => 1
//             ];
//         }
//         return [
//             'wallet_to_usd' => $walletCurrency === 'USD'
//                 ? 1.0
//                 : $this->getLiveExchangeRate('USD', $walletCurrency),

//             'usd_to_target' => $this->getLiveExchangeRate('USD', $targetCurrency),
//             'wallet_to_target' => $walletCurrency === 'USD' && $targetCurrency === 'USD'
//                 ? 1.0
//                 : $this->getLiveExchangeRate($walletCurrency, $targetCurrency)
//         ];
//     }

//     private function calculateFees(
//         float $amount,
//         string $walletCurrency,
//         string $targetCurrency,
//         float $floatPercent,
//         float $fixedFeeUSD,
//         PayoutMethods $payoutMethod,
//         array $rates
//     ): array {
//         $amountUSD = $amount / $rates['wallet_to_usd'];

//         $floatFee = $amountUSD * ($floatPercent / 100) * $rates['usd_to_target'];
//         $fixedFee = $fixedFeeUSD * $rates['usd_to_target'];

//         $totalFee = $floatFee + $fixedFee;

//         // Convert min/max from USD to payout currency
//         $minChargeInTarget = $payoutMethod->minimum_charge * $rates['usd_to_target'];
//         $maxChargeInTarget = $payoutMethod->maximum_charge * $rates['usd_to_target'];

//         if ($totalFee < $minChargeInTarget) {
//             $totalFee = $minChargeInTarget;
//         }

//         if ($totalFee > $maxChargeInTarget) {
//             $totalFee = $maxChargeInTarget;
//         }

//         return [
//             'float_fee' => round($floatFee, 2),
//             'fixed_fee' => round($fixedFee, 2),
//             'total_fee' => round($totalFee, 2),
//             'min_charge_in_target' => round($minChargeInTarget, 2),
//             'max_charge_in_target' => round($maxChargeInTarget, 2),
//         ];
//     }

//     private function applyExchangeRateFloat(float $rate, float $floatPercent): float
//     {
//         return round($rate - ($rate * ($floatPercent / 100)), 6);
//     }

//     public function getLiveExchangeRate(string $from, string $to): float
//     {
//         $from = strtoupper($from);
//         $to = strtoupper($to);

//         if ($from === $to) return 1.0;

//         return Cache::remember(
//             "exchange_rate_{$from}_{$to}",
//             now()->addMinutes(30),
//             function () use ($from, $to) {
//                 $client = new Client();
//                 $apis = [
//                     "https://min-api.cryptocompare.com/data/price" => ['fsym' => $from, 'tsyms' => $to],
//                     "https://api.coinbase.com/v2/exchange-rates" => ['currency' => $from]
//                 ];

//                 foreach ($apis as $url => $params) {
//                     try {
//                         $response = json_decode($client->get($url, ['query' => $params])->getBody(), true);

//                         if (isset($response['Response']) && $response['Response'] === 'Error') {
//                             Log::error("API Error: " . $response['Message']);
//                             continue;
//                         }

//                         $rate = str_contains($url, 'cryptocompare')
//                             ? ($response[$to] ?? null)
//                             : ($response['data']['rates'][$to] ?? null);

//                         if ($rate) {
//                             return (float) $rate;
//                         }
//                     } catch (\Exception $e) {
//                         Log::error("Exchange rate error: {$e->getMessage()}");
//                     }
//                 }

//                 throw new \RuntimeException("Failed to fetch exchange rate for {$from}->{$to}");
//             }
//         );
//     }

//     private function compileResults(
//         float $amount,
//         array $fees,
//         float $exchangeRate,
//         float $adjustedRate,
//         string $targetCurrency,
//         PayoutMethods $payoutMethod,
//         string $walletCurrency
//     ): array {
//         if (strtolower($payoutMethod->currency) === strtolower(request()->debit_wallet)) {
//             $adjustedRate = 1;
//         }

//         $amountInTarget = $amount * $adjustedRate;
//         $totalAmount = $amountInTarget + $fees['total_fee'];

//         $feesInPayoutCurrency = [
//             'float_fee' => round($fees['float_fee'] / $exchangeRate, 6),
//             'fixed_fee' => round($fees['fixed_fee'] / $exchangeRate, 6),
//             'total_fee' => round($fees['total_fee'] / $exchangeRate, 6)
//         ];

//         // Convert min/max again for final enforcement (for display or last adjustment)
//         $minChargeInPayoutCurrency = $payoutMethod->minimum_charge * $this->getLiveExchangeRate('USD', $targetCurrency);
//         $maxChargeInPayoutCurrency = $payoutMethod->maximum_charge * $this->getLiveExchangeRate('USD', $targetCurrency);

//         $total_fee_due = $feesInPayoutCurrency['float_fee'] + $feesInPayoutCurrency['fixed_fee'];

//         if ($total_fee_due < $minChargeInPayoutCurrency) {
//             $total_fee_due = $minChargeInPayoutCurrency;
//         } elseif ($total_fee_due > $maxChargeInPayoutCurrency) {
//             $total_fee_due = $maxChargeInPayoutCurrency;
//         }

//         $debitAmountInWalletCurrency = round($totalAmount * $exchangeRate, 6);
//         $debitAmountInPayoutCurrency = round($totalAmount, 6);

//         $customerReceiveAmountInWalletCurrency = round($amount, 6);
//         $customerReceiveAmountInPayoutCurrency = round($amount / $exchangeRate, 6);

//         return [
//             'total_fee' => [
//                 'payout_currency' => $total_fee_due,
//                 'wallet_currency' => round($fees['total_fee'], 6)
//             ],
//             'total_amount' => [
//                 'wallet_currency' => $customerReceiveAmountInWalletCurrency + $total_fee_due,
//                 'payout_currency' => $debitAmountInWalletCurrency / $adjustedRate
//             ],
//             "amount_due" => $customerReceiveAmountInWalletCurrency + $total_fee_due,
//             'exchange_rate' => $exchangeRate,
//             'adjusted_rate' => $adjustedRate,
//             'target_currency' => $targetCurrency,
//             'base_currencies' => explode(',', $payoutMethod->base_currency),
//             'debit_amount' => [
//                 'wallet_currency' => $debitAmountInWalletCurrency / $adjustedRate,
//                 'payout_currency' => $debitAmountInPayoutCurrency
//             ],
//             'customer_receive_amount' => [
//                 'wallet_currency' => $customerReceiveAmountInWalletCurrency,
//                 'payout_currency' => $customerReceiveAmountInWalletCurrency * $adjustedRate,
//             ],
//             'fee_breakdown' => [
//                 'float' => [
//                     'wallet_currency' => round($fees['float_fee'], 6),
//                     'payout_currency' => $feesInPayoutCurrency['float_fee']
//                 ],
//                 'fixed' => [
//                     'wallet_currency' => round($fees['fixed_fee'], 6),
//                     'payout_currency' => $feesInPayoutCurrency['fixed_fee']
//                 ],
//                 'total' => $total_fee_due,
//             ],
//             "PayoutMethod" => $payoutMethod
//         ];
//     }
// }
