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

                    // ✅ Get Live Exchange Rate
                    $exchangeRate = $this->getLiveExchangeRate($request->debit_wallet, $beneficiary->currency);
                    if (!$exchangeRate || $exchangeRate <= 0) {
                        return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
                    }

                    // ✅ Exchange Rate Float Adjustment
                    $exchangeRateFloat = floatval($payoutMethod->exchange_rate_float ?? 0) / 100;
                    $adjustedExchangeRate = round($exchangeRate - ($exchangeRate * $exchangeRateFloat), 6);

                    // ✅ Compute Transaction Fee
                    $gatewayFloatCharge = floatval($payoutMethod->float_charge ?? 0) / 100;
                    $gatewayFixedCharge = floatval($payoutMethod->fixed_charge ?? 0);

                    $floatFee = round($gatewayFloatCharge * $exchangeRate, 6);
                    $fixedCharge = round($gatewayFixedCharge * $exchangeRate, 6);
                    $transactionFee = round($floatFee + $fixedCharge, 6);

                    // ✅ Compute Total Amount Due
                    $amount = floatval($request->amount);
                    $totalAmountDue = round(($amount * $adjustedExchangeRate) + $fixedCharge, 6);

                    // ✅ Convert to Debit Wallet Currency
                    $totalAmountInDebitCurrency = number_format(floatval($totalAmountDue) / floatval($exchangeRate), 6);
                    $transactionFeeInDebitCurrency = round($transactionFee / $exchangeRate, 6);

                    // ✅ Store in Session
                    session([
                        'transaction_fee' => $transactionFee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_due' => $totalAmountDue,
                        'total_amount_due_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);

                    // ✅ Debug Mode - Dump All Parameters
                    if ($request->has('debug')) {
                        dd([
                            "exchange_rate" => $exchangeRate,
                            "exchange_rate_float (%)" => $exchangeRateFloat * 100,
                            "adjusted_exchange_rate" => $adjustedExchangeRate,
                            "gateway_float_charge (%)" => $gatewayFloatCharge * 100,
                            "gateway_fixed_charge" => $gatewayFixedCharge,
                            "float_fee" => $floatFee,
                            "fixed_charge" => $fixedCharge,
                            "transaction_fee" => $transactionFee,
                            "amount" => $amount,
                            "total_amount_due" => $totalAmountDue,
                            "total_amount_in_debit_currency" => $totalAmountInDebitCurrency,
                        ]);
                    }

                    // ✅ Validate Allowed Currencies
                    $allowedCurrencies = explode(',', $payoutMethod->base_currency ?? '');
                    if (!in_array($request->debit_wallet, $allowedCurrencies)) {
                        return get_error_response([
                            'error' => "The selected wallet is not supported for the selected gateway. Allowed currencies: " . $payoutMethod->base_currency
                        ], 400);
                    }

                    // ✅ Deduct from User's Wallet
                    $chargeNow = debit_user_wallet(floatval($totalAmountInDebitCurrency * 100), $request->debit_wallet, "Payout transaction", [
                        'transaction_fee' => $transactionFee,
                        'transaction_fee_in_debit_currency' => $transactionFeeInDebitCurrency,
                        'total_amount_due' => $totalAmountDue,
                        'total_amount_due_in_debit_currency' => $totalAmountInDebitCurrency
                    ]);

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

    private function getLiveExchangeRate($from, $to)
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
