<?php

namespace App\Services;

// use Illuminate\Support\Facades\Http;

// class DepositCalculator
// {
//     protected $gateway;

//     public function __construct(array $gateway)
//     {
//         $this->gateway = $gateway;
//     }

//     /**
//      * Get adjusted exchange rate from $fromCurrency to $toCurrency with markup
//      */
//     public function getAdjustedExchangeRate(string $fromCurrency, string $toCurrency): float
//     {
//         if($fromCurrency == $toCurrency) {
//             return round(1, 6); // keep precision
//         }
//         $floatMarkup = $this->gateway['exchange_rate_float'] ?? 0;

//         $response = Http::get('https://min-api.cryptocompare.com/data/price', [
//             'fsym' => strtoupper($fromCurrency),
//             'tsyms' => strtoupper($toCurrency)
//         ]);

//         if (!$response->ok() || !isset($response[$toCurrency])) {
//             throw new \Exception("Unable to retrieve exchange rate from $fromCurrency to $toCurrency.");
//         }

//         $rawRate = (float) $response[$toCurrency];
//         $adjustedRate = $rawRate * (1 + ($floatMarkup / 100));

//         return round($adjustedRate, 6); // keep precision
//     }

//     /**
//      * Calculate deposit fees and credited amounts.
//      *
//      * @param float $depositAmount Amount user is paying in payin currency
//      * @return array
//      */
//     public function calculate(float $depositAmount): array
//     {
//         $payinCurrency = strtoupper(request('currency'));         // e.g. 'USD' or 'CLP'
//         $walletCurrency = strtoupper($this->gateway['currency']); // e.g. 'CLP' or 'USD'

//         // Exchange rates
//         $payinToWalletRate = $this->getAdjustedExchangeRate($payinCurrency, $walletCurrency);
//         $walletToPayinRate = $this->getAdjustedExchangeRate($walletCurrency, $payinCurrency);

//         // Fees from gateway config (fixed, min, max) are in USD
//         $fixedFeeUSD = $this->gateway['fixed_charge'] ?? 0;
//         $minChargeUSD = $this->gateway['minimum_charge'] ?? null;
//         $maxChargeUSD = $this->gateway['maximum_charge'] ?? null;
//         $floatChargeRate = $this->gateway['float_charge'] ?? 0; // percentage fee

//         // Convert fixed/min/max fees USD → payin currency (because fees deducted from depositAmount in payin currency)
//         $fixedFeeInPayin = $fixedFeeUSD * $walletToPayinRate;
//         $minChargeInPayin = $minChargeUSD !== null ? $minChargeUSD * $walletToPayinRate : null;
//         $maxChargeInPayin = $maxChargeUSD !== null ? $maxChargeUSD * $walletToPayinRate : null;

//         // Percentage fee based on deposit amount in payin currency
//         $percentageFee = $depositAmount * ($floatChargeRate / 100);

//         // Calculate total fees in payin currency
//         $totalFee = $fixedFeeInPayin + $percentageFee;

//         // Enforce min/max fee boundaries
//         if ($minChargeInPayin !== null && $totalFee < $minChargeInPayin) {
//             $totalFee = $minChargeInPayin;
//         }
//         if ($maxChargeInPayin !== null && $totalFee > $maxChargeInPayin) {
//             $totalFee = $maxChargeInPayin;
//         }

//         // Amount credited to wallet in payin currency (after fees)
//         $creditedAmountInPayin = $depositAmount - $totalFee;

//         // Convert amounts to wallet currency
//         $depositAmountInWallet = $depositAmount * $payinToWalletRate;
//         $creditedAmountInWallet = $creditedAmountInPayin * $payinToWalletRate;

//         return [
//             'payin_currency'  => $payinCurrency,
//             'wallet_currency' => $walletCurrency,

//             'deposit_amount' => round($depositAmount, 2),                      // payin currency
//             'deposit_amount_in_wallet_currency' => round($depositAmountInWallet, 2),

//             'fixed_fee' => round($fixedFeeInPayin, 2),                         // payin currency
//             'float_fee' => round($percentageFee, 2),                           // payin currency
//             'total_fees' => round($totalFee, 2),                               // payin currency

//             'credited_amount' => round($creditedAmountInPayin, 2),             // payin currency
//             'credited_amount_in_wallet_currency' => round($creditedAmountInWallet, 2),

//             'exchange_rate' => round($payinToWalletRate, 6),

//             // Extra detailed fields for clarity (optional)
//             'fixed_fee_usd' => round($fixedFeeUSD, 2),
//             'minimum_charge_usd' => $minChargeUSD !== null ? round($minChargeUSD, 2) : null,
//             'maximum_charge_usd' => $maxChargeUSD !== null ? round($maxChargeUSD, 2) : null,
//             'percentage_fee_rate' => round($floatChargeRate, 2), // percentage
//         ];
//     }
// }



// namespace App\Services;

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
    // public function getAdjustedExchangeRate(): float
    // {
    //     $currency = $this->gateway['currency'];
    //     $floatMarkup = $this->gateway['exchange_rate_float'] ?? 0;

    //     $response = Http::get('https://min-api.cryptocompare.com/data/price', [
    //         'fsym' => 'USD',
    //         'tsyms' => $currency
    //     ]);

    //     if (!$response->ok() || !isset($response[$currency])) {
    //         throw new \Exception("Unable to retrieve exchange rate.");
    //     }

    //     $rawRate = (float) $response[$currency];
    //     $adjustedRate = $rawRate * (1 + ($floatMarkup / 100));
        
    //     return round($adjustedRate, 4);
    // }

    public function getAdjustedExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        if($fromCurrency == $toCurrency) {
            return round(1, 6); // keep precision
        }
        
        $floatMarkup = $this->gateway['exchange_rate_float'] ?? 0;

        $response = Http::get('https://min-api.cryptocompare.com/data/price', [
            'fsym' => $fromCurrency,
            'tsyms' => $toCurrency
        ]);

        if (!$response->ok() || !isset($response[$toCurrency])) {
            throw new \Exception("Unable to retrieve exchange rate.");
        }

        $rawRate = (float) $response[$toCurrency];
        $adjustedRate = $rawRate * (1 + ($floatMarkup / 100));
        
        return round($adjustedRate, 6); // Higher precision is better here
    }


    /**
     * Calculate deposit breakdown
     */
    public function calculate(float $depositAmount): array
    {
        $adjustedRate = $this->getAdjustedExchangeRate($this->gateway['currency'], request('currency'));

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
