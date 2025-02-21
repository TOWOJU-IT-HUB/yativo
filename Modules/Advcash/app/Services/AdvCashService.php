<?php

namespace Modules\Advcash\app\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use SoapClient, SoapFault;

class AdvCashService
{
    protected $apiKey;
    protected $apiPassword;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.advcash.api_key');
        $this->apiPassword = config('services.advcash.api_password');
        $this->apiUrl = 'https://api.advcash.com/v1/';
    }

    public function initiatePayment($amount, $currency, $description)
    {
        $client = new Client();

        $response = $client->post($this->apiUrl . 'createPayment', [
            'json' => [
                'access_token' => $this->apiKey,
                'amount' => $amount,
                'currency' => $currency,
                'note' => $description,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function handleCallback($callbackData)
    {
        Log::info("AdvCash Webhook Notification using request class: ", $callbackData);
        // Implement your logic to handle the callback data
        // Verify the callback data against your records and mark the transaction as completed
        // Update your database, etc.

        return ['status' => 'success'];
    }

    public function getPaymentStatus($paymentId)
    {
        try {
            $adv = new AdvCashService();
            $transaction = $adv->processAdvCashPayout('findTransaction', $paymentId);
            return $transaction;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function processAdvCashPayout(string $action = 'sendMoney', string|array $arg1)
    {
        
        // return $this->advToken(); //phpinfo();
        $wsdl = 'https://account.volet.com/wsm/apiWebService?wsdl';

        try {
            // Initialize SOAP client
            $client = new SoapClient($wsdl);

            $params = [
                'arg0' => [
                    'apiName' => 'Yativo 1',
                    'authenticationToken' => $this->advToken(),
                    'accountEmail' => env("ADVCASH_EMAIL"),
                ],

                'arg1' => $arg1
            ];

            // Make the API call
            $response = $client->__soapCall($action, [$params]);
            echo "Transaction ID: " . $response->return;
        } catch (SoapFault $e) {
            // Handle any errors
            return ['error' . $e->getMessage()];
        }
    }

    private function advToken()
    {
        $apiPassword = env('ADVCASH_PASSWORD');
        $date = Carbon::now('UTC');
        $dateString = $date->format('Ymd');
        $timeString = $date->format('H');
        $textToHash = "$apiPassword:$dateString:$timeString";
        $authenticationToken = hash('sha256', data: $textToHash);
        return ($authenticationToken);
    }
}
