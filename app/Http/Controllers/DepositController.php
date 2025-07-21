<?php

namespace App\Http\Controllers;

use App\Jobs\DemoDepositWebhookJob;
use App\Jobs\Transaction;
use App\Models\CheckoutModel;
use App\Models\CurrencyList;
use App\Models\CustomPricing;
use App\Models\DemoModel;
use App\Models\Deposit;
use App\Models\PayinMethods;
use App\Models\TransactionRecord;
use App\Services\DepositCalculator;
use App\Services\DepositService;
use App\Services\PaymentService;
use Creatydev\Plans\Models\PlanModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Handles deposit related actions
 */
class DepositController extends Controller
{

    public function __construct()
    {
        // CheckoutModel
        if (!Schema::hasColumn('checkout_models', 'expiration_time')) {
            Schema::table('checkout_models', function (Blueprint $table) {
                $table->string('expiration_time')->nullable();
            });
        }
    }

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
            
            $query = Deposit::whereUserId(active_user())->with(['depositGateway']);
    
            // Filter by customer_id
            if ($request->has('customer_id')) {
                $query->where('customer_id', $request->customer_id);
            }
    
            // Filter by customer_id
            if ($request->has('gateway') && $request->gateway == 'epay') {
                $query->where('gateway', '999999');
            } else if ($request->has('gateway')) {
                $query->where('gateway', $request->gateway);
            }

            // Filter by customer_id
            if ($request->has('currency')) {
                $query->where('currency', $request->currency);
            }

    
            // Filter by date range
            $start_date = $request->input('start_date');
            $end_date = $request->input('end_date');
            
            if ($start_date && $end_date) {
                $query->whereBetween('created_at', [$start_date, $end_date]);
            } elseif ($start_date) {
                $query->where('created_at', '>=', $start_date);
            } elseif ($end_date) {
                $query->where('created_at', '<=', $end_date);
            }
    
