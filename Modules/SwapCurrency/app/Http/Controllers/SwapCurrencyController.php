<?php

namespace Modules\SwapCurrency\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TransactionRecord;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SwapCurrencyController extends Controller
{
    /**
     * Initiate a currency swap.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function initiateSwap(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'from_currency' => 'required|string',
            'to_currency' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return get_error_response(['error' => $validate->errors()->toArray()]);
        }

        $validatedData = $validate->validated();

        // Call a third-party API or your own service to get the exchange rate
        $exchangeRate = $this->getExchangeRate($validatedData['from_currency'], $validatedData['to_currency']);

        if ($exchangeRate === null) {
            return response()->json(['error' => 'Failed to retrieve exchange rate.'], 500);
        }

        $convertedAmount = $validatedData['amount'] * $exchangeRate;

        // Perform the currency swap logic here
        // You can deduct the amount from the user's wallet in the 'from_currency'
        // and credit the converted amount to the user's wallet in the 'to_currency'

        return get_success_response([
            'from_currency' => $validatedData['from_currency'],
            'to_currency' => $validatedData['to_currency'],
            'amount' => $validatedData['amount'],
            'converted_amount' => $convertedAmount,
            'exchange_rate' => $exchangeRate,
        ]);
    }


    /**
     * Initiate a currency swap.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processSwap(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'from_currency' => 'required|string',
            'to_currency' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return get_error_response(['error' => $validate->errors()->toArray()]);
        }

        $validatedData = $validate->validated();

        // Call a third-party API or your own service to get the exchange rate
        $exchangeRate = $this->getExchangeRate($validatedData['from_currency'], $validatedData['to_currency']);

        if ($exchangeRate === null) {
            return response()->json(['error' => 'Failed to retrieve exchange rate.'], 500);
        }

        $user = $request->user();

        $fromWallet = $user->getWallet($request->from_currency);
        $toWallet = $user->getWallet($request->to_currency);

        if (!$fromWallet or !$toWallet) {
            return get_error_response(['error' => "Invalid currencies supplied"]);
        }

        $convertedAmount = $validatedData['amount'] * $exchangeRate;
        $convertedAmount = $convertedAmount * 100;
        if ($fromWallet->Withdraw($request->amount * 100)) {
            $process = $toWallet->deposit($convertedAmount, ["message" => "Currency swap from $fromWallet->slug to $toWallet->slug"]);
            if ($process) {
                $swap_data = [
                    'from_currency' => $validatedData['from_currency'],
                    'to_currency' => $validatedData['to_currency'],
                    'amount' => $validatedData['amount'],
                    'converted_amount' => $convertedAmount,
                    'exchange_rate' => $exchangeRate,
                ];

                TransactionRecord::create([
                    "user_id" => auth()->id(),
                    "transaction_beneficiary_id" => $user->id,
                    "transaction_id" => generate_uuid(),
                    "transaction_amount" => $validatedData['amount'] * 100,
                    "gateway_id" => "currency_swap",
                    "transaction_status" => "success",
                    "transaction_type" => 'currency_swap',
                    "transaction_memo" => "currency swap",
                    "swap_to_currency" => $validatedData['to_currency'], 
                    "swap_from_currency" => $validatedData['from_currency'], 
                    "transaction_purpose" => request()->transaction_purpose ?? "Currency swap from {$validatedData['from_currency']} to {$validatedData['to_currency']}",
                    "transaction_payin_details" => null,
                    "transaction_payout_details" => null,
                    "transaction_swap_details" => $swap_data,
                    "customer_id" => $request->customer_id ?? null
                ]);

                return get_success_response($swap_data);
            }
        }

        return get_error_response(['error' => 'Unable to process your exchange, please contact support']);
    }

    /**
     * Get the exchange rate between two currencies.
     *
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float|null
     */
    private function getExchangeRate(string $fromCurrency, string $toCurrency)
    {
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}";
        
        return Cache::remember($cacheKey, 5, function() use ($fromCurrency, $toCurrency) {
            $rate = Http::get("https://min-api.cryptocompare.com/data/price?fsym={$fromCurrency}&tsyms={$toCurrency}")->json();
            
            if (isset($rate[$toCurrency]) && $rate[$toCurrency] > 0) {
                return $rate[$toCurrency];
            }
            
            return 0;
        });
    }
}
