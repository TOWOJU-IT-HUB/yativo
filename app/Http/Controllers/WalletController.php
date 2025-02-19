<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClabe;
use App\Jobs\Transaction as JobsTransaction;
use App\Models\Balance;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\TransactionRecord;
use App\Models\User;
use App\Models\Withdraw;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Modules\Currencies\app\Models\Currency;


/**
 * This controller contains methods for managing user wallets.
 *
 * The main methods are:
 *
 * - addWallet: Adds a new wallet currency for the user
 * - deposits: Gets a list of deposits for the user
 * - withdrawals: Gets a list of withdrawals for the user (moved to WithdrawalController)
 * - yativoTransfer: Transfers balance between two users
 * - getBalance: Gets the wallet balance for the user
 *
 * WalletController
 *
 * This controller handles wallet management operations like getting balance,
 * adding/removing currencies, making deposits/withdrawals etc.
 */

class WalletController extends Controller
{
    /**
     * Get a paginated list of withdrawals for the authenticated user.
     *
     * @return mixed
     */
    public function index()
    {
        try {
            $payouts = Withdraw::where('user_id', auth()->id())->with('beneficiary')->paginate(per_page())->withQueryString();
            return paginate_yativo($payouts);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }


    /**
     * Get balance for authenticated user
     */
    public function balance($param = null)
    {
        try {
            // Get the authenticated user
            $user = auth()->user();

            if ($user->wallets()->count() == 0) {
                $user->createWallet([
                    'name' => 'USD',
                    'slug' => 'usd',
                    'decimal_places' => 2,
                    'meta' => [
                        'fullname' => 'US Dollar',
                        'symbol' => "$",
                        'precision' => 2,
                        'logo' => 'https://catamphetamine.github.io/country-flag-icons/3x2/US.svg',
                    ],
                ]);
            }

            $wallets = $user->wallets->makeHidden(['holder_id', 'id', 'holder_type', 'uuid', 'created_at', 'updated_at']);

            if ($param == "total") {
                $total_balance = 0;
                foreach ($wallets as $key => $wallet) {
                    // Ensure $wallets is defined or initialized before using it
                    $slug = strtoupper($wallet->slug);
                    $usdRateResponse = $this->rates($slug);

                    if (isset($usdRateResponse['error'])) {
                        // Handle error from rates function
                        return get_error_response(['error' => 'Error fetching exchange rates.', 'details' => $usdRateResponse['error']]);
                    }
                    // Fetch the exchange rate from the response
                    $usdRate = $usdRateResponse['data'][$slug];
                    // Calculate total balance
                    $total_balance += ($wallet->balance * $usdRate);
                }
                // Return total balance
                return get_success_response(['total_balance' => $total_balance]);
            }


            // Return success response with wallets
            return get_success_response($wallets);
        } catch (\Throwable $th) {
            // Log error
            \Log::error($th);

            // Return error response
            return get_error_response(['error' => 'Unable to get wallets', 'info' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }

    /**
     * Add new wallet currency/balance for customer
     * @param Request $request
     * @return array
     */

    public function addNewWallet(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'currency' => 'required|in:USD,EUR,GBP,CLP,PEN,ARS,MXN,COP,BRL',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }


            $user = $request->user();

            // check if user already has wallet
            if ($user->hasWallet($request->currency)) {
                return get_error_response([
                    'error' => 'Wallet already exists',
                ]);
            }

            $currency = Currency::whereWallet($request->currency)->first();

            if (!$currency) {
                return get_error_response(['error' => 'Currency is currenctly unavailable, please contact support']);
            }

            $wallet = [
                'name' => $currency->wallet,
                'slug' => strtolower($currency->wallet),
                'decimal_places' => $currency->decimal_places ?? 2,
                'meta' => [
                    'fullname' => $currency->currency_name,
                    'symbol' => $currency->currency_icon,
                    'precision' => $currency->decimal_places ?? 2,
                    'logo' => $currency->logo_url ?? 'https://yativo.com/wp-content/uploads/2024/03/Yativo-42x43_090555.png',
                ],
            ];

            if ($user->createWallet($wallet)) {
                if ($request->currency == "MXN") {
                    // generate clabe for customer
                    new GenerateClabe($user);
                }
                return get_success_response(['msg' => 'Wallet created successfully']);
            }
            return get_error_response(['error' => 'Unable to process request, please try again later']);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * This method adds a new wallet currency/balance for a customer.
     *
     * @param Request $request - The incoming request
     * @return array - Returns a success or error response
     */

    public function deposits()
    {
        try {
            $deposits = Deposit::whereUserId(active_user())->with('transaction')->paginate(per_page())->withQueryString();
            return paginate_yativo($deposits);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * 
     * The methods handle validation, database queries, and background jobs for transactions.
     * Responses are returned using helper methods like get_success_response().
     * The code follows common Laravel conventions and patterns.
     * This method transfers balance between two users.
     *
     * @param Request $request - The incoming request
     * @return array - Returns a success or error response
     */
    public function yativoTransfer(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'email' => 'required|email',
                'amount' => 'required|numeric|min:1',
                'currency' => 'required|string',
            ]);

            if ($validate->fails()) {
                return get_error_response(['error' => $validate->errors()->toArray()]);
            }

            $validate = $validate->validated();

            $user = $request->user();
            if (!$user->hasWallet($validate['currency'])) {
                return get_error_response(['error' => 'Wallet not found']);
            }

            if ($validate['email'] == $user->email) {
                return get_error_response(['error' => "Sorry you can't transfer to yourself"]);
            }

            $receiver = User::where('email', $validate['email'])->first();
            if (!$receiver) {
                return get_error_response(['error' => 'Invalid receiver provided']);
            }

            if (!$receiver->hasWallet($validate['currency'])) {
                return get_error_response(['error' => 'Receiver wallet not found']);
            }

            $senderWallet = $user->getWallet($validate['currency']);
            $receiverWallet = $receiver->getWallet($validate['currency']);

            if (!$senderWallet->canWithdraw($validate['amount'])) {
                return get_error_response(['error' => 'Insufficient wallet balance']);
            }

            DB::beginTransaction();
            try {
                $sen = $senderWallet->withdraw($validate['amount']);
                $dep = $receiverWallet->deposit($validate['amount']);

                unset($validate['email']);
                $validate['beneficiary_id'] = $receiver->id;
                $validate['user_id'] = $user->id;

                $transfer = [
                    'amount' => $validate['amount'],
                    'currency' => $validate['currency'],
                    'sender' => $user->email,
                    'receiver' => $receiver->email,
                ];

                // add record for sender
                TransactionRecord::create([
                    "user_id" => auth()->id(),
                    "transaction_beneficiary_id" => $user->id,
                    "transaction_id" => $sen->id,
                    "transaction_amount" => $request->amount,
                    "gateway_id" => 'yativo',
                    "transaction_status" => "successful",
                    "transaction_type" => 'yativo_transfer',
                    "transaction_memo" => "credit",
                    "transaction_purpose" => "Intra System Transfer",
                    "transaction_payin_details" => $validate,
                    "transaction_payout_details" => $sen,
                ]);

                // add record for receiver
                TransactionRecord::create([
                    "user_id" => auth()->id(),
                    "transaction_beneficiary_id" => $receiver->id,
                    "transaction_id" => $dep->id,
                    "transaction_amount" => $request->amount,
                    "gateway_id" => 'yativo',
                    "transaction_status" => "successful",
                    "transaction_type" => 'yativo_transfer',
                    "transaction_memo" => "credit",
                    "transaction_purpose" => "Intra System Transfer",
                    "transaction_payin_details" => $transfer,
                    "transaction_payout_details" => $validate,
                ]);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return get_error_response(['error' => 'Transaction failed. Please try again.']);
            }

            // $payouts = Deposit::whereUserId($user->id)
            //                  ->with('transactionRecord')
            //                 ->get();
            return get_success_response($transfer);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function rates(mixed $walletCurrencies)
    {
        try {
            $mergedData = [];
            if (is_string($walletCurrencies) && $walletCurrencies == 'USD') {
                return ['data' => ["USD" => 1]];
            }

            // Check if response is cached
            $cacheKey = 'coinbase_rates';
            if (cache()->has($cacheKey)) {
                $response = cache()->get($cacheKey);
            } else {
                // Fetch exchange rates from Coinbase API
                $response = file_get_contents('https://api.coinbase.com/v2/exchange-rates');
                // Cache response for 5 minutes
                cache()->put($cacheKey, $response, now()->addMinutes(5));
            }

            // Check if the request was successful
            if ($response) {
                $data = json_decode($response, true);

                if (is_array($walletCurrencies)) {
                    // Initialize an empty array to store merged data
                    $mergedData = [];
                    // Iterate through wallet currencies
                    foreach ($walletCurrencies as $currency) {
                        // Check if the currency exists in the API response
                        if (isset($data['data']['rates'][$currency])) {
                            // Merge the exchange rate data with wallet currencies
                            $mergedData[$currency] = round($data['data']['rates'][$currency], 4);
                        } else {
                            // Handle case where currency is not found in API response
                            $mergedData[$currency] = 0;
                        }
                    }

                    // Return merged data
                    return ['data' => $mergedData];
                } else if (isset($data['data']['rates'][$walletCurrencies]) && is_string($walletCurrencies)) {
                    $mergedData[$walletCurrencies] = round($data['data']['rates'][$walletCurrencies], 4);
                    return ['data' => $mergedData];
                }
            }

            // Handle unsuccessful API request or missing response
            return ['error' => 'Failed to fetch exchange rates.'];
        } catch (\Throwable $th) {
            // Handle any unexpected errors
            return get_error_response(['error' => $th->getMessage(), 'trace' => $th->getTrace()]);
        }
    }
}
