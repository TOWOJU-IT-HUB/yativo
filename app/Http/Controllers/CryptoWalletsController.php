<?php

namespace App\Http\Controllers;

use App\Models\CryptoDeposit;
use App\Models\CryptoWallets;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\CoinPayments\app\Services\CoinpaymentServices;
use Modules\Webhook\app\Models\Webhook;
use Spatie\WebhookServer\WebhookCall;

class CryptoWalletsController extends Controller
{
    public $coinpayment;

    public function __construct()
    {
        $apiKey = getenv("COINPAYMENT_PRIVATE_KEY");
        $secretKey = getenv("COINPAYMENT_PUBLIC_KEY");
        $this->coinpayment = new CoinpaymentServices($apiKey, $secretKey);
    }

    public function createWallet(Request $request)
    {
        Log::info('Incoming request data:', $request->all());

        $validate = Validator::make($request->all(), [
            'currency' => 'required|in:USDT.BEP20,USDC.BEP20',
            'customer_id' => 'required_if:is_customer,true'
        ]);

        if ($validate->fails()) {
            return get_error_response($validate->errors()->toArray());
        }

        // Send request to generate wallet address to Coinpayment
        $userId = auth()->id();
        $callbackUrl = route('crypto.wallet.address.callback', ['userId' => $userId, 'currency' => $request->currency]);
        $curl = $this->coinpayment->GetCallbackAddress(currency: $request->currency, ipn_url: $callbackUrl);

        if (isset($curl['address'])) {
            $record = CryptoWallets::create([
                "user_id" => $userId,
                "is_customer" => filter_var($request->is_customer, FILTER_VALIDATE_BOOLEAN),
                "customer_id" => $request->is_customer ? $request->customer_id : null,
                "wallet_address" => $curl['address'],
                "wallet_currency" => $request->currency,
                "wallet_network" => explode('.', $request->currency)[1] ?? $request->currency,
                "wallet_provider" => 'coinpayment',
                "coin_name" => $request->currency,
                "wallet_balance" => 0,
            ]);

            $webhook_url = Webhook::whereUserId($userId)->first();

            if ($webhook_url) {
                WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                    "event.type" => "wallet_generated",
                    "payload" => $record
                ])->dispatchSync();
            }

            if ($request->is_customer == true) {
                $record = CryptoWallets::whereId($record->id)->with('customer')->first();
            } else {
                $record = CryptoWallets::whereId($record->id)->with('user')->first();
            }

            return get_success_response($record); // Return the created record directly
        }

        return get_error_response(['error' => "We're currently unable to process your request at the moment."]);
    }


    public function wallet_webhook(Request $request)
    {
        Log::channel('deposit_error')->info('Coinpayment webhook received', [$request->all()]);

        // Verify the webhook signature (if provided by CoinPayments)
        if (!$this->coinpayment->ipn($request)) {
            Log::channel('deposit_error')->info('Invalid Signature');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

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

            if ($wallet && $wallet->deposit($request->amount)) {
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

    public function walletWebhook(Request $request, $userId, $currency)
    {
        Log::channel('deposit_error')->info('Coinpayment webhook received', [
            'incoming_request' => $request->all(),
            'url' => $request->url(),
        ]);

        // Verify the webhook signature (if provided by CoinPayments)
        if (!$this->coinpayment->ipn($request)) {
            Log::channel('deposit_error')->info('Invalid Signature');
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Process the deposit
        try {
            // Find the user's wallet
            $wallet = CryptoWallets::where('user_id', $userId)
                ->where('wallet_currency', $currency)
                ->where('wallet_address', $request->address)
                ->firstOrFail();

            // Check if the transaction already exists
            if (CryptoDeposit::where('transaction_id', $request->txn_id)->exists()) {
                return response()->json(['message' => 'Transaction already processed'], 200);
            }

            // Create a new deposit record
            $deposit = CryptoDeposit::create([
                'user_id' => $userId,
                'currency' => $currency,
                'amount' => $request->amount,
                'address' => $request->address,
                'transaction_id' => $request->txn_id,
                'status' => 'success',
                'payload' => $request->all(),
            ]);

            // Update wallet balance
            $user = User::findOrFail($wallet->user_id);
            // Get the wallet for the specified currency
            $walletInstance = $user->getWallet('usd');

            if ($walletInstance) {
                $coinFee = $request->fee ?? 0;
                $zeeFee = $coinFee * 0.30;
                $fee = $coinFee + $zeeFee;
                $creditAmount = $request->amount - $fee;

                // Credit the user's wallet
                $walletInstance->deposit(($creditAmount * 100), $deposit->toArray());
            } else {
                throw new \Exception('Wallet not found for the specified currency');
            }

            $webhook_url = Webhook::whereUserId($userId)->first();
            // Send a webhook notification if available
            if ($webhook = Webhook::whereUserId($userId)->first()) {
                WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])
                    ->url($webhook->url)
                    ->useSecret($webhook->secret)
                    ->payload(['event.type' => 'crypto_deposit', 'payload' => $deposit->toArray()])
                    ->dispatchSync();
            }

            return response()->json(['message' => 'Deposit processed successfully'], 200);
        } catch (\Exception $e) {
            Log::channel('deposit_error')->error('Error processing crypto deposit', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error processing deposit'], 500);
        }
    }


    public function getWallets()
    {
        try {
            $wallets = CryptoWallets::whereUserId(auth()->id())->with('customer')->latest()->paginate(per_page());
            if ($wallets) {
                return paginate_yativo($wallets);
            }

            return get_error_response(['error' => "No wallet found!"], 404);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
            return get_error_response(['error' => $th->getMessage()]);
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
                ->paginate(per_page());

            if ($history->isNotEmpty()) {
                return paginate_yativo($history);
            }

            return get_error_response(['error' => 'No deposit history found for the given wallet address']);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
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
        ])->paginate(per_page());

        return paginate_yativo($walletAddresses);
    }
}

