<?php

namespace app\Services;

use Illuminate\Support\Facades\Http;

class TransakServices
{
    public $apiKey, $baseUrl;

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?? getenv('TRANSAK_API_KEY');
        $this->baseUrl = getenv('TRANSAK_BASE_URL');
    }

    public function get_order($orderId)
    {
        $client = new \GuzzleHttp\Client();

        $url = $this->baseUrl . "/order/{$orderId}";
        $response = $client->request('GET', $url, [
            'headers' => [
                'accept' => 'application/json',
                'access-token' => 'YOUR_ACCESS_TOKEN',
            ],
        ]);

        return $response->getBody();
    }

    public function create_url($request)
    {
        $url = $this->baseUrl . "?apiKey={$this->apiKey}&" . http_build_query($request);
        return $url;
    }

    public function validateWallet($walletAddress, $coinTicker, $coinNetwork)
    {
        try {
            $url = $this->baseUrl . "/currencies/verify-wallet-address?walletAddress=$walletAddress&cryptoCurrency={$coinTicker}&network={$coinNetwork}";
            $request = Http::get($url)->json();
            return $request;
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }
}
