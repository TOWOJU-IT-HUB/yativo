<?php

namespace Modules\Bitso\app\Services;

use App\Models\UserMeta;
use GuzzleHttp\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Beneficiary\app\Models\Beneficiary;

class BitsoServices
{
    public $baseUrl, $apiKey, $apiSecret, $requestPath, $client;

    public function __construct($requestPath = "/api/v3/withdrawals", $apiKey = "AUFEXQubph", $apiSecret = "115cdcaab9cc969acc2b0d70eb813635", $baseUrl = "https://bitso.com")
    {
        $this->apiKey = env('BITSO_API_KEY', $apiKey);
        $this->apiSecret = env('BITSO_SECRET_KEY', $apiSecret);
        $this->baseUrl = env('BITSO_BASE_URL', $baseUrl);
        $this->requestPath = $requestPath;
        $this->client = new Client(['base_uri' => $this->baseUrl]);
    }

    private function generatePayload(array $withdrawalData)
    {
        return json_encode($withdrawalData);
    }

    private function createSignature($nonce, $payload, $method = 'POST')
    {
        if(is_array($payload)) {
            $payload = json_encode($payload);
        }
        $signatureData = $nonce . $method . $this->requestPath . $payload;
        return hash_hmac('sha256', $signatureData, $this->apiSecret);
    }

    public function sendRequest($payload, $method = 'POST')
    {
        $nonce = round(microtime(true) * 1000);
        $signature = $this->createSignature($nonce, $payload);
        $authHeader = "Bitso {$this->apiKey}:$nonce:$signature";
        $url = $this->requestPath;

        Log::info(json_encode([
            "payload" => $payload,
            "signature" => $signature,
            "authHeader" => $authHeader,
            "url" => $url,
            "nonce" => $nonce
        ]));

        try {
            $options = [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Content-Type' => 'application/json',
                ],
                'body' => $payload,
                'timeout' => 30,
            ];

            $response = $this->client->request($method, $url, $options);

            $responseBody = json_decode($response->getBody()->getContents(), true);
            $httpCode = $response->getStatusCode();

            Log::info('Bitso response body: ', $responseBody);

            if ($httpCode !== 200) {
                if (isset($responseBody['error'])) {
                    return ['error' => $responseBody['error']['message']];
                }

                // check for mxn account
                if(isset($responseBody['success']) && $responseBody['success'] == true && isset($responseBody['payload'])) {
                    return $responseBody['payload'];
                }
                return ['error' => $responseBody];
            }

            if (isset($responseBody["success"]) && $responseBody["success"] == true) {
                return $responseBody['payload'];
            }

            Log::info("Bitso transaction result: ", ['response' => $responseBody]);
            return $responseBody;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error($e->getMessage());
            return ['error' => 'Request failed', 'details' => $e->getMessage()];
        }
    }

    public function initiateWithdrawal(array $withdrawalData)
    {
        $payload = $this->generatePayload($withdrawalData);
        return $this->sendRequest($payload);
    }

    public function getWallet()
    {
        $baseUrl = "https://bitso.com";
        $endpoint = "/spei/v1/clabes";

        $data = '';
        $JSONPayload = $data;
        $nonce = time() * 1000;

        $request = generateSignature($nonce, $baseUrl . $endpoint, $endpoint, "POST", $JSONPayload);
        //($nonce, $baseUrl . $endpoint, $endpoint, "GET", $JSONPayload);
        $result = json_decode($request, true);
        return $result;
    }

    public function getMexAccount()
    {
        $result = $this->sendRequest('', 'POST');
        return $result;
    }

    public function getDepositWallet($amount)
    {
        $this->requestPath = "/api/v3/payments/usd/bridge/deposit-intents";
        $payload = json_encode(['amount' => $amount]);
        $result = $this->sendRequest($payload, 'POST');
        return $result;
    }

    public function depositCop($amount, $cellphone, $email, $documentType, $documentNumber, $fullName)
    {
        $this->requestPath = "/api/v3/funding_details/pse/payment_links";
        $callback_url = request()->redirect_url ?? "https://app.yativo.com";
        $data = [
            "amount" => $amount,
            "cellphone" => $cellphone,
            "email" => $email,
            "document_type" => $documentType,
            "document_number" => $documentNumber,
            "full_name" => $fullName,
            "callback_url" => base64_encode($callback_url)
        ];

        $payload = json_encode($data);
        return $this->sendRequest($payload, 'POST');
    }

    public function payout($data)
    {
        return $this->initiateWithdrawal($data);
    }

    public function storeUSDAccount($account_id, $account_name, $account_number, $bank_name, $bank_code, $bank_address, $bank_city, $bank_country, $bank_phone, $bank_email)
    {
        try {
            $this->requestPath = "/api/v3/payments/usd/bridge/external-accounts";
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
            $payload = json_encode($data);
            return $this->sendRequest($payload, 'POST');
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            return ['error' => 'Failed to store USD account details'];
        }
    }

    public function getDepositStatus($fid, $payload)
    {
        $this->requestPath = "/api/v3/fundings/{$fid}";
        $request = $this->sendRequest($payload, 'GET');
        return $request;
    }
}
