<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\PayinMethods;
use App\Models\payoutMethods;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Illuminate\Support\Facades\Log;

class ChargeWalletMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if ($request->has('amount')) {
                $user = $request->user();
                if ($request->has('payment_method_id')) {
                    // Withdrawal to beneficiary
                    $beneficiary = BeneficiaryPaymentMethod::whereId($request->payment_method_id)->first();
                    if (!$beneficiary) {
                        return get_error_response(['error' => 'Beneficiary not found']);
                    }

                    $payoutMethod = PayoutMethods::whereId($beneficiary->gateway_id)->first();
                    if (!$payoutMethod) {
                        return get_error_response(['error' => 'Invalid payout method selected']);
                    }

                    // ✅ Compute Exchange Rate (Inline)
                    $baseExchangeRate = $this->getLiveExchangeRate($request->debit_wallet, $beneficiary->currency);
                    if (!$baseExchangeRate || $baseExchangeRate <= 0) {
                        return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
                    }

                    $exchangeRateFloat = floatval($payoutMethod->exchange_rate_float ?? 0);
                    $finalExchangeRate = $baseExchangeRate - ($baseExchangeRate * $exchangeRateFloat / 100);

                    // ✅ Convert amount to beneficiary's currency
                    $convertedAmount = number_format($finalExchangeRate * floatval($request->amount), 4);

                    // ✅ Compute Transaction Fee (Inline)
                    $fixedFee = floatval($payoutMethod->fixed_fee ?? 0);
                    $percentageFee = floatval($payoutMethod->percentage_fee ?? 0);
                    $percentageCharge = ($percentageFee / 100) * floatval($request->amount);

                    $transactionFee = number_format($fixedFee + $percentageCharge, 4);

                    // ✅ Calculate total charge
                    $xtotal = $convertedAmount + $transactionFee;
                    $totalAmountInDebitCurrency = number_format($xtotal / $finalExchangeRate, 4);
                    $transactionFeeInDebitCurrency = number_format($transactionFee / $finalExchangeRate, 4);

                    // ✅ Store values in session
                    session([
                        'transaction_fee' => $transactionFee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_charged' => $xtotal,
                        'total_amount_charged_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);

                    // ✅ Validate allowed currencies
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($request->debit_wallet, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "The selected wallet is not supported for the selected gateway. Allowed currencies: " . $payoutMethod->base_currency
                        ], 400);
                    }

                    // ✅ Deduct from user's wallet
                    $chargeNow = debit_user_wallet(floatval($totalAmountInDebitCurrency * 100), $request->debit_wallet, "Payout transaction", [
                        'transaction_fee' => $transactionFee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_charged' => $xtotal,
                        'total_amount_charged_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);

                    if ($request->has('debug')) {
                        var_dump([
                            "exchange_rate" => $finalExchangeRate,
                            "transaction_fee" => $transactionFee,
                            "payout_amount" => $convertedAmount,
                            "total_amount_charged" => $xtotal,
                            "amount_to_be_charged" => $totalAmountInDebitCurrency,
                            "fee_breakdown" => [
                                "fixed_fee" => $fixedFee,
                                "percentage_fee" => $percentageCharge,
                                "final_transaction_fee" => $transactionFee,
                                "base_exchange_rate" => $baseExchangeRate,
                                "final_exchange_rate" => $finalExchangeRate
                            ]
                        ]);
                        session()->forget([
                            "fixed_fee",
                            "percentage_fee",
                            "total_charge",
                            "base_exchange_rate",
                            "final_exchange_rate"
                        ]);
                        exit;
                    }

                    if (!$chargeNow || isset($chargeNow['error'])) {
                        return get_error_response(['error' => 'Insufficient wallet balance']);
                    }
                }

                return $next($request);
            }

            return get_error_response(['error' => "Sorry, we're currently unable to process your transaction"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    /**
     * Get live exchange rate for currency conversion.
     *
     * @param string $from
     * @param string $to
     * @return float
     */
    private function getLiveExchangeRate($from, $to)
    {
        if ($from === $to) return 1.0; // Same currency

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
