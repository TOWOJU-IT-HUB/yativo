<?php

namespace App\Services;

use App\Models\PayinMethods;
use Illuminate\Support\Facades\Http;

class DepositCalculator
{
    protected $gateway;

    public function __construct(array $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get adjusted USD to quote currency rate with markup
     */
    public function getAdjustedExchangeRate(): float
    {
        $currency = $this->gateway['currency'];
        $floatMarkup = $this->gateway['exchange_rate_float'] ?? 0;

        $response = Http::get('https://min-api.cryptocompare.com/data/price', [
            'fsym' => 'USD',
            'tsyms' => $currency
        ]);

        if (!$response->ok() || !isset($response[$currency])) {
            throw new \Exception("Unable to retrieve exchange rate.");
        }

        $rawRate = (float) $response[$currency];
        $adjustedRate = $rawRate * (1 + ($floatMarkup / 100));
        
        return round($adjustedRate, 4);
    }

    /**
     * Calculate deposit breakdown
     */
    public function calculate(float $depositAmount): array
    {
        $request = request();
        if($request->has('from_currency') && $request->has('to_currency')) {
            // then the deposit currency is
            $depositCurrency = $request->to_currency;
        } else {
            $depositCurrency = $request->currency;
        }
        
        $adjustedRate = $this->getAdjustedExchangeRate();

        $floatChargeRate = $this->gateway['float_charge'] ?? 0;
        $fixedChargeUSD = $this->gateway['fixed_charge'] ?? 0;

        $percentageFee = $depositAmount * floatval($floatChargeRate / 100);
        $fixedFeeInQuote = $fixedChargeUSD * $adjustedRate;
        $totalFees = $percentageFee + $fixedFeeInQuote;

        // Convert min and max charges from USD to quote currency
        $minChargeInQuote = isset($this->gateway['minimum_charge']) ? $this->gateway['minimum_charge'] * $adjustedRate : null;
        $maxChargeInQuote = isset($this->gateway['maximum_charge']) ? $this->gateway['maximum_charge'] * $adjustedRate : null;

        // Enforce min/max boundaries in quote currency
        $totalFee = $totalFees;
        if ($minChargeInQuote !== null && $totalFee < $minChargeInQuote) {
            $totalFee = $minChargeInQuote;
        }
        if ($maxChargeInQuote !== null && $totalFee > $maxChargeInQuote) {
            $totalFee = $maxChargeInQuote;
        }

        $creditedAmount = $depositAmount - $totalFee;

        if(strtolower($depositCurrency) !== strtolower($this->gateway['currency'])) {
            $creditedAmount = $creditedAmount / $adjustedRate;
            // echo "testing mode";
        } elseif(strtolower($depositCurrency) === strtolower($this->gateway['currency'])) {
             $creditedAmount = $depositAmount - $totalFee;
        }

        return [
            'deposit_amount' => round($depositAmount, 2),
            'fixed_fee' => round($fixedFeeInQuote, 2),
            'float_fee' => round($percentageFee, 2),
            'exchange_rate' => $adjustedRate,
            'percentage_fee' => round($percentageFee, 2),
            'fixed_fee_in_quote' => round($fixedFeeInQuote, 2),
            'total_fees' => round($totalFee, 2),
            'credited_amount' => round($creditedAmount, 2),
        ];
    }
}