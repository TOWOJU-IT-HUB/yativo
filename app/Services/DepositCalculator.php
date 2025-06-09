<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DepositCalculator
{
    protected $gateway;

    public function __construct(array $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * Get adjusted exchange rate from FROM → TO currency, with markup
     */
    public function getAdjustedExchangeRate(string $fromCurrency, string $toCurrency): float
    {
        // If same currency, rate is 1
        if ($fromCurrency === $toCurrency) {
            return 1.0;
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

        return round($adjustedRate, 6); // higher precision is safer
    }

    /**
     * Calculate deposit breakdown
     */
    public function calculate(float $depositAmount): array
    {
        // Define currencies
        $payinCurrency = request('currency'); // e.g. 'CLP'
        $walletCurrency = $this->gateway['currency']; // e.g. 'USD'

        // Get correct exchange rates
        $payinToWalletRate = $this->getAdjustedExchangeRate($payinCurrency, $walletCurrency);

        // Convert deposit amount to wallet currency (USD for example)
        $depositAmountInWallet = $depositAmount * $payinToWalletRate;

        // Load fee settings
        $floatChargeRate = $this->gateway['float_charge'] ?? 0;          // % fee
        $fixedChargeUSD = $this->gateway['fixed_charge'] ?? 0;           // fixed fee in USD
        $minChargeUSD    = $this->gateway['minimum_charge'] ?? null;     // min fee in USD
        $maxChargeUSD    = $this->gateway['maximum_charge'] ?? null;     // max fee in USD

        // Calculate fees (in wallet currency, i.e. USD)
        $percentageFeeUSD = $depositAmountInWallet * ($floatChargeRate / 100);
        $fixedFeeUSD      = $fixedChargeUSD;

        $totalFeeUSD = $percentageFeeUSD + $fixedFeeUSD;

        // Apply min/max fee boundaries (all in USD)
        if ($minChargeUSD !== null && $totalFeeUSD < $minChargeUSD) {
            $totalFeeUSD = $minChargeUSD;
        }
        if ($maxChargeUSD !== null && $totalFeeUSD > $maxChargeUSD) {
            $totalFeeUSD = $maxChargeUSD;
        }

        // Calculate credited amount in wallet currency (USD)
        $creditedAmountUSD = $depositAmountInWallet - $totalFeeUSD;

        // For display: if needed, convert total fee back to payin currency (CLP) for user to see
        $walletToPayinRate = $this->getAdjustedExchangeRate($walletCurrency, $payinCurrency);
        $totalFeeInPayinCurrency = $totalFeeUSD * $walletToPayinRate;

        return [
            'payin_currency'  => $payinCurrency,
            'wallet_currency' => $walletCurrency,

            'deposit_amount' => round($depositAmount, 2), // original payin currency
            'deposit_amount_in_wallet_currency' => round($depositAmountInWallet, 2), // converted to wallet currency

            'fixed_fee_usd'     => round($fixedFeeUSD, 2),
            'float_fee_usd'     => round($percentageFeeUSD, 2),
            'total_fees_usd'    => round($totalFeeUSD, 2),
            'total_fees_in_payin_currency' => round($totalFeeInPayinCurrency, 2), // optional for showing to user

            'credited_amount_usd' => round($creditedAmountUSD, 2), // credited in wallet currency

            'exchange_rate_payin_to_wallet' => $payinToWalletRate,
            'exchange_rate_wallet_to_payin' => $walletToPayinRate,
        ];
    }
}



// namespace App\Services;

// use App\Models\PayinMethods;
// use Illuminate\Support\Facades\Http;

// class DepositCalculator
// {
//     protected $gateway;

//     public function __construct(array $gateway)
//     {
//         $this->gateway = $gateway;
//     }

//     /**
//      * Get adjusted USD to quote currency rate with markup
//      */
//     // public function getAdjustedExchangeRate(): float
//     // {
//     //     $currency = $this->gateway['currency'];
//     //     $floatMarkup = $this->gateway['exchange_rate_float'] ?? 0;

//     //     $response = Http::get('https://min-api.cryptocompare.com/data/price', [
//     //         'fsym' => 'USD',
//     //         'tsyms' => $currency
//     //     ]);

//     //     if (!$response->ok() || !isset($response[$currency])) {
//     //         throw new \Exception("Unable to retrieve exchange rate.");
//     //     }

//     //     $rawRate = (float) $response[$currency];
//     //     $adjustedRate = $rawRate * (1 + ($floatMarkup / 100));
        
//     //     return round($adjustedRate, 4);
//     // }

//     public function getAdjustedExchangeRate(string $fromCurrency, string $toCurrency): float
//     {
//         $floatMarkup = $this->gateway['exchange_rate_float'] ?? 0;

//         $response = Http::get('https://min-api.cryptocompare.com/data/price', [
//             'fsym' => $fromCurrency,
//             'tsyms' => $toCurrency
//         ]);

//         if (!$response->ok() || !isset($response[$toCurrency])) {
//             throw new \Exception("Unable to retrieve exchange rate.");
//         }

//         $rawRate = (float) $response[$toCurrency];
//         $adjustedRate = $rawRate * (1 + ($floatMarkup / 100));
        
//         return round($adjustedRate, 6); // Higher precision is better here
//     }


//     /**
//      * Calculate deposit breakdown
//      */
//     public function calculate(float $depositAmount): array
//     {
//         $adjustedRate = $this->getAdjustedExchangeRate($this->gateway['currency'], request('currency'));

//         $floatChargeRate = $this->gateway['float_charge'] ?? 0;
//         $fixedChargeUSD = $this->gateway['fixed_charge'] ?? 0;

//         $percentageFee = $depositAmount * floatval($floatChargeRate / 100);
//         $fixedFeeInQuote = $fixedChargeUSD * $adjustedRate;
//         $totalFees = $percentageFee + $fixedFeeInQuote;

//         // Convert min and max charges from USD to quote currency
//         $minChargeInQuote = isset($this->gateway['minimum_charge']) ? $this->gateway['minimum_charge'] * $adjustedRate : null;
//         $maxChargeInQuote = isset($this->gateway['maximum_charge']) ? $this->gateway['maximum_charge'] * $adjustedRate : null;

//         // Enforce min/max boundaries in quote currency
//         $totalFee = $totalFees;
//         if ($minChargeInQuote !== null && $totalFee < $minChargeInQuote) {
//             $totalFee = $minChargeInQuote;
//         }
//         if ($maxChargeInQuote !== null && $totalFee > $maxChargeInQuote) {
//             $totalFee = $maxChargeInQuote;
//         }

//         $creditedAmount = $depositAmount - $totalFee;

//         return [
//             'deposit_amount' => round($depositAmount, 2),
//             'fixed_fee' => round($fixedFeeInQuote, 2),
//             'float_fee' => round($percentageFee, 2),
//             'exchange_rate' => $adjustedRate,
//             'percentage_fee' => round($percentageFee, 2),
//             'fixed_fee_in_quote' => round($fixedFeeInQuote, 2),
//             'total_fees' => round($totalFee, 2),
//             'credited_amount' => round($creditedAmount, 2),
//         ];
//     }
// }
