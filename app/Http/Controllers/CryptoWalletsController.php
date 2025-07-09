<?php

namespace App\Http\Controllers;

use App\Models\BusinessConfig;
use App\Models\CryptoDeposit;
use App\Models\CryptoWallets;
use App\Models\User;
use App\Models\TransactionRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\CoinPayments\app\Services\CoinpaymentServices;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;
use Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class CryptoWalletsController extends Controller
{
    public $coinpayment, $businessConfig, $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env("YATIVO_CRYPTO_API_URL");
        // $apiKey = getenv("COINPAYMENT_PRIVATE_KEY");
        // $secretKey = getenv("COINPAYMENT_PUBLIC_KEY");
        // $this->coinpayment = new CoinpaymentServices($apiKey, $secretKey);
        // $this->middleware('can_create_crypto')->only(['createWallet']);

        if(!Schema::hasColumn('users', 'yativo_customer_id')) {
            Schema::table('users', function(Blueprint $table) {
                $table->string('yativo_customer_id')->nullable();
            });
        }
    }
    
    public function createWallet()
    {
        $request = request();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'currency' => 'required',
        ]);
    
        if ($validator->fails()) {
            return get_error_response($validator->errors()->toArray());
        }
    
        // Check business approval for issuing wallet
        $user = auth()->user();
        $currency = $request->currency;
    
        $userId = $user->id;

        // return exiting wallet if any
        $walletAddresses = CryptoWallets::where(["user_id" => $userId, "wallet_currency" => $currency])->first();
        if($walletAddresses) {
            return get_success_response($walletAddresses, 200, "Wallet already exists");
        }
    
        // Generate wallet address
        $yativo = new CryptoYativoController();
        $token = $yativo->getToken();

        $yativo_customer_id = $this->addCustomer();
        
        $payload = [
            "ticker_name" => $currency, // $this->getAssetId($request->currency) ?? "67db5f72ebea822c360d568d",
            "customer_id" => $yativo_customer_id,
        ];

        $response = Http::withToken($token)->post($this->baseUrl . "assets/add-customer-asset", $payload)->json();

        if(isset($response['status']) && $response["status"] == true) {
            // Create wallet record in the database
            $data = $response['data'];
            $walletData = [
                "user_id" => $userId,
                "is_customer" => false, // request()->customer_id ? true : false,
                "customer_id" => null, // request()->customer_id,
                "wallet_address" => $data['address'],
                "wallet_currency" => trim($data['ticker_name']),
                "wallet_network" => $data['chain'],
                "wallet_provider" => 'yativo',
                "coin_name" => $data['asset_name'],
                "wallet_balance" => 0,
            ];
        
            $record = CryptoWallets::create($walletData);
        
            // Queue webhook notification
            dispatch(function () use ($userId, $record) {
                $webhook = Webhook::whereUserId($userId)->first();
                if ($webhook) {
                    WebhookCall::create()
                        ->meta(['_uid' => $webhook->user_id])
                        ->url($webhook->url)
                        ->useSecret($webhook->secret)
                        ->payload([
                            "event.type" => "crypto.wallet.created",
                            "payload" => $record,
                        ])
                        ->dispatchSync();
                }
            })->afterResponse();
        
            // Load relationships for the response
            // $record = $record->load('user');
        
            return get_success_response($record);
        }

        if (!isset($response['status']) || !isset($response['data']) ) {
            Log::error("Failed to generate wallet", ["error" => $response, 'token' => $token, 'payload' => $payload]);
            return get_error_response(['error' => $response]);
        }
        
        if (isset($response['error']) || !isset($response['address'])) {
            Log::error("Failed to generate wallet", ["error" => $response, 'token' => $token, 'payload' => $payload]);
            return get_error_response(['error' => $response]);
        }
    }
    


    public function wallet_webhook(Request $request)
    {
        Log::channel('deposit_error')->info('Coinpayment webhook received', [$request->all()]);

        // Check if the webhook indicates an error
        if ($request->status < 0 || $request->status == 'error') {
            Log::channel('deposit_error')->error('Coinpayment webhook error', [$request->all()]);
            return response()->json(['error' => 'Webhook indicates an error'], 400);
        }

        // Process the deposit
        try {
            // Find the user's wallet
            $wallet = CryptoWallets::where('wallet_address', $request->address)->first();

            if (!$wallet) {
                Log::channel('deposit_error')->info('Wallet not found for the given address', ["address" => $request->address]);
                throw new \Exception('Wallet not found for the given address');
            }

            // Retrieve the user associated with the virtual account
            $user = User::whereUserId($wallet->user_id)->first();

            // Create a new deposit record
            $deposit = new CryptoDeposit();
            $deposit->user_id = $user->id;
            $deposit->currency = $wallet->wallet_currency;
            $deposit->amount = $request->amount;
            $deposit->address = $request->address;
            $deposit->transaction_id = $request->txn_id;
            $deposit->status = 'success';
            $deposit->payload = $request->all();
            $deposit->save();


            if (!$user) {
                Log::channel('virtual_account')->error('User not found', [$wallet]);
            }

            // Get the wallet for the specified currency
            $wallet = $user->getWallet('usd');

            if ($wallet && $wallet->deposit($request->amount * 100)) {
            }

            $webhook_url = Webhook::whereUserId($user->id)->first();

            if ($webhook_url) {
                WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                    "event.type" => "crypto_deposit",
                    "payload" => $deposit->toArray()
                ])->dispatchSync();
            }

            return response()->json(['message' => 'Deposit processed successfully'], 200);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Error processing crypto deposit: ' . $e->getMessage());
            return response()->json(['error' => 'Error processing deposit'], 500);
        }
    }

    // public function walletWebhook(Request $request)
    // {
    //     Log::channel('deposit_error')->info('New crypto deposit webhook received', [
    //         'incoming_request' => $request->all(),
    //         'url' => $request->url(),
    //     ]);

    //     try {
    //         $payload = $request->all();

    //         // Determine webhook format (structure 1 or 2)
    //         $isV1 = isset($payload['event']) && isset($payload['data']);
    //         $isV2 = isset($payload['event_type']) && isset($payload['event_data']);

    //         if (!$isV1 && !$isV2) {
    //             return response()->json(['error' => 'Invalid webhook structure'], 422);
    //         }

    //         // Extract relevant fields
    //         $eventType = $isV1 ? $payload['event'] : $payload['event_type'];
    //         $data = $isV1 ? $payload['data'] : $payload['event_data'];
    //         $meta = $payload['metadata'] ?? [];

    //         // Only proceed if event type indicates a received or confirmed deposit
    //         if (!in_array($eventType, ['customer.deposit.received', 'customer.deposit.detected'])) {
    //             return response()->json(['message' => 'Event ignored'], 200);
    //         }

    //         $transactionId = $data['transaction_hash'] ?? $meta['webhook_id'] ?? null;
    //         $toAddress = $data['receiving_address'] ?? $data['to_address'] ?? null;
    //         $amountRaw = $data['amount_numeric'] ?? $data['amount'] ?? '0';
    //         $status = $data['status'] ?? 'processing';
    //         $chain = $data['chain'] ?? 'SOL';
    //         $tokenType = strtoupper(trim(($data['asset_name'] ?? 'USDC') . '_' . $chain));

    //         // Normalize amount
    //         $amount = (float) preg_replace('/[^\d.]/', '', $amountRaw);
    //         $fee = 0;

    //         if (!$transactionId || !$toAddress) {
    //             return response()->json(['error' => 'Missing required transaction fields'], 422);
    //         }

    //         // Check if already logged
    //         if (TransactionRecord::where('transaction_id', $transactionId)->exists()) {
    //             return response()->json(['message' => 'crypto Transaction already processed (TransactionRecord)'], 200);
    //         }

    //         // Match receiving wallet
    //         $wallet = CryptoWallets::where('wallet_address', $toAddress)->firstOrFail();
    //         $user = User::findOrFail($wallet->user_id);
    //         $userId = $user->id;
    //         $currency = $wallet->currency ?? $tokenType;

    //         // Create transaction log
    //         TransactionRecord::create([
    //             "user_id" => $userId,
    //             "transaction_beneficiary_id" => $userId,
    //             "transaction_id" => $transactionId,
    //             "transaction_amount" => $amount,
    //             "gateway_id" => null,
    //             "transaction_status" => $status,
    //             "transaction_type" => "crypto",
    //             "transaction_memo" => "crypto",
    //             "transaction_currency" => $currency,
    //             "base_currency" => $currency,
    //             "secondary_currency" => $chain,
    //             "transaction_purpose" => "crypto_deposit",
    //             "transaction_payin_details" => $payload,
    //             "transaction_payout_details" => [],
    //         ]);

    //         // Check if already exists in deposits table
    //         if (CryptoDeposit::where('transaction_id', $transactionId)->exists()) {
    //             return response()->json(['message' => 'Transaction already processed (CryptoDeposit)'], 200);
    //         }

    //         // Create the deposit
    //         $deposit = CryptoDeposit::create([
    //             'user_id' => $user->id,
    //             'currency' => $currency,
    //             'amount' => $amount,
    //             'address' => $toAddress,
    //             'transaction_id' => $transactionId,
    //             'status' => $status,
    //             'payload' => $payload,
    //         ]);

    //         // Credit user wallet
    //         $walletInstance = $user->getWallet('usd');
    //         if ($walletInstance) {
    //             $zeeFee = $fee * 0.30;
    //             $totalFee = $fee + $zeeFee;
    //             $creditAmount = $amount - $totalFee;
    //             $walletInstance->deposit(($creditAmount * 100), $deposit->toArray());
    //         } else {
    //             Log::channel('deposit_error')->warning('User wallet not found-crypto', ['user_id' => $user->id]);
    //         }

    //         // Notify user’s webhook if set
    //         if ($webhook = Webhook::whereUserId($user->id)->first()) {
    //             WebhookCall::create()->meta(['_uid' => $webhook->user_id])
    //                 ->url($webhook->url)
    //                 ->useSecret($webhook->secret)
    //                 ->payload([
    //                     'event.type' => 'crypto_deposit',
    //                     'payload' => $deposit->toArray()
    //                 ])
    //                 ->dispatchSync();
    //         }

    //         return response()->json(['message' => 'Deposit processed successfully'], 200);

    //     } catch (\Exception $e) {
    //         Log::channel('deposit_error')->error('Error processing crypto deposit', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         return response()->json(['error' => 'Error processing deposit'], 500);
    //     }
    // }

    public function walletWebhook(Request $request)
    {
        Log::channel('deposit_error')->info('New crypto deposit webhook received', [
            'incoming_request' => $request->all(),
            'url' => $request->url(),
        ]);

        try {
            $payload = $request->all();

            // Determine webhook format (structure 1 or 2)
            $isV1 = isset($payload['event']) && isset($payload['data']);
            $isV2 = isset($payload['event_type']) && isset($payload['event_data']);

            if (!$isV1 && !$isV2) {
                return response()->json(['error' => 'Invalid crypto webhook structure'], 422);
            }

            // Extract relevant fields
            $eventType = $isV1 ? $payload['event'] : $payload['event_type'];
            $data = $isV1 ? $payload['data'] : $payload['event_data'];
            $meta = $payload['metadata'] ?? [];

            // Only proceed if event type indicates a received or detected deposit
            if (!in_array($eventType, ['customer.deposit.received', 'customer.deposit.detected'])) {
                return response()->json(['message' => 'crypto Event ignored'], 200);
            }

            $transactionId = $data['transaction_hash'] ?? $meta['webhook_id'] ?? null;
            $toAddress = $data['receiving_address'] ?? $data['to_address'] ?? null;
            $amountRaw = $data['amount_numeric'] ?? $data['amount'] ?? '0';
            $status = 'success';
            $chain = $data['chain'] ?? 'SOL';
            $tokenType = strtoupper(trim(($data['asset_name'] ?? 'USDC') . '_' . $chain));

            // Normalize amount
            $amount = (float) preg_replace('/[^\d.]/', '', $amountRaw);
            $fee = 0;

            if (!$transactionId || !$toAddress) {
                return response()->json(['error' => 'Missing required transaction fields'], 422);
            }

            // Check if already logged
            if (TransactionRecord::where('transaction_id', $transactionId)->exists()) {
                return response()->json(['message' => 'crypto Transaction already processed (TransactionRecord)'], 200);
            }

            // Match receiving wallet
            $wallet = CryptoWallets::where('wallet_address', $toAddress)->firstOrFail();
            $user = User::findOrFail($wallet->user_id);
            $userId = $user->id;
            $currency = $wallet->currency ?? $tokenType;

            // Create transaction log
            TransactionRecord::create([
                "user_id" => $userId,
                "transaction_beneficiary_id" => $userId,
                "transaction_id" => $transactionId,
                "transaction_amount" => $amount,
                "gateway_id" => null,
                "transaction_status" => $status,
                "transaction_type" => "crypto",
                "transaction_memo" => "crypto",
                "transaction_currency" => $currency,
                "base_currency" => $currency,
                "secondary_currency" => $chain,
                "transaction_purpose" => "crypto_deposit",
                "transaction_payin_details" => $payload,
                "transaction_payout_details" => [],
            ]);

            // Check if already exists in deposits table
            if (CryptoDeposit::where('transaction_id', $transactionId)->exists()) {
                return response()->json(['message' => 'Transaction already processed (CryptoDeposit)'], 200);
            }

            // Create the deposit
            $deposit = CryptoDeposit::create([
                'user_id' => $user->id,
                'currency' => $currency,
                'amount' => $amount,
                'address' => $toAddress,
                'transaction_id' => $transactionId,
                'status' => $status,
                'payload' => $payload,
            ]);

            // Credit user wallet
            $walletInstance = $user->getWallet('usd');
            if ($walletInstance) {
                $zeeFee = $fee * 0.30;
                $totalFee = $fee + $zeeFee;
                $creditAmount = $amount - $totalFee;
                $walletInstance->deposit(($creditAmount * 100), $deposit->toArray());
            } else {
                Log::channel('deposit_error')->warning('User wallet not found (crypto)', ['user_id' => $user->id]);
            }

            // Notify user’s webhook if set
            if ($webhook = Webhook::whereUserId($user->id)->first()) {
                WebhookCall::create()->meta(['_uid' => $webhook->user_id])
                    ->url($webhook->url)
                    ->useSecret($webhook->secret)
                    ->payload([
                        'event.type' => 'crypto_deposit',
                        'payload' => $deposit->toArray()
                    ])
                    ->dispatchSync();
            }

            return response()->json(['message' => 'Deposit processed successfully'], 200);

        } catch (\Exception $e) {
            Log::channel('deposit_error')->error('Error processing crypto deposit', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Error processing deposit'], 500);
        }
    }

    public function getWallets()
    {
        try {
            $wallets = CryptoWallets::whereUserId(auth()->id())->with('customer')->latest()->paginate(per_page())->withQueryString();
            if ($wallets) {
                return paginate_yativo($wallets);
            }

            return get_error_response(['error' => "No wallet found!"], 404);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function depositHistories(Request $request)
    {
        try {
            $deposits = new CryptoDeposit();
            if ($history = $deposits->whereUserId(auth()->id())->with('customer')->paginate(per_page())) {
                return paginate_yativo($history);
            }

            return get_error_response(['error' => 'Error: please contact support']);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function depositHistory($depositId)
    {
        try {
            $deposits = new CryptoDeposit();
            if ($history = $deposits->whereUserId(auth()->id())->with('customer')->whereId($depositId)->latest()->first()) {
                return get_success_response($history);
            }

            return get_error_response(['error' => 'Error: please contact support']);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    public function deleteWallet($walletId)
    {
        try {
            $wallet = CryptoWallets::whereUserId(auth()->id())->whereId($walletId)->first();

            if ($wallet && $wallet->delete()) {
                $userId = $wallet->user_id;
                $webhook_url = Webhook::whereUserId($userId)->first();
                WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                    "event.type" => "wallet.deleted",
                    "payload" => $wallet
                ]);

                return get_success_response(['message' => "Crypto wallet deleted successfully, fund send to this wallet will be lost and can't be recovered"]);
            }

            return get_error_response(['error' => 'Wallet with the provided data not found']);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * get deposit hostories for a single wallet address
     * 
     * @param string walletAddresses
     * 
     * @return mixed
     */
    public function walletHistories($walletAddresses)
    {
        try {
            $deposits = new CryptoDeposit();
            $history = $deposits->whereUserId(auth()->id())
                ->with('customer')
                ->whereAddress($walletAddresses)
                ->paginate(per_page())->withQueryString();

            if ($history->isNotEmpty()) {
                return paginate_yativo($history);
            }

            return get_error_response(['error' => 'No deposit history found for the given wallet address']);
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * Get all wallet address for a customer
     * 
     * @param string UUID customerId
     * 
     * @return mixed
     */
    public function customerWallets(Request $request, $customerId)
    {
        $walletAddresses = CryptoWallets::where([
            "user_id" => auth()->id(),
            "customer_id" => $customerId
        ])->paginate(per_page())->withQueryString();

        return paginate_yativo($walletAddresses);
    }

    public function addCustomer()
    {
        try {
            $request = request();
            $user = auth()->user();

            if (!empty($user->yativo_customer_id)) {
                return $user->yativo_customer_id;
            }

            $yativo = new CryptoYativoController();
            $token = $yativo->getToken();

            if (!$token) {
                return ['error' => 'Failed to authenticate with Yativo API'];
            }

            $payload = [
                "username" => explode("@", $user->email)[0] . rand(0, 9999),
                "email" => $user->email
            ];

            $curl = Http::withToken($token)->post($this->baseUrl . "customers/create-customer", $payload)->json();

            if (isset($curl['status']) && $curl['status'] === true) {
                $user->yativo_customer_id = $curl['data']['_id'];
                $user->save();
                Log::debug("Yativo User added successfully", ["user_id" => $curl['data']['_id']]);
                return $curl['data']['_id'];
            }

            Log::error("Failed to create yativo user", ["error" => $curl]);
            return ['error' => $curl['message'] ?? 'Unknown error'];
        } catch (\Throwable $th) {
            Log::error("Error encountered", ["error" => $th->getMessage()]);
            return ["error" => $th->getMessage()];
        }
    }

    public function yativo_webhook(Request $request)
    {
        //
    }
}

