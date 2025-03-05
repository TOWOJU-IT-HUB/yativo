<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Log;

class ex_VitaBusinessAPI
{
    private $secretKey;
    private $xLogin;
    private $xApiKey;
    private $xTransKey;

    public function __construct()
    {
        // Set these from env variables or config file
        $this->secretKey = env('VITA_SECRET_KEY');
        $this->xLogin = env('VITA_X_LOGIN');
        $this->xApiKey = env('VITA_X_API_KEY');
        $this->xTransKey = env('VITA_X_TRANS_KEY');
    }

    /**
     * Generate HMAC-SHA256 Signature
     *
     * @param array|string|null $requestBody
     * @param string $xDate
     * @return string
     */
    private function generateSignature($requestBody, $xDate)
    {
        Log::info("vita controller 004");
        // Sort and concatenate the request body (if it's not null or empty)
        $sortedRequestBody = $this->getSortedRequestBody($requestBody);

        // Create the signature base string (X-Login + X-Date + RequestBody)
        $signatureBase = $this->xLogin . $xDate . $sortedRequestBody;

        // Generate HMAC-SHA256 hash
        return hash_hmac('sha256', $signatureBase, $this->secretKey);
    }

    /**
     * Sort and concatenate request body
     *
     * @param array|null $requestBody
     * @return string
     */
    private function getSortedRequestBody($requestBody)
    {
        if (empty($requestBody)) {
            return ''; // If request body is empty, return empty string
        }

        Log::info("vita controller 005");
        // Sort the request body by keys
        ksort($requestBody);

        // Concatenate all key-value pairs without separators
        return implode('', array_map(function ($key, $value) {
            return $key . $value;
        }, array_keys($requestBody), $requestBody));
    }

    /**
     * Make a signed request to Vita Business API
     *
     * @param string $endpoint
     * @param array|null $body
     * @param string $method (default: post)
     * @return \Illuminate\Http\Client\Response
     */
    public function makeSignedRequest($endpoint, array $body = null, $method = "post")
    {
        $xDate = now()->toISOString();

        Log::info("vita controller 003");
        // Handle GET requests separately, passing an empty string as the body
        $sortedRequestBody = $method === 'get' ? '' : $this->getSortedRequestBody($body);
        $signature = $this->generateSignature($sortedRequestBody, $xDate);

        // Prepare headers
        $headers = [
            'x-date' => $xDate,
            'x-login' => $this->xLogin,
            'X-Trans-Key' => $this->xTransKey,
            'Authorization' => 'V2-HMAC-SHA256, Signature: ' . $signature,
        ];

        // Only include Content-Type header for non-GET requests
        if ($method !== 'get') {
            $headers['Content-Type'] = 'application/json';
        }

        // Make the HTTP request with the headers, using the correct method
        if ($method === 'get') {
            $req = Http::withHeaders($headers)->get($endpoint, $body);
        } else {
            $req = Http::withHeaders($headers)->$method($endpoint, $body);
        }

        // Log the request details for debugging
        Log::info('Request Headers:', [
            "date" => $xDate,
            "signature" => $signature,
            "response" => $req->json()
        ]);

        return $req;
    }
}