            $deposits = $query->latest()->paginate($per_page);
            return paginate_yativo($deposits);
        } catch (\Throwable $th) {
            if (env('APP_ENV') == 'local') {
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
            $validate = Validator::make($request->all(), [
                'gateway' => 'required',
                'amount' => 'required|numeric|min:0',
                'currency' => 'required',
            ]);

            if ($validate->fails()) {
                return get_error_response($validate->errors()->toArray());
            }

            $gateway = PayinMethods::whereId($request->gateway)->firstOrFail();

            $user = $request->user();
            $deposit_currency = $request->currency;

            if (!$user->hasWallet($deposit_currency)) {
                return get_error_response(['error' => "Invalid credit wallet selected"], 400);
            }

            // Validate allowed debit currencies
            $allowedCurrencies = explode(',', $gateway->base_currency);
            if (!in_array($request->currency, $allowedCurrencies)) {
                return get_error_response([
                    'error' => "Supported deposit wallets are: " . implode(', ', $allowedCurrencies)
                ], 400);
            }
            
            // Validate deposit amount against gateway min/max limits
            if ($gateway->minimum_deposit && $request->amount < $gateway->minimum_deposit) {
                return get_error_response([
                    'error' => "Minimum deposit amount is {$gateway->minimum_deposit} {$gateway->currency}"
                ], 400);
            }

            if ($gateway->maximum_deposit && $request->amount > $gateway->maximum_deposit) {
                return get_error_response([
                    'error' => "Maximum deposit amount is {$gateway->maximum_deposit} {$gateway->currency}"
                ], 400);
            }


            if($gateway->gateway == "bitso") {
                $validate = Validator::make($request->all(), [
                    'cellphone' => 'required',
                    'documentType' => 'required',
                    'documentNumber' => 'required',
                ]);

                if ($validate->fails()) {
                    return get_error_response($validate->errors()->toArray());
                }
            }

            // Get user plan and charges
            $user_plan = 1;
            if (!$user->hasActiveSubscription()) {
                $plan = PlanModel::where('price', 0)->latest()->first();
                if ($plan) {
                    $user->subscribeTo($plan, 30, true);
                }
            }

            $subscription = $user->activeSubscription();
            if ($subscription) {
                $user_plan = (int) $subscription->plan_id;
            }

            $fixed_charge = $float_charge = 0;

            if ($user_plan === 3) {
                $customPricing = CustomPricing::where('user_id', $user->id)
                    ->where([
                        'gateway_id' => $gateway->id,
                        'gateway_type' => 'payin',
                    ])->first();

                if ($customPricing) {
                    $fixed_charge = $customPricing->fixed_charge;
                    $float_charge = $customPricing->float_charge;
                } else {
                    $user_plan = 2;
                }
            }

            if ($user_plan === 1) {
                $fixed_charge = $gateway->fixed_charge;
                $float_charge = $gateway->float_charge;
            } elseif ($user_plan === 2) {
                $fixed_charge = $gateway->pro_fixed_charge;
                $float_charge = $gateway->pro_float_charge;
            }

            // Prepare gateway config for calculator
            $gatewayConfig = [
                'method_name'         => $gateway->method_name,
                'gateway'             => $gateway->gateway,
                'country'             => $gateway->country,
                'currency'            => $gateway->currency,
                'charges_type'        => 'combined',
                'fixed_charge'        => $fixed_charge,
                'float_charge'        => $float_charge,
                'exchange_rate_float' => $gateway->exchange_rate_float ?? 0,
                'minimum_charge'      => $gateway->minimum_charge ?? 0,
                'maximum_charge'      => $gateway->maximum_charge ?? 0,
            ];

            if(env('IS_DEMO') === true) {
                $demo = new DemoModel();
                $demoResponse = $demo->response(request());
                // dispatch(new DemoDepositWebhookJob($deposit));
                return get_success_response($demoResponse);
            }                

            $calculator = new DepositCalculator($gatewayConfig);
            $calc = $calculator->calculate($request->amount);

            // Save deposit
            $deposit = new Deposit();
            $deposit->currency = $gateway->currency;
            $deposit->deposit_currency = $deposit_currency;
            $deposit->user_id = active_user();
            $deposit->amount = $request->amount;
            $deposit->gateway = $request->gateway;
            $deposit->receive_amount = floor($calc['credited_amount']);
            $deposit->customer_id = $request->customer_id ?? null;

            $totalAmount = $calc['amount'];

            if ($deposit->save()) {
                $arr['payment_info'] = [
                    "send_amount" => round($request->amount, 4) . " " . strtoupper($gateway->currency),
                    "receive_amount" => floor($calc['credited_amount']) . " " . strtoupper($deposit_currency),
                    "exchange_rate" => "1 " . strtoupper($deposit_currency) . " = " . round($calc['exchange_rate'], 8) . " " . strtoupper($gateway->currency),
                    "transaction_fee" => round($calc['total_fees'], 4) . " " . strtoupper($gateway->currency),
                    "payment_method" => $gateway->method_name,
                    "estimate_delivery_time" => formatSettlementTime($gateway->settlement_time),
                    "total_amount_due" => round($totalAmount, 4) . " " . strtoupper($gateway->currency),
                    'calc' => $calc
                ];
                
                $expireAt = $gateway->expiration_time;
                $process = $this->process_store($request->gateway, $gateway->currency, $totalAmount, $deposit->toArray(), $expireAt);

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
    public function process_store($gateway, $currency, $amount, $deposit = [], $expireAt, $txn_type = 'deposit')
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
            $checkout->expiration_time = $expireAt;

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
        Log::info("Fetching transaction rate", [
            'send_currency' => $send_currency,
            'receive_currency' => $receive_currency,
            'gateway' => $gateway,
            'type' => $type,
        ]);
        
        if(strtoupper($send_currency) === strtoupper($receive_currency)) {
            return 1;
        }

        $result = 0;
    
        // Fetch exchange rate details based on gateway and type
        $method = PayinMethods::whereId($gateway)->first();
    
        if (!$method) {
            Log::error("Payin method not found for gateway ID: {$gateway}");
            return 0;
        }
    
        // Fetch float percentage from the gateway method (exchange rate float)
        $rate = $method->exchange_rate_float ?? 0;
    
        // Fetch base rate from external service or function
        $baseRate = exchange_rates(strtoupper($send_currency), strtoupper($receive_currency));
    
        if ($baseRate <= 0) {
            Log::error("Invalid base rate fetched for {$send_currency} to {$receive_currency}");
            return 0;
        }
    
        if ($rate > 0) {
            // If rate is provided, calculate adjusted rate (increase by float percentage)
            $rate_floated_amount = ($rate / 100) * $baseRate;
            $result = $baseRate + $rate_floated_amount;
        } else {
            // If no rate is provided, simply return base rate
            $result = $baseRate;
        }
    
        Log::info("Calculated exchange rate", [
            'base_rate' => $baseRate,
            'rate' => $rate,
            'adjusted_rate' => $result,
        ]);
    
        return floatval($result);
    }
    
    private function exchange_rate($from_currency, $receive_currency)
    {
        $get_rate = file_get_contents("https://min-api.cryptocompare.com/data/price?fsym={$from_currency}&tsyms={$receive_currency}");
        $rateInArray = json_decode($get_rate, true);
        $rate = $rateInArray[$receive_currency];
        return $rate;
    }
}
