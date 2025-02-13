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
            $deposits = Deposit::whereUserId(active_user())->latest()->paginate($per_page);
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
                    'gateway' => 'required|numeric|min:1',
                    'amount' => 'required|numeric',
                    'currency' => 'required_without:credit_wallet',
                    'credit_wallet' => 'required_without:currency'
                ]
            );
            
            if ($validate->fails()) {
                return get_error_response($validate->errors()->toArray());
            }

            $user = $request->user();

            if (!$user->hasWallet($request->currency)) {
                return get_error_response(['error' => "Invalid wallet selected"], 400);
            }

            $payin = PayinMethods::whereId($request->gateway)->first();

            if (!$payin) {
                return get_error_response(['error' => 'Invalid payment gateway selected.'], 400);
            }
            $allowedCurrencies = [];
            $allowedCurrencies[] = explode(',', $payin->base_currency);
            
            if (($request->currency || !in_array($request->currency, $allowedCurrencies))) {
                return get_error_response([
                    'currencies' => $allowedCurrencies,
                    'error' => "The selected deposit wallet is not supported for selected gateway. Allowed currencies: " . $payin->base_currency
                ], 400);
            }
            
            $exchange_rate = get_transaction_rate($payin->currency, $request->credit_wallet ?? $request->currency, $payin->id, "payin");
            
            if (!$exchange_rate || $exchange_rate <= 0) {
                return get_error_response(['error' => 'Invalid exchange rate. Please try again.'], 400);
            }
            $exchange_rate = floatval($exchange_rate);
            
            $amount = floatval($request->amount ?? 0);
            if ($amount <= 0) {
                return get_error_response(['error' => 'Invalid deposit amount.'], 400);
            }
            
            if ($payin->minimum_deposit > ($amount * $exchange_rate)) {
                return get_error_response(['error' => "Minimum deposit amount for the selected Gateway is {$payin->minimum_deposit}"], 400);
            }
            
            if ($payin->maximum_deposit < ($amount * $exchange_rate)) {
                return get_error_response(['error' => "Maximum deposit amount for the selected Gateway is {$payin->maximum_deposit}"], 400);
            }
            

            // record deposit info into the DB
            $deposit = new Deposit();
            $deposit->currency = $payin->currency;
            $deposit->deposit_currency = $request->credit_wallet ?? $request->currency;
            $deposit->user_id = active_user();
            $deposit->amount = $request->amount;
            $deposit->gateway = $request->gateway;
            $deposit->receive_amount = floatval($request->amount * $exchange_rate);
            $transaction_fee = floatval($exchange_rate * get_transaction_fee($request->gateway, $request->amount, 'deposit', "payin"));

            if(is_array($transaction_fee) && isset($transaction_fee['error'])) {
                return get_error_response($transaction_fee, 422);
            }

            if (!$payin) {
                return get_error_response(['error' => 'Invalid gateway, please contact support']);
            }

            $deposit->currency = $payin->currency;
            if ($deposit->save()) {
                $total_amount_due = round($request->amount / $exchange_rate, 4) + $transaction_fee;
                $arr['payment_info'] = [
                    "send_amount" => round($request->amount / $exchange_rate, 4)." $payin->currency",
                    "receive_amount" => ($request->amount * $exchange_rate) .explode(".", $deposit->deposit_currency)[0],
                    "exchange_rate" => "1" . strtoupper($payin->currency) . " ~ $exchange_rate" . strtoupper($request->credit_wallet ?? $request->currency),
                    "transaction_fee" => "$transaction_fee $payin->currency",
                    "payment_method" => $payin->method_name,
                    "estimate_delivery_time" => formatSettlementTime($payin['settlement_time']),
                    "total_amount_due" => "$total_amount_due $payin->currency"
                ];

                $process = $this->process_store($request->gateway, $payin->currency, $total_amount_due, $deposit->toArray());
                // var_dump($process);exit;

                if (isset($process['error']) || in_array('error', $process)) {
                    return get_error_response($process);
                }
                return get_success_response(array_merge($process, $arr));
            }

            return get_error_response(['error' => "Sorry we're currently unable to process your deposit request"]);
        } catch (\Throwable $th) {
            // return response()->json(['error' => $th->getTraceAsString()]);
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
}
