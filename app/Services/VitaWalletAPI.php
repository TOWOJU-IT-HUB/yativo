<?php

namespace App\Services;

use DateTime;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VitaWalletAPI
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

    private function generateSignature(array|string $requestBody, $xDate)
    {
        $sortedRequestBody = $this->prepareResult($requestBody);
        $signatureBase = $this->xLogin . $xDate;
        return hash_hmac('sha256', $signatureBase, $this->secretKey);
    }

    public static function prepareResult($requestBody = [])
    {
        if (!empty($requestBody)) {
            ksort($requestBody);
            return implode('', array_map(function ($key, $value) {
                return $key . $value;
            }, array_keys($requestBody), $requestBody));
        }
        return '';
    }

    public function prepareHeaders(array $payload = [])
    {
        $X_Login = $this->credentials['X_Login'];
        $X_Trans_Key = $this->credentials['X_Trans_Key'];
        $secret = $this->credentials['secret'];
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

    public function makeSignedRequest($endpoint, array $body = [], $method = "post")
    {
        $baseUrl = $this->credentials['BASE_URL'];

        if (!filter_var($endpoint, FILTER_VALIDATE_URL)) {
            $endpoint = "{$baseUrl}{$endpoint}";
        }

        $headers = $this->prepareHeaders($body);
        $xheaders = [
            "X-Date: " . $headers['headers']["X-Date"],
            "X-Login: " . $headers['headers']["X-Login"],
            "X-Trans-Key: " . $headers['headers']["X-Trans-Key"],
            "Content-Type: " . $headers['headers']["Content-Type"],
            "Authorization: " . $headers['headers']["Authorization"],
        ];

        // Log payload and headers
        Log::info("Request Payload: " . json_encode($body));
        Log::info("Request Headers: " . json_encode($xheaders));

        // Make the cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $result = ["error" => curl_error($ch)];
            Log::error("cURL Error: " . curl_error($ch));
        } else {
            $result = json_decode($response, true);
            Log::info("Response: " . $response);
        }

        curl_close($ch);

        return $result;
    }
}
