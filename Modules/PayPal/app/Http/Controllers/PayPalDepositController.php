<?php

namespace Modules\PayPal\app\Http\Controllers;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use PayPalHttp\Environment;



class PayPalDepositController extends Controller
{
    protected $client;
    protected $paypalApiUrl;

    public function __construct()
    {
        $this->client = new Client();
        $this->paypalApiUrl = env('PAYPAL_ENVIRONMENT') === 'live'
            ? 'https://api.paypal.com'
            : 'https://api.sandbox.paypal.com';
    }

    public function createOrder(int $quoteId, float $amount, string $currency)
    {
        // Get PayPal API credentials from the config
        $clientId = config('paypal.client_id');
        $secret = config('paypal.secret');

        // Step 1: Get Access Token
        $response = $this->client->post("$this->paypalApiUrl/v1/oauth2/token", [
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
            ],
            'auth' => [$clientId, $secret],
            'form_params' => [
                'grant_type' => 'client_credentials',
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        $accessToken = $data['access_token'];

        // Step 2: Create Order
        $response = $this->client->post("$this->paypalApiUrl/v2/checkout/orders", [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $accessToken,
            ],
            'json' => [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => $amount,
                        ],
                    ],
                ],
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        $orderId = $data['id'];

        // Step 3: Get Approval Link
        $response = $this->client->get("$this->paypalApiUrl/v2/checkout/orders/$orderId", [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
            ],
        ]);

        $data = json_decode($response->getBody(), true);
        $approvalLink = collect($data['links'])->firstWhere('rel', 'approve')['href'];

        // update api resonse to sendMoney db
        updateSendMoneyRawData($quoteId, $data);
        // Step 4: Redirect User to PayPal Approval Link
        return $approvalLink;
    }
}
