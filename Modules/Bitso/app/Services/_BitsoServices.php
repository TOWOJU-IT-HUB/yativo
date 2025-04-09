<?php

namespace Modules\Bitso\app\Services;

use App\Models\UserMeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Modules\Beneficiary\app\Models\Beneficiary;

class _BitsoServices
{

    public $baseUrl, $apiKey, $apiSecret, $requestPath;

    public function __construct($apiKey = "", $apiSecret = "", $baseUrl = "https://bitso.com", $requestPath = "/api/v3/withdrawals")
    {
        $this->apiKey = env('BITSO_API_KEY', $apiKey);
        $this->apiSecret = env('BITSO_SECRET_KEY', $apiSecret);
        $this->baseUrl = env('BITSO_BASE_URL', $baseUrl);
        $this->requestPath = $requestPath;

    }

    private function generatePayload(array $withdrawalData)
    {
        return json_encode($withdrawalData);
    }

    private function createSignature($nonce, $payload)
    {
        $signatureData = $nonce . 'POST' . $this->requestPath . $payload;
        return hash_hmac('sha256', $signatureData, $this->apiSecret);
    }

    private function sendRequest($payload)
    {
        $nonce = round(microtime(true) * 1000);
        $signature = $this->createSignature($nonce, $payload);
        $authHeader = "Bitso {$this->apiKey}:$nonce:$signature";
        $url = $this->baseUrl . $this->requestPath;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: $authHeader",
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            return ['error' => $errorMsg];
        }

        curl_close($ch);

        if (!is_array($response)) {
            $response = json_decode($response, true);
        }

        if ($httpCode !== 200) {
            if (isset($response['error'])) {
                return ['error' => $response['error']['message']];
            }

            return ['error' => $response];
        }

        if (isset($response["success"]) && $response["success"] == true) {
            return $response['payload'];
        }
        return $response;
    }

    public function initiateWithdrawal(array $withdrawalData)
    {
        $payload = $this->generatePayload($withdrawalData);
        return $this->sendRequest($payload);
    }

    /**
     * Get funding details from Bitso
     */
    public function getWallet()
    {

        $endpoint = "/spei/v1/clabes";

        $JSONPayload = '';
        $request = $this->getData($endpoint, "GET", $JSONPayload);
        return $request;
    }

    
    private function getData($RequestPath, $HTTPMethod, $JSONPayload)
    {

        $baseUrl = $this->baseUrl;
        $endpoint = $RequestPath ?? "/spei/v1/clabes";
        $method = $HTTPMethod ?? "GET";

        $data = '';
        $JSONPayload = $data;
        $nonce = time() * 1000;

        $request = generateSignature($nonce, $baseUrl . $endpoint, $endpoint, $method, $JSONPayload);
        //($nonce, $baseUrl . $endpoint, $endpoint, "GET", $JSONPayload);
        $result = json_decode($request, true);
        // if(isset($result['status']) && $result['status'] == 400){
        //     return ['error' => $result['detail']];
        // }

        return $result;

        // $nonce = time() * 1000;
        // $baseUrl = "https://bitso.com";
        // $endpoint = "/spei/v1/clabes";
        // $result = getData($nonce, $baseUrl . $endpoint, $endpoint, "GET", $JSONPayload);
        // // $result = getData($nonce, $this->url . $RequestPath, $RequestPath, $HTTPMethod, $JSONPayload);

        // return $result;
    }

    /**
     * Get funding details from Bitso
     */
    public function getDepositWallet($amount)
    {

        $endpoint = "/api/v3/payments/usd/bridge/deposit-intents";

        $JSONPayload = [
            'amount' => $amount,
        ];
        $request = $this->getData($endpoint, "POST", $JSONPayload);
        // var_dump($request); exit;
        return $request;
    }

    /**
     * send payout request to Bitso server
     */
    public function payout($amount, $clabe, $currency)
    {
        $beneficiary = Beneficiary::whereId(request()->beneficiary_id)->first();
        $customer = $beneficiary->customer_name;
        if (strtolower($currency) == 'mxn') {
            $data = [
                "method" => "praxis",
                "amount" => $amount,
                "currency" => "mxn",
                "beneficiary" => $customer,
                "clabe" => $clabe,
                "protocol" => "clabe",
            ];

        } else {
            return ['error' => "We currently can not process this currency"];
        }

        $curl = $this->initiateWithdrawal($data);

        return $curl;
    }


    /**
     * store USD payout account details on Bitso
     * @return mixed - account_id for future reference
     */
    public function storeUSDAccount($account_id, $account_name, $account_number, $bank_name, $bank_code, $bank_address, $bank_city, $bank_country, $bank_phone, $bank_email)
    {
        try {
            $endpoint = "/api/v3/payments/usd/bridge/external-accounts";
            $data = [
                'bank_name' => 'Bank of America',
                'account_owner_name' => 'Crypto Technology',
                'account_number' => '1283712372123255',
                'routing_number' => '026009593',
                'type' => 'wire',
                'address' => [
                    'street_line_1' => 'Boulevard Avenue',
                    'street_line_2' => 'Suite 373',
                    'city' => 'La Jolla',
                    'state' => 'CA',
                    'postal_code' => '92037',
                    'country' => 'USA',
                ],
            ];
            $JSONPayload = json_encode($data);
            $curl = $this->getData($endpoint, "POST", $JSONPayload);
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}



