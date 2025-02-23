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
                    $walletCurrency      = $request->debit_wallet;
                    $beneficiaryCurrency = $payoutMethod->currency;
                    $amount              = $request->amount;
                    $floatFeePercentage  = $payoutMethod->float_charge; // i.e. 0.2%
                    $fixedFeeUsd         = $payoutMethod->fixed_charge;

                    
                    $totalFeeInLocal = $this->calculateTotalFee(
                        $walletCurrency,
                        $beneficiaryCurrency,
                        $amount,
                        $floatFeePercentage,
                        $fixedFeeUsd
                    );

                    if($request->has('debug')) {
                        dd([
                            "walletCurrency" => $walletCurrency,
                            "beneficiaryCurrency" => $beneficiaryCurrency,
                            "amount" => $amount,
                            "floatFeePercentage" => $floatFeePercentage,
                            "fixedFeeUsd" => $fixedFeeUsd,
                            "EwUsd" => session()->get("EwUsd"),
                            "EusdLocal" => session()->get("EusdLocal"),
                            "totalFeeInLocal" => $totalFeeInLocal
                        ]);
                    }
                }

                return $next($request);
            }

            return get_error_response(['error' => "Sorry, we're currently unable to process your transaction"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }


    private function getLiveExchangeRate($from, $to)
    {
        // If both currencies are the same, rate = 1.0
        if ($from === $to) {
            return 1.0;
        }

        // Cache key to avoid repeated calls
        $cacheKey = "exchange_rate_{$from}_{$to}";

        return cache()->remember($cacheKey, now()->addMinutes(30), function () use ($from, $to) {
            $client = new \GuzzleHttp\Client();
            $apis = [
                "https://min-api.cryptocompare.com/data/price" => [
                    'query' => [
                        'fsym'  => $from,
                        'tsyms' => $to
                    ]
                ],
                "https://api.coinbase.com/v2/exchange-rates" => [
                    'query' => [
                        'currency' => $from
                    ]
                ],
            ];

            foreach ($apis as $url => $params) {
                try {
                    $response = json_decode($client->get($url, ['query' => $params])->getBody(), true);
                    $rate = ($url === "https://min-api.cryptocompare.com/data/price")
                        ? ($response[$to] ?? null)
                        : ($response['data']['rates'][$to] ?? null);

                    if ($rate) {
                        return (float) $rate;
                    }
                } catch (\Exception $e) {
                    Log::error("Error fetching exchange rate from $url: " . $e->getMessage());
                }
            }

            // Fallback if all APIs fail
            return 0;
        });
    }

    /**
     * Calculate the total fee in the beneficiary's local currency.
     *
     * Formula:
     *   Total Fee = (A / E_{W->USD}) * (R / 100) * E_{USD->Local} + (F * E_{USD->Local})
     *
     * Where:
     *   A = amount to be paid out in wallet currency
     *   R = floating fee percentage
     *   F = fixed fee in USD
     *   E_{W->USD}     = exchange rate from wallet currency to USD (if wallet is USD, set to 1)
     *   E_{USD->Local} = exchange rate from USD to beneficiary currency
     *
     * @param  string  $walletCurrency        e.g. "USD", "EUR", etc.
     * @param  string  $beneficiaryCurrency   e.g. "CLP", "EUR", etc.
     * @param  float   $amount                amount in the wallet currency
     * @param  float   $floatFeePercentage    floating fee percentage (e.g. 0.2% => 0.2)
     * @param  float   $fixedFeeUsd           fixed fee in USD
     * @return float                          total fee in the beneficiary's local currency
     */
    public function calculateTotalFee(
        string $walletCurrency,
        string $beneficiaryCurrency,
        float $amount,
        float $floatFeePercentage,
        float $fixedFeeUsd
    ): float {
        // 1. Get the exchange rate from wallet currency -> USD
        $EwUsd = $this->getLiveExchangeRate($walletCurrency, 'USD');

        // 2. Get the exchange rate from USD -> beneficiary's local currency
        $EusdLocal = $this->getLiveExchangeRate('USD', $beneficiaryCurrency);

        // Optional: Handle any failure case (e.g. if either rate is 0, return 0 or throw an exception)
        if ($EwUsd <= 0 || $EusdLocal <= 0) {
            Log::error("Invalid exchange rate(s): E_{W->USD}=$EwUsd, E_{USD->Local}=$EusdLocal");
            return 0.0;
        }

        // 3. Calculate the floating fee portion in local currency
        $floatFeeLocal = ($amount / $EwUsd) * ($floatFeePercentage / 100.0) * $EusdLocal;

        // 4. Calculate the fixed fee portion in local currency
        $fixedFeeLocal = $fixedFeeUsd * $EusdLocal;

        // 5. Total fee in local currency
        $totalFee = $floatFeeLocal + $fixedFeeLocal;


        session([
            "EwUsd" => $EwUsd,
            "EusdLocal" => $EusdLocal,
        ]);


        return $totalFee;
    }
}