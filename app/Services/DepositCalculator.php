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
    public function getAdjustedExchangeRate($fromCurrency, $toCurrency): float
    {
        if($fromCurrency == $toCurrency) {
            return round(1, 6); // keep precision
        }

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
        $adjustedRate = $this->getAdjustedExchangeRate(request('currency'), $this->gateway['currency']);

        $floatChargeRate = $this->gateway['float_charge'] ?? 0;
        $fixedChargeUSD = $this->gateway['fixed_charge'] ?? 0;

        $percentageFee = $depositAmount * floatval($floatChargeRate / 100);
        $fixedFeeInQuote = $fixedChargeUSD * $adjustedRate;
        $totalFees = $percentageFee + $fixedFeeInQuote;

        // Enforce min/max boundaries
        $totalFee = $totalFees;
        if (isset($this->gateway['minimum_charge']) && $totalFee < $this->gateway['minimum_charge']) {
            $totalFee = $this->gateway['minimum_charge'];
        }
        if (isset($this->gateway['maximum_charge']) && $totalFee > $this->gateway['maximum_charge']) {
            $totalFee = $this->gateway['maximum_charge'];
        }

        $creditedAmount = $depositAmount - $totalFee;

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