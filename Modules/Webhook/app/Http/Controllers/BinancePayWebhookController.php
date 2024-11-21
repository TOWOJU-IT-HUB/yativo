<?php

namespace Modules\Webhook\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BinancePayWebhookController extends Controller
{
    /**
     * Handle Binance Pay webhook notifications
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        $payload = $request->all();

        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Handle different event types
        switch ($payload['bizType']) {
            case 'PAY':
                return $this->handleDeposit($payload);
            case 'PAYOUT':
                return $this->handlePayout($payload);
            default:
                return response()->json(['error' => 'Unsupported event type'], 400);
        }
    }

    /**
     * Verify Binance Pay webhook signature
     *
     * @param Request $request
     * @return bool
     */
    private function verifySignature(Request $request): bool
    {
        $payload = $request->getContent();
        $signature = $request->header('BinancePay-Signature');
        $timestamp = $request->header('BinancePay-Timestamp');
        $nonce = $request->header('BinancePay-Nonce');

        // Get API key and secret from config
        $apiKey = config('binancepay.api_key');
        $apiSecret = config('binancepay.api_secret');

        // Construct the string to sign
        $stringToSign = $timestamp . "\n" . $nonce . "\n" . $payload . "\n";

        // Generate signature
        $calculatedSignature = hash_hmac('sha512', $stringToSign, $apiSecret);

        return hash_equals($signature, $calculatedSignature);
    }

    /**
     * Handle deposit notifications
     *
     * @param array $payload
     * @return 
     */
    private function handleDeposit(array $payload)
    {
        // Extract payment information
        $orderId = $payload['merchantTradeNo'];
        $amount = $payload['orderAmount'];
        $currency = $payload['currency'];
        $status = $payload['status'];

        try {
            // Update payment status in database
            if ($status === 'SUCCESS') {
                // Process successful payment
                // Add your implementation here

                return response()->json(['message' => 'Deposit processed successfully']);
            } elseif ($status === 'FAILED') {
                // Handle failed payment
                // Add your implementation here

                return response()->json(['message' => 'Deposit failed']);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle payout notifications
     *
     * @param array $payload
     * @return 
     */
    private function handlePayout(array $payload)
    {
        // Extract payout information
        $transferId = $payload['transferId'];
        $amount = $payload['amount'];
        $currency = $payload['currency'];
        $status = $payload['status'];

        try {
            // Update payout status in database
            if ($status === 'SUCCESS') {
                // Process successful payout
                // Add your implementation here

                return response()->json(['message' => 'Payout processed successfully']);
            } elseif ($status === 'FAILED' OR $status === 'EXPIRED') {
                // Handle failed payout
                // Add your implementation here

                return response()->json(['message' => 'Payout failed']);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

}
