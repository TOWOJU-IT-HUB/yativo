<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PayoutMethods;

class CalculateTransactionFees
{
    public function handle(Request $request, Closure $next)
    {
        $fromCurrency = $request->input('from_currency');
        $toCurrency = $request->input('to_currency');
        $amount = (float) $request->input('amount');

        // Fetch payout method details for the target currency
        $payoutMethod = PayoutMethods::where('currency', $toCurrency)->first();
        if (!$payoutMethod) {
            return response()->json(['error' => 'Invalid payout method'], 400);
        }

        // Retrieve fees stored in USD
        $fixedChargeUSD = (float) $payoutMethod->fixed_charge;
        $floatChargePercent = (float) $payoutMethod->float_charge; // As a decimal (e.g., 0.2 for 20%)

        // Get exchange rate
        $exchangeRate = $this->getExchangeRate($fromCurrency, $toCurrency); // Implement this function

        // Exchange rate float applies **only when currencies differ**
        $exchangeRateFloat = ($fromCurrency !== $toCurrency) ? (float) $payoutMethod->exchange_rate_float : 0;

        // Convert fixed fee & float fee from USD to target currency
        $convertedFixedCharge = $fixedChargeUSD * $exchangeRate;
        $convertedFloatFee = ($floatChargePercent * $exchangeRate) * $amount;

        // Adjust exchange rate by subtracting exchange rate float
        $adjustedExchangeRate = $exchangeRate - $exchangeRateFloat;

        // Calculate transaction fee and total amount due
        $transactionFee = $convertedFixedCharge + $convertedFloatFee;
        $convertedAmount = $amount * $adjustedExchangeRate;
        $totalAmountDue = $convertedAmount + $convertedFixedCharge;

        // Debugging: Dump full data if debug mode is enabled
        if ($request->has('debug')) {
            dd([
                "from_currency" => $fromCurrency,
                "to_currency" => $toCurrency,
                "exchange_rate" => $exchangeRate,
                "exchange_rate_float" => $exchangeRateFloat,
                "original_amount" => $amount,
                "float_charge_percent" => $floatChargePercent * 100, // Convert to percentage
                "fixed_charge_usd" => $fixedChargeUSD,
                "converted_fixed_charge" => $convertedFixedCharge,
                "converted_float_fee" => $convertedFloatFee,
                "adjusted_exchange_rate" => $adjustedExchangeRate,
                "transaction_fee" => $transactionFee,
                "converted_amount" => $convertedAmount,
                "total_amount_due" => $totalAmountDue,
            ]);
        }

        // Add computed values to the request for further processing
        $request->merge([
            'transaction_fee' => $transactionFee,
            'total_amount_due' => $totalAmountDue,
        ]);

        return $next($request);
    }

    private function getExchangeRate($from, $to)
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
                    Log::error("Error fetching exchange rate from $url: " . $e->getMessage());
                }
            }

            return 0;
        });
    }
}
