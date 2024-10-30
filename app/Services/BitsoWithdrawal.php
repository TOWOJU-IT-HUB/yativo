<?php

namespace App\Services;

class BitsoWithdrawal
{
    private $apiKey;
    private $apiSecret;
    private $baseUrl;
    private $requestPath;

    public function __construct($apiKey, $apiSecret, $baseUrl, $requestPath)
    {
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->baseUrl = $baseUrl;
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

        if ($httpCode !== 200) {
            return ['error' => $response];
        }

        return $response;
    }

    public function initiateWithdrawal(array $withdrawalData)
    {
        $payload = $this->generatePayload($withdrawalData);
        return $this->sendRequest($payload);
    }
}
