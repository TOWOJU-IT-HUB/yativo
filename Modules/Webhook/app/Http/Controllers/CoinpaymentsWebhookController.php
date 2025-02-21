<?php

namespace Modules\Webhook\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CryptoWallets;
use App\Models\Deposit;
use App\Models\User;
use Illuminate\Http\Request;
use Str;

class CoinpaymentsWebhookController extends Controller
{
    public function handleDeposit(Request $request)
    {
        \Log::info('Coinpayments webhook received:', $request->all());

        try {
            // Validate webhook signature and required fields
            if (!$this->validateWebhook($request)) {
                return response()->json(['error' => 'Invalid webhook request'], 400);
            }

            $txnId = $request->input('txn_id');
            $address = $request->input('address');
            $amount = $request->input('amount');
            $currency = $request->input('currency');
            $status = $request->input('status');

            // Check if wallet address exists in cryptowallets
            $wallet = CryptoWallets::where('address', $address)
                ->where('currency', strtoupper($currency))
                ->first();

            if ($wallet) {
                // Process deposit for existing wallet
                return $this->processExistingWalletDeposit($wallet, $amount, $txnId, $status);
            }

            // Check deposit table for pending transaction
            $deposit = Deposit::where('transaction_id', $txnId)
                ->orWhere('address', $address)
                ->first();

            if (!$deposit) {
                \Log::error('No matching wallet or deposit found for address: ' . $address);
                return response()->json(['error' => 'No matching wallet found'], 404);
            }

            // Create user if doesn't exist
            $user = User::firstOrCreate(
                ['email' => $deposit->email],
                [
                    'name' => $deposit->name ?? 'User_' . time(),
                    'password' => bcrypt(Str::random(12)),
                    'status' => 'active'
                ]
            );

            // Update deposit record
            $deposit->update([
                'status' => $this->mapCoinpaymentsStatus($status),
                'amount_received' => $amount,
                'transaction_id' => $txnId,
                'user_id' => $user->id
            ]);

            // Create wallet for user if doesn't exist
            $wallet = CryptoWallets::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'currency' => strtoupper($currency),
                    'address' => $address
                ]
            );

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            \Log::error('Coinpayments webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    private function validateWebhook(Request $request)
    {
        // Implement signature validation logic here
        $merchantId = config('services.coinpayments.merchant_id');
        $ipnSecret = config('services.coinpayments.ipn_secret');

        // Verify merchant
        if ($request->input('merchant') !== $merchantId) {
            return false;
        }

        // Verify IPN signature
        $hmac = hash_hmac('sha512', $request->getContent(), $ipnSecret);
        if ($hmac !== $request->header('HMAC')) {
            return false;
        }

        return true;
    }

    private function processExistingWalletDeposit($wallet, $amount, $txnId, $status)
    {
        // Create deposit record
        Deposit::create([
            'user_id' => $wallet->user_id,
            'currency' => $wallet->currency,
            'amount' => $amount,
            'status' => $this->mapCoinpaymentsStatus($status),
            'transaction_id' => $txnId,
            'address' => $wallet->address
        ]);

        return response()->json(['success' => true]);
    }

    private function mapCoinpaymentsStatus($status)
    {
        $statusMap = [
            '0' => 'pending',
            '1' => 'confirmed',
            '100' => 'complete',
            '-1' => 'cancelled',
        ];

        return $statusMap[$status] ?? 'pending';
    }

}
