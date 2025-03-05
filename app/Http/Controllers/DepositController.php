<?php

namespace App\Http\Controllers;

use App\Jobs\Transaction;
use App\Models\CheckoutModel;
use App\Models\CurrencyList;
use App\Models\Deposit;
use App\Models\PayinMethods;
use App\Models\TransactionRecord;
use App\Services\DepositService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Log;

/**
 * Handles deposit related actions
 */
class DepositController extends Controller
{

    /**
     * Display a listing of the resource.
     *
     * @return 
     */
    public function index()
    {
        try {
            $request = request();
            $per_page = $request->per_page ?? per_page();
            $deposits = Deposit::whereUserId(active_user())->with(['depositGateway'])->latest()->paginate($per_page);
            return paginate_yativo($deposits);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Generate deposit link for selected payment gateway.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return 
     */
    public function store(Request $request)
    {
        try {
            $validate = Validator::make(
                $request->all(),
                [
                    'gateway' => 'required',
                    'amount' => 'required|numeric|min:0',
                    'currency' => 'required',
                    'credit_wallet' => 'sometimes'
                ]
            );

            if ($validate->fails()) {
                return get_error_response($validate->errors()->toArray());
            }

            $user = $request->user();

            // Validate credit_wallet and currency
            if ($request->has('credit_wallet')) {
                if (!$user->hasWallet($request->credit_wallet)) {
                    return get_error_response(['error' => "Invalid credit wallet selected"], 400);
                }
                $wallet = $user->getWallet($request->credit_wallet);
                if ($wallet->currency !== $request->currency) {
                    return get_error_response(['error' => "Credit wallet must be in currency {$request->currency}"], 400);
                }
            } else {
                $walletExists = $user->wallets()->where('currency', $request->currency)->exists();
                if (!$walletExists) {
                    return get_error_response(['error' => "No wallet found for currency {$request->currency}"], 400);
                }
            }

            $deposit_currency = $request->currency;

            $payin = PayinMethods::whereId($request->gateway)->firstOrFail();

            if ($payin->minimum_deposit > $request->amount) {
                return get_error_response(['error' => "Minimum deposit amount is {$payin->minimum_deposit} {$payin->currency}"], 400);
            }

            if ($payin->maximum_deposit < $request->amount) {
                return get_error_response(['error' => "Maximum deposit amount is {$payin->maximum_deposit} {$payin->currency}"], 400);
            }

            $exchange_rate = floatval($this->get_transaction_rate($payin->currency, $deposit_currency, $payin->id, "payin"));

            // Record deposit
            $deposit = new Deposit();
            $deposit->currency = $payin->currency;
            $deposit->deposit_currency = $deposit_currency;
            $deposit->user_id = active_user();
            $deposit->amount = $request->amount;
            $deposit->gateway = $request->gateway;
            $deposit->receive_amount = $request->amount * $exchange_rate;
            $transaction_fee = get_transaction_fee($request->gateway, $request->amount, 'deposit', "payin");

            if (!$payin) {
                return get_error_response(['error' => 'Invalid gateway']);
            }

            if ($deposit->save()) {
                $total_amount_due = round($request->amount + $transaction_fee, 4);
                $arr['payment_info'] = [
                    "send_amount" => round($request->amount, 4) . " " . strtoupper($payin->currency),
                    "receive_amount" => round($deposit->receive_amount, 4) . " " . strtoupper($deposit_currency),
                    "exchange_rate" => "1 " . strtoupper($payin->currency) . " = " . $exchange_rate . " " . strtoupper($deposit_currency),
                    "transaction_fee" => round($transaction_fee, 4) . " " . strtoupper($payin->currency),
                    "payment_method" => $payin->method_name,
                    "estimate_delivery_time" => formatSettlementTime($payin->settlement_time),
                    "total_amount_due" => $total_amount_due . " " . strtoupper($payin->currency)
                ];

                $process = $this->process_store($request->gateway, $payin->currency, $total_amount_due, $deposit->toArray());

                if (isset($process['error'])) {
                    return get_error_response($process);
                }
                return get_success_response(array_merge($process, $arr));
            }

            return get_error_response(['error' => "Unable to process deposit"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
    
    /**
     * @return array
     */
    public function process_store($gateway, $currency, $amount, $deposit = [], $txn_type = 'deposit')
    {
        try {
            $payment = new DepositService();
            $callback = $payment->makeDeposit($gateway, $currency, $amount, $deposit, $txn_type);

            Log::info("Deposit callback: " . json_encode($callback));

            if (is_string($callback)) {
                $callback = ['url' => $callback];  // Treat string responses as redirect URLs
            } elseif (is_object($callback)) {
                $callback = (array) $callback;
            }

            if (empty($callback) || isset($callback['error'])) {
                return ['error' => $callback['error'] ?? 'An unknown error occurred'];
            }

            // Determine the payment mode and data based on callback response
            $mode = null;
            $pay_data = null;

            if (isset($callback['url'])) {
                $mode = 'redirect';
                $pay_data = $callback['url'];
            } elseif (isset($callback['payment_url'])) { // for floid
                $mode = 'redirect';
                $pay_data = $callback['url'] = $callback['payment_url'];
            } elseif (isset($callback['brCode'])) {
                $mode = 'brCode';
                $pay_data = $callback['brCode'];
            } elseif (isset($callback['qr'])) {
                $mode = 'qr_code';
                $pay_data = $callback['qr'];
            } elseif (isset($callback['ticket'])) {
                $mode = 'wire_details';
                $pay_data = $callback['ticket'];
            } elseif (isset($callback['onramp'])) {
                $mode = 'onramp';
                $pay_data = $callback['onramp'];
            } elseif (isset($callback['wireInstructions'])) {
                $mode = 'wire_details';
                $pay_data = $callback['wireInstructions'];
            } else {
                Log::info("Received payment response", $callback);
                return ['error' => 'Unsupported payment response format'];
            }

            $transaction = TransactionRecord::where([
                "transaction_type" => $txn_type,
                'transaction_id' => $deposit['id']
            ])->first();

            if (!$transaction) {
                return ['error' => 'Transaction not found'];
            }

            // Create a new checkout entry
            $checkout = new CheckoutModel();
            $checkout->user_id = auth()->id();
            $checkout->transaction_id = $transaction->id;
            $checkout->deposit_id = $deposit['id'];
            $checkout->checkout_mode = $mode;
            $checkout->checkout_id = session()->get("checkout_id", $callback['id'] ?? null);
            $checkout->provider_checkout_response = $callback;
            $checkout->checkouturl = str_replace('http://api.yativo.com', 'https://checkout.yativo.com', route("checkout.url", ['id' => $deposit['id']]));
            $checkout->checkout_status = "pending";

            if (!$checkout->save()) {
                return ['error' => "Unable to initiate payment, please contact support."];
            }

            $encryptedId = Crypt::encrypt($checkout->id);
            $checkoutUrl = route('checkout.url', ['id' => $encryptedId]);

            return [
                'deposit_url' => $checkout->checkouturl,
                'deposit_data' => $deposit,
            ];

        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }


    /**
     * Display the specified resource.
     * 
     * @param  string  $id
     * @return \Illuminate\Http\Response
     */
    public function show(string $id)
    {
        try {
            $deposit = Deposit::whereUserId(active_user())->where(['id' => $id])->first();
            if (!$deposit)
                return get_error_response(['error' => "Transaction not found"]);
            return get_success_response($deposit);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function getPayinCurrencies(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'country' => 'required|min:3|max:3'
            ]);
    
            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }
    
            $where = [
                'country' => $request->country
            ];
    
            $currencies = PayinMethods::where($where)
                ->join('currency_lists', 'currency_lists.currency_code', '=', 'payin_methods.currency')
                ->select('currency_lists.currency_code', 'currency_lists.currency_name', 'currency_lists.currency_symbol')
                ->get();
    
            return get_success_response($currencies);
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }
    

    public function payinMethods(Request $request)
    {
        try {
            // Validation: country and currency can be nullable
            $validate = Validator::make($request->all(), [
                'country' => 'nullable|min:3|max:3',  // country can be null or 3 chars long
                'currency' => 'nullable|min:3|max:3', // currency can be null or 3 chars long
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            // Initialize the query conditions based on which parameter is provided
            $where = [];
            if ($request->has('country')) {
                $where['country'] = $request->country;
            }

            if ($request->has('currency')) {
                $where['currency'] = $request->currency;
            }

            // Fetch the payin methods based on the query conditions
            $methods = PayinMethods::where($where)->get();

            return get_success_response($methods);
        } catch (\Throwable $th) {
            // Handle errors
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }


    public function payinMethodsCountries()
    {
        try {
            $countries = PayinMethods::join('countries', 'countries.iso3', '=', 'payin_methods.country')
                ->select('countries.iso3', 'countries.iso2', 'countries.name')
                ->with('currency_lists')
                ->distinct()
                ->get();

            $countries = $countries->map(function ($country) {
                return [
                    'country' => $country->iso3,
                    'name' => $country->name,
                    'flag' => "https://cdn.jsdelivr.net/gh/hampusborgos/country-flags@main/svg/{$country->iso2}.svg",
                    'currencies' => $country->currency_lists
                ];
            });
            return get_success_response($countries);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    private function get_transaction_rate($send_currency, $receive_currency, $gateway, $type)
    {
        Log::info(json_encode([$send_currency, $receive_currency, $gateway, $type]));
        $result = 0;

        // Fetch exchange rate details based on gateway and type
        $method = PayinMethods::whereId($gateway)->first();
        $rates = $method->exchange_rate_float ?? 0;

        // Fetch base rate from external service or function
        $baseRate = exchange_rates(strtoupper($send_currency), strtoupper($receive_currency));

        if ($rates) {

            if ($baseRate > 0) {
                // Calculate floated amount if float percentage is set
                $rate_floated_amount = ($rates->float_percentage ?? 0) / 100 * $baseRate;
                $result = $baseRate + $rate_floated_amount;
            } else {
                Log::error("Base rate is 0 for {$send_currency} to {$receive_currency}");
            }
        } else {
            if ($baseRate > 0) {
                $result = $baseRate;
            } else {
                Log::error("No exchange rate found for gateway ID: {$gateway}, type: {$type}");
            }
        }

        return floatval($result);
    }

}
