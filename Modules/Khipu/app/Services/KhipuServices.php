<?php

namespace Modules\Khipu\app\Services;

use Khipu\Configuration;
use Khipu\ApiClient;
use Khipu\Client\PaymentsApi;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


class KhipuServices
{
    public function __construct()
    {
    }

    public function get($id)
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => env('KHIPU_API_KEY'),
            ])->get("https://payment-api.khipu.com/v3/payments/{$id}");

            if ($response->successful()) {
                return $response->json(); // Returns decoded JSON as array
            } else {
                return [
                    'error' => $response->body()
                ];
            }
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    public function makePayment($txn_id, $amount, $currency = "CLP")
    {
        try {
            $payload = [
                "amount" => $amount,
                "currency" => $currency,
                "subject" => "Yativo wallet deposit",
                "transaction_id" => $txn_id,
                "return_url" => request('return_url'),
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => env('KHIPU_API_KEY'),
            ])->post('https://payment-api.khipu.com/v3/payments', $payload);

            if ($response->successful()) {
                return $response->json(); // Returns array
            } else {
                return [
                    'error' => $response->body()
                ];
            }
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    public function webhook($incoming)
    {
        // Replace with your actual merchant secret key
        $mySecret = env('KHIPU_WEBHOOK_SECRET');

        // Get the x-khipu-signature header
        $xKhipuSignature = request()->header('x-khipu-signature'); // e.g. t=1711965600393,s=GYzpjnXlTKQ+BJY7pZJmrM6DZgWMSJdtOr/dleBKTdg=

        $tValue = null;
        $sValue = null;

        // Parse the signature header
        $signatureParts = explode(',', $xKhipuSignature);
        foreach ($signatureParts as $part) {
            [$key, $value] = explode('=', $part, 2);
            if ($key === 't') {
                $tValue = $value;
            } elseif ($key === 's') {
                $sValue = $value;
            }
        }

        // Log received signature
        Log::info('Received hash: ' . $sValue);

        // Get raw JSON payload (body of the request)
        $jsonPayload = $incoming;

        // Prepare data to hash
        $toHash = "{$tValue}.{$jsonPayload}";

        // Generate HMAC hash (base64 encoded)
        $calculatedHash = base64_encode(hash_hmac('sha256', $toHash, $mySecret, true));

        // Log generated hash
        Log::info('Generated hash: ' . $calculatedHash);

        // Verify signature
        if ($calculatedHash === $sValue) {
            Log::info('✅ HMAC is correct');
            // Proceed with processing the webhook
            return true;
        } else {
            Log::warning('❌ Message was tampered');
            abort(403, 'Invalid signature.');
        }

    }
}