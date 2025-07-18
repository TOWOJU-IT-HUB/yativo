<?php

namespace App\Services;

use App\Models\PayinMethods;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OnrampService
{
    protected $apiKey;
    protected $apiId;
    protected $walletPrivateKey;
    protected $walletAddress;
    protected $client;


    public function __construct()
    {
        $this->apiKey = env('ONRAMP_API_KEY');
        $this->apiSecret = env('ONRAMP_API_SECRET');
        $this->apiId = env('ONRAMP_APP_ID');
        $this->client = new Client();
    }

    // $currency = $data['currency'];
    // if (!in_array($currency, [12, 2, 1])) { // EUR => 12, TLR => 2, INR => 1
    //     return [
    //         'error' => 'Currency not supported'
    //     ];
    // }
    
    public function generateSignature($payload)
    {
        return hash_hmac('sha512', base64_encode(json_encode($payload)), $this->apiSecret);
    }

    // public function getQuotes($data)
    // {
    //     $timestamp = round(microtime(true) * 1000);
    //     $payload = [
    //         'timestamp' => $timestamp,
    //         'body' => $data
    //     ];

    //     $signature = $this->generateSignature($payload);
    //     $headers = [
    //         'Accept' => 'application/json',
    //         'Content-Type' => 'application/json;charset=UTF-8',
    //         'X-ONRAMP-SIGNATURE' => $signature,
    //         'X-ONRAMP-APIKEY' => $this->apiKey,
    //         'X-ONRAMP-PAYLOAD' => base64_encode(json_encode($payload))
    //     ];

    //     $response = Http::withHeaders($headers)->post('https://api.onramp.money/onramp/api/v2/common/transaction/quotes', $data);

    //     return $response->json();
    // }

    /**
     * @param string redirectUrl
     * @param string paymentMethod
     * @param string walletAddress
     * @param string network
     * @param string coinCode
     * @param string fiatAmount
     */
    public function payIn($data)
    {
        $result = PayinMethods::whereId(request()->gateway)->first();

        $countryId = [
            "INR" => 1,
            "TRY" => 2,
            "AED" => 3,
            "LKR" => 32,
            "THB" => 27,
            "IDR" => 14,
            "PHP" => 11,
            "VND" => 5,
        ];

        $queries = [
            'redirectUrl' => route('onramp.payIn.callback'),
            'appId' => $this->apiId,
            'paymentMethod' => $result->payment_mode, // 1 -> Instant transfer (e.g. UPI) 2 -> Bank transfer (e.g. IMPS/FAST)
            'walletAddress' => env('wallet_address'),
            'network' => 'bep20',
            'coinCode' => 'USDT' ?? 'USDC',
            'fiatType' => $data['fiat_type'] ?? $countryId[$result->currency],
            'fiatAmount' => $data['amount']

        ];
        $queriedUrl['onramp'] = $queries;
        Log::info("onramp", $queriedUrl);
        return $queriedUrl;
    }

    public function payOut($data)
    {
        $queries = [
            'redirectUrl' => route('onramp.payOut.callback'),
            'appId' => $this->apiId,
            'network' => 'bep20',
            'coinCode' => 'USDT' ?? 'USDC',
            'fiatAmount' => $data['amount'],
            'merchantRecognitionId' => generate_uuid(),
            'fiatType' => $data['fiat_type'],

        ];
        $queriedUrl = 'https://onramp.money/main/sell?' . http_build_query($queries);
        return $queriedUrl;
    }

    public function payInCallback()
    {
        $request = request();
        // Log the received data or handle it as needed
        Log::info('Webhook received', ['data' => $request->all()]);
        return response()->json(['message' => 'Received data :)'], 200);
    }
    
    public function orderStatus($orderId, $orderType = 1)
    {
        try {
            $body = [
                'orderId' => $orderId,
                'type' => $orderType
            ];
    
            $payload = [
                "timestamp" => round(microtime(true) * 1000), // Convert to milliseconds
                "body" => $body
            ];
    
            $api_key = $this->apiKey;
            $api_secret = $this->apiSecret;
    
            $encoded_payload = base64_encode(json_encode($payload));
            $signature = hash_hmac('sha512', $encoded_payload, $api_secret);
    
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json;charset=UTF-8',
                'X-ONRAMP-SIGNATURE' => $signature,
                'X-ONRAMP-APIKEY' => $api_key,
                'X-ONRAMP-PAYLOAD' => $encoded_payload
            ];
    
            $url = 'https://api.onramp.money/onramp/api/v2/common/transaction/orderStatus';
    
            $response = Http::withHeaders($headers)->post($url, $body);
    
            if ($response->failed()) {
                Log::error('Order Status API Error:', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'error' => true
                ]);
                throw new \Exception('Failed to retrieve order status.');
            }
    
            return $response->json(); 
        } catch (\Exception $e) {
            Log::error('Order Status Request Failed: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
    
}
