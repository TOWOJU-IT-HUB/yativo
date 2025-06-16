<?php

namespace App\Services;

use App\Models\PayinMethods;
use Illuminate\Support\Facades\Http;
use Log;

class DepositCalculator
{
    protected $gateway;

    public function __construct(array $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get adjusted exchange rate with markup
     */
    public function getAdjustedExchangeRate($fromCurrency, $toCurrency): float
    {
        $floatMarkup = $this->gateway['exchange_rate_float'] ?? 0;

        Log::info("From currency is: {$fromCurrency} and to currency is {$toCurrency}");

        $response = Http::get('https://min-api.cryptocompare.com/data/price', [
            'fsym' => $fromCurrency,
            'tsyms' => $toCurrency
        ]);

        Log::info("Exchange rate response", [$response]);

        if (!$response->ok() || !isset($response[$toCurrency])) {
            throw new \Exception("Unable to retrieve exchange rate.");
        }

        $rawRate = (float) $response[$toCurrency];
        $adjustedRate = $rawRate * (1 + ($floatMarkup / 100));
        
        return round($adjustedRate, 4);
    }

    /**
     * Calculate deposit breakdown
     */
    public function calculate(float $depositAmount, $requestCurrency): array
    {
        $gatewayCurrency = $this->gateway['currency'];
        $fixedChargeUSD = $this->gateway['fixed_charge'] ?? 0;
        $floatChargeRate = $this->gateway['float_charge'] ?? 0;

        // If the request currency is the same as the gateway currency
        if ($requestCurrency === $gatewayCurrency) {
            $exchangeRate = 1;
            $fixedFeeInQuote = $fixedChargeUSD;
        } else {
            // Get the adjusted exchange rate
            $exchangeRate = $this->getAdjustedExchangeRate($requestCurrency, $gatewayCurrency);
            $fixedFeeInQuote = $fixedChargeUSD * $exchangeRate;
        }

        $percentageFee = $depositAmount * ($floatChargeRate / 100);
        $totalFees = $percentageFee + $fixedFeeInQuote;

        // Convert min and max charges from USD to quote currency
        $minChargeInQuote = isset($this->gateway['minimum_charge']) ? $this->gateway['minimum_charge'] * $exchangeRate : null;
        $maxChargeInQuote = isset($this->gateway['maximum_charge']) ? $this->gateway['maximum_charge'] * $exchangeRate : null;

        // Enforce min/max boundaries in quote currency
        if ($minChargeInQuote !== null && $totalFees < $minChargeInQuote) {
            $totalFees = $minChargeInQuote;
        }
        if ($maxChargeInQuote !== null && $totalFees > $maxChargeInQuote) {
            $totalFees = $maxChargeInQuote;
        }

        $creditedAmount = $depositAmount - $totalFees;

        return [
            'deposit_amount' => round($depositAmount, 2),
            'fixed_fee' => round($fixedFeeInQuote, 2),
            'float_fee' => round($percentageFee, 2),
            'exchange_rate' => $exchangeRate,
            'percentage_fee' => round($percentageFee, 2),
            'fixed_fee_in_quote' => round($fixedFeeInQuote, 2),
            'total_fees' => round($totalFees, 2),
            'credited_amount' => round($creditedAmount, 2),
        ];
    }
}