<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\Http;
use Log;
use App\Services\Configuration;

class VitaBusinessAPI
{
    protected $xLogin, $xApiKey, $xTransKey, $secretKey, $xMode;
    private static $instance;
    private $credentials = [
        'X_Login' => null,
        'X_Trans_Key' => null,
        'secret' => null,
        'env' => null,
        'isDevelopment' => false,
        'BASE_URL' => null,
    ];

    public static $STAGE = 'stage';

    public function __construct()
    {
        // You can set these from env variables or config file
        $this->secretKey = env('VITA_SECRET_KEY');
        $this->xLogin = env('VITA_X_LOGIN');
        $this->xApiKey = env('VITA_X_API_KEY');
        $this->xTransKey = env('VITA_X_TRANS_KEY');
        $this->xMode = env('VITA_MODE');

        $this->credentials['X_Login'] = $this->xLogin;
        $this->credentials['X_Trans_Key'] = $this->xTransKey;
        $this->credentials['secret'] = $this->secretKey;
        $this->credentials['env'] = $this->xMode;
        $this->credentials['BASE_URL'] = env('VITA_BASE_URL', 'https://api.stage.vitawallet.io/api/businesses');
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate HMAC-SHA256 Signature
     *
     * @param array|string $requestBody
     * @param string $xDate
     * @return string
     */
    private function generateSignature(array|string $requestBody, $xDate)
    {
        // Sort and concatenate the request body (if it's not null)
        $sortedRequestBody = $this->prepareHeaders($requestBody);

        // Create the signature base string (X-Login + X-Date + RequestBody)
        $signatureBase = $this->xLogin . $xDate;

        // Generate HMAC-SHA256 hash
        return hash_hmac('sha256', $signatureBase, $this->secretKey);
    }

    /**
     * Sort and concatenate request body
     * 
     * @param array|null $requestBody
     * @return string
     */
    public static function prepareResult($requestBody = [])
    {
        if (count($requestBody) > 0) {
            if (empty($requestBody)) {
                return '';
            }

            // Sort the request body by keys
            ksort($requestBody);

            // Concatenate all key-value pairs without separators
            return implode('', array_map(function ($key, $value) {
                return $key . $value;
            }, array_keys($requestBody), $requestBody));
        }

        return '';
    }

    public function prepareHeaders(array $payload = [])
    {
        $credentials = self::getInstance()->credentials;
        $X_Login = $credentials['X_Login'];
        $X_Trans_Key = $credentials['X_Trans_Key'];
        $secret = $credentials['secret'];

        $X_Date = (new DateTime())->format('Y-m-d\TH:i:s.v\Z');
        $result = self::prepareResult($payload);

        $signature = hash_hmac('sha256', $X_Login . $X_Date . $result, $secret);

        return [
            'headers' => [
                'X-Date' => $X_Date,
                'X-Login' => $X_Login,
                'X-Trans-Key' => $X_Trans_Key,
                'Content-Type' => 'application/json',
                'Authorization' => "V2-HMAC-SHA256, Signature: {$signature}",
            ],
        ];
    }

    /**
     * Make a signed request to Vita Business API
     *
     * @param string $endpoint
     * @param array|null $body
     * @param string $method (default: post)
     * @return \array
     */
    public function makeSignedRequest($endpoint, array $body = [], $method = "post")
    {
        $credentials = self::getInstance()->credentials;
        $baseUrl = $credentials['BASE_URL'];
        
        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $endpoint = "{$baseUrl}$endpoint";
        }

        $headers = $this->prepareHeaders($body);
        $xheaders = [
            "X-Date: " . $headers['headers']["X-Date"],
            "X-Login: " . $headers['headers']["X-Login"],
            "X-Trans-Key: " . $headers['headers']["X-Trans-Key"],
            "Content-Type: " . $headers['headers']["Content-Type"],
            "Authorization: " . $headers['headers']["Authorization"],
        ];

        // Prepare cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);  // Enable verbose output for debugging

        // Execute request
        $response = curl_exec($ch);
        if ($response === false) {
            $result = [
                "error" => curl_error($ch),
            ];
        } else {
            $result = (array) $response;
        }

        Log::info("makeSignedRequest response ", ['curl' => $response, 'curl_result' => $result]);
        curl_close($ch);

        return $result;
    }
}

