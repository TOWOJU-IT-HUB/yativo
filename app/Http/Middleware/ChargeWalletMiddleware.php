<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\payoutMethods;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Illuminate\Support\Facades\Log;
use App\Services\PayoutCalculator;
use GuzzleHttp\Client;

class ChargeWalletMiddleware 
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $request->validate([
                "amount" => "required|numeric",
                "debit_wallet" => "required|string",
                "payment_method_id" => "required|integer",
            ]);

            $beneficiary = BeneficiaryPaymentMethod::find($request->payment_method_id);
    
            // Validate beneficiary and payment method
            if (!$beneficiary || !$beneficiary->gateway_id) {
                return get_error_response(['error' => "Invalid payment method configuration"]);
            }
    
            $payoutMethod = PayoutMethods::find($beneficiary->gateway_id);
            if (!$payoutMethod) {
                return get_error_response(['error' => "Unsupported withdrawal method"]);
            }
    
            $calculator = new PayoutCalculator();
            
            $result = $calculator->calculate(
                floatval($request->amount),
                $request->debit_wallet,
                $request->payment_method_id,
                floatval($request->exchange_rate_float ?? 0)
            );

            $validated = $request->all();
            $xchangeRate = $this->getExchangeRate($payoutMethod->currency, $request->debit_wallet);
            $comparedMinAmount = floatval($payoutMethod->minimum_withdrawal * $xchangeRate) + $result['total_fee']['payout_currency'];
            $comparedMaxAmount = floatval($payoutMethod->maximum_withdrawal * $xchangeRate) + $result['total_fee']['payout_currency'];
            // Validate withdrawal limits in DEBIT CURRENCY
            // convert the $payoutMethod->minimum_withdrawal to debit_wallet currency and compare the amount
            if ($validated['amount'] < $comparedMinAmount) {
                return get_error_response([
                    'error' => "Minimum withdrawal: " . number_format($comparedMinAmount, 2). " $request->debit_wallet"
                ]);
            }
    
            // convert the $payoutMethod->maximum_withdrawal to debit_wallet currency and compare the amount
            if ($validated['amount'] > $comparedMaxAmount) {
                return get_error_response([
                    'error' => "Minimum withdrawal: " . number_format($comparedMaxAmount, 2). " $request->debit_wallet"
                ]);
            }

            // Validate allowed currencies
            if (!in_array($request->debit_wallet, $result['base_currencies'])) {
                return get_error_response(['error' => 'Currency pair error. Supported are: '.explode(',', $result['base_currencies'])], 400);
            }

            // Debugging: Check the types of the values
            Log::debug('Debit Amount:', ['amount' => $result['amount_due']]);
            Log::debug('Amount:', ['amount' => $request->amount]);
            Log::debug('Exchange Rate:', ['rate' => $result['exchange_rate']]);

            $amount_due = $result['amount_due'];

            if ($request->has('debug')) {
                dd($result); exit;
            }

            // Deduct from wallet
            $chargeNow = debit_user_wallet(
                floatval($amount_due * 100),
                $request->debit_wallet,
                "Payout transaction",
                $result
            );

            if (!$chargeNow || isset($chargeNow['error'])) {
                return get_error_response(['error' => 'Insufficient wallet balance']);
            }

            return $next($request);

        } catch (\Throwable $th) {
            // Log the error or notify
            \Log::error("Error processing payout: ", ['message' => $th->getMessage(), 'trace' => $th->getTrace()]);
           
            // Safe check for chargeNow['amount_charged']
            if (isset($chargeNow) && (is_array($chargeNow) && isset($chargeNow['amount_charged']) || property_exists($chargeNow, 'amount_charged'))) {
                // Refund the user
                $user = auth()->user();
                $wallet = $user->getWallet($request->debit_wallet); 

                // Define a description for the refund
                $description = "Refund for failed payout transaction: " . (is_array($chargeNow) ? $chargeNow['transaction_id'] : $chargeNow->transaction_id); 
                
                // Credit the wallet back (refund)
                $refundResult = $wallet->credit(floatval(is_array($chargeNow) ? $chargeNow['amount_charged'] : $chargeNow->amount_charged), $description);

                // Check if the refund was successful
                if ($refundResult) {
                    return get_error_response(['error' => $th->getMessage()]);
                } else {
                    return get_error_response(['error' => $th->getMessage(), 'message' => 'Transaction failed and refund could not be processed.']);
                }
            }

            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    private function getExchangeRate($from_currency, $to_debit_wallet)
    {
    
        $from = strtoupper($from_currency);
        $to = strtoupper($to_debit_wallet);
        if ($from === $to) return 1.0;

        return cache()->remember("exchange_rate_{$from}_{$to}", now()->addMinutes(30), 
            function () use ($from, $to) {
                $client = new Client();
                $apis = [
                    "https://min-api.cryptocompare.com/data/price" => ['fsym' => $from, 'tsyms' => $to],
                    "https://api.coinbase.com/v2/exchange-rates" => ['currency' => $from]
                ];

                foreach ($apis as $url => $params) {
                    try {
                        $response = json_decode($client->get($url, ['query' => $params])->getBody(), true);
                        if (isset($response['Response']) && $response['Response'] === 'Error') {
                            Log::error("API Error: " . $response['Message']);
                            continue;
                        }
                        $rate = match(str_contains($url, 'cryptocompare')) {
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


}