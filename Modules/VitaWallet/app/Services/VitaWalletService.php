<?php

namespace Modules\VitaWallet\app\Services;

use App\Services\VitaBusinessAPI;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VitaWalletService
{
    private $baseUrl;
    private $secret;
    private $xLogin;
    private $xTransKey;

    public function __construct()
    {
        $this->baseUrl = env('VITA_BASE_URL');
        $this->secret = env('VITA_SECRET');
        $this->xLogin = env('VITA_X_LOGIN');
        $this->xTransKey = env('VITA_X_TRANS_KEY');
    }
    
    // sample:  
    public function sendRequest($method, $endpoint, array $requestBody = [])
    {
        $apiService = new VitaBusinessAPI();

        $endpoint = getenv('VITA_BASE_URL').$endpoint;
        $response = $apiService->makeSignedRequest($endpoint, $requestBody, $method);
        return $response;
    }

    private function getSignature($secretKey, $payload)
    {
        return hash_hmac('sha256', $payload, $secretKey);
    }

    private function flattenArray(array $array)
    {
        $result = '';
        array_walk_recursive($array, function ($item) use (&$result) {
            $result .= $item;
        });
        return $result;
    }

    public static function currency($iso2, $currency)
    {
        if ($currency == 'USD') {
            switch ($iso2) {
                case 'pe':
                    return 'peusd'; // Peru USD
                case 'hk':
                    return 'hkusd'; // Hong Kong USD
                case 'eu':
                    return 'euusd'; // Europe USD
                case 'ca':
                    return 'causd'; // Canada USD
                case 'cn':
                    return 'cnusd'; // China USD
                case 'gb':
                    return 'gbusd'; // GBP USD
                default:
                    break;
            }
        }

        return $iso2;
    }
}
