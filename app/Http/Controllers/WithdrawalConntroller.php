<?php

namespace App\Http\Controllers;

use App\Models\Gateways;
use App\Models\payoutMethods;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Models\Withdraw;
use App\Notifications\WithdrawalNotification;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Modules\Beneficiary\app\Models\Beneficiary;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Currencies\app\Models\Currency;
use Modules\Monnet\app\Services\MonnetServices;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use App\Services\PayoutCalculator;
use Log;

/**
 * WithdrawalConntroller handles withdrawal requests.
 */
class WithdrawalConntroller extends Controller
{

    public function getPayoutMethods(Request $request)
    {
        try {
            $methods = payoutMethods::when($request->has('country'), function ($query) use ($request) {
                $query->where('country', $request->country);
            })->when($request->has('currency'), function ($query) use ($request) {
                $query->where('currency', $request->currency);
            })->get();

            return get_success_response($methods);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function payoutFilter(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "country" => "required",
                "currency" => "required",
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $filters = [
                "country" => $request->country,
                "currency" => $request->currency,
            ];
            $payout = payoutMethods::where($filters)->get();
            return get_success_response($payout);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Get a paginated list of withdrawals for the authenticated user.
     *
     * @return 
     */
    public function index($arrOnly = false)
    {
        try {
            $r = request();
            $payouts = Withdraw::where('user_id', auth()->id())->with('beneficiary')->latest()->paginate(per_page($r->per_page ?? 10));
            if ($arrOnly) {
                return $payouts;
            }
            return paginate_yativo($payouts);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function show($payoutId)
    {
        try {
            $where = [
                'user_id' => auth()->id(),
                'payout_id' => $payoutId
            ];
            $payout = Withdraw::where($where)->with('beneficiary')->first();
            if(!$payout) {
                return get_error_response(['error' => "Payout not found"]);
            }
            return get_success_response($payout->makeHidden(['raw_data', "user_id", "bridge_id", "bridge_customer_id", "bridge_response"]));
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Store a new withdrawal request.
     * Restoring the codebase to test vitawallet
     * @param Request $request
     * @return \Response
     */
    public function store(Request $request)
    {
        try {
            // Add debit_wallet column if missing
            if (!Schema::hasColumn('withdraws', 'customer_receive_amount')) {
                Schema::table('withdraws', function (Blueprint $table) {
                    $table->string('customer_receive_amount')->nullable();
                });
            }

            $validate = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:0.01',
                'payment_method_id' => 'required|integer',
                'customer_id' => 'sometimes|exists:customers,customer_id',
                'debit_wallet' => 'required|string'
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $validated = $validate->validated();
            $beneficiary = BeneficiaryPaymentMethod::find($validated['payment_method_id']);

            // Validate beneficiary and payment method
            if (!$beneficiary || !$beneficiary->gateway_id) {
                return get_error_response(['error' => "Invalid payment method configuration"]);
            }

            $payoutMethod = PayoutMethods::find($beneficiary->gateway_id);
            if (!$payoutMethod) {
                return get_error_response(['error' => "Unsupported withdrawal method"]);
            }

            // Validate allowed debit currencies
            $allowedCurrencies = explode(',', $payoutMethod->base_currency);
            if (!in_array($validated['debit_wallet'], $allowedCurrencies)) {
                return get_error_response([
                    'error' => "Supported debit currencies: " . implode(', ', $allowedCurrencies)
                ], 400);
            }

            // Calculate payout details
            $calculator = new PayoutCalculator();
            $result = $calculator->calculate(
                $validated['amount'],
                $validated['debit_wallet'],
                $validated['payment_method_id'],
                $payoutMethod->exchange_rate_float
            );

            // Validate exchange rate
            if ($result['adjusted_rate'] <= 0) {
                return get_error_response(['error' => 'Invalid exchange rate configuration'], 400);
            }

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

            // Prepare withdrawal record
            $customer_receive_amount = $validated['amount'] * $result['adjusted_rate'];
            $withdrawalData = [
                'user_id' => auth()->id(),
                'gateway' => $payoutMethod->gateway,
                'gateway_id' => $beneficiary->gateway_id,
                'currency' => $payoutMethod->currency,
                'beneficiary_id' => $validated['payment_method_id'],
                'debit_wallet' => $validated['debit_wallet'],
                'amount' => $validated['amount'],
                "debit_amount" => $result['amount_due'],
                "send_amount" => $validated['amount'],
                "customer_receive_amount" => $customer_receive_amount,
                'raw_data' => $result,
                'status' => 'pending'
            ];

            $telegramNotification = "You have a new payout request of $customer_receive_amount with below informations";
            // sendTelegramNotification($telegramNotification);
            // Construct the message payload
            // $message_payload = $telegramNotification . "<code>" . json_encode($withdrawalData) . "</code>";

            // Retrieve environment variables
            $botToken = env("TELEGRAM_TOKEN");
            $chatId = env('TELEGRAM_CHAT_ID');

            // Log the notification call
            Log::debug("Telegram notification called");

            // Construct the Telegram API URL
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            // Send the HTTP request using Laravel's HTTP client
            try {
                $response = Http::post($url, [
                    'text' => $telegramNotification,
                    'chat_id' => $chatId,
                    'protect_content' => true,
                    'parse_mode' => 'html'
                ]);
                
                if ($response->successful()) {
                    Log::debug("Telegram notification sent successfully");
                } else {
                    Log::error("Telegram notification failed: " . $response->body());
                }
            } catch (\Exception $e) {
                Log::error("Telegram notification error: " . $e->getMessage());
            }
            
            if(request()->has('debug')) {
                dd($withdrawalData); exit;
            }

            $withdrawal = Withdraw::create($withdrawalData);

            if(!$withdrawal) {
                return get_error_response([], 400, 'Unable to process Withdrawal');
            }
           
            $result['exchange_rate'] = $result['adjusted_rate'];
            unset($result['debit_amount']);
            unset($result['PayoutMethod']);
            unset($result['total_fee']);
            unset($result['adjusted_rate']);
            unset($result['base_currencies']);
            unset($result['fee_breakdown']);
            // Format response data
            $responseData = [
                'withdrawal_id' => $withdrawal->id,
                'payout_id' => $withdrawal->payout_id,
                'status' => $withdrawal->status,
                'debit_amount' => $result['amount_due'],
                'target_amount' => $result['total_amount'],
                'currency' => $payoutMethod->currency,
                'fees' => $result,
                'processed_at' => $withdrawal->created_at
            ];

            return get_success_response($responseData, 201, "Withdrawal request initiated successfully");

        } catch (\Throwable $th) {
            // var_dump($th); exit;
            return get_error_response([
                'error' => $th->getMessage(),
                'trace' => config('app.debug') ? $th->getTrace() : []
            ]);
        }
    }

    /**
     * Get the status of a withdrawal.
     * 
     * @param int $payoutsId
     * @return \Response
     */
    public function getWithdrawalStatus($payoutsId)
    {
        try {
            // $monnet = new MonnetServices();
            // return $monnet->payoutStatus($payoutsId);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function payoutMethodsCountries()
    {
        try {
            $countries = PayoutMethods::join('countries', 'countries.iso3', '=', 'payout_methods.country')
                ->select('countries.iso3', 'countries.iso2', 'countries.name')
                ->distinct()
                ->get();
            return get_success_response($countries);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
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
