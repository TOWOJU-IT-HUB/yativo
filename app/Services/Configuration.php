<?php

namespace App\Services;
use DateTime;

class Configuration
{
    private static $instance;
    private $credentials = [
        'X_Login' => null,
        'X_Trans_Key' => null,
        'secret' => null,
        'env' => null,
        'isDevelopment' => false,
        'BASE_URL' => 'https://api.vitawallet.io/api/businesses'
    ];

    public static $STAGE = 'live';

    private function __construct()
    {
        $this->credentials['X_Login'] = getenv('VITA_X_LOGIN');
        $this->credentials['X_Trans_Key'] = getenv('VITA_X_TRANS_KEY');
        $this->credentials['secret'] = getenv('VITA_SECRET');
        $this->credentials['env'] = getenv('VITA_MODE');
        $this->credentials['BASE_URL'] = env('VITA_BASE_URL');
    }

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function isCredentials()
    {
        $credentials = self::getInstance()->credentials;
        return (
            !empty($credentials['X_Login']) &&
            !empty($credentials['X_Trans_Key']) &&
            !empty($credentials['secret']) &&
            !empty($credentials['env'])
        );
    }

    public static function isDevelopment()
    {
        return self::getInstance()->credentials['isDevelopment'] ?? false;
    }

    public function setCredentials($credentials)
    {
        $this->credentials = array_merge($this->credentials, $credentials);
    }

    public static function getUrl()
    {
        return self::getInstance()->credentials['BASE_URL'] ?? '';
    }

    public static function payin()
    {
        return self::getUrl() . "/payment_orders";
    }

    public static function getWalletsUrl($resource = '')
    {
        return self::getUrl() . "/wallets/{$resource}";
    }

    public static function getTransactionsUrl($resource = '')
    {
        return self::getUrl() . "/transactions/{$resource}";
    }

    public static function getTransactionsUrI($resource = '')
    {
        echo 301;
        return self::getUrl() . "/transactions?order={$resource}";
    }

    public static function getPaymentOrderUrl($id = '')
    {
        return self::getUrl() . "/payment_orders/{$id}";
    }

    public static function createTransaction()
    {
        return self::getUrl() . "/transactions/";
    }

    public static function getPricesUrl()
    {
        return self::getUrl() . "/prices";
    }

    public static function getBanksUrl()
    {
        return self::getUrl() . "/banks";
    }

    public static function getVitaUsersUrl()
    {
        return self::getUrl() . "/vita_users";
    }

    public static function getWithdrawalRulesUrl()
    {
        return self::getUrl() . "/withdrawal_rules";
    }

    public static function prepareResult($requestBody = []) {
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

    public static function prepareHeaders(array $payload = []) {
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
}
