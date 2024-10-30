<?php

namespace App\Services;

use GuzzleHttp\Client;

class BrlaDigitalService
{
    private $client, $baseUrl, $brlaEmail, $brlaPassword;

    public function __construct()
    {
        $port = env('IS_BRLA_TEST') ? 4567 : 5567;
        $this->brlaEmail = env('BRLA_EMAIL');
        $this->brlaPassword = env('BRLA_PASSWORD');
        $this->baseUrl = "https://api.brla.digital:{$port}/v1/business";
        $this->client = new Client();
    }

    public function generatePayInBRCode(array $payload = [])
    {
        $result = $this->get('/pay-in/br-code', $payload);
        return $result;
    }

    public function getPayInHistory(array $payload = [])
    {
        return $this->get('/pay-in/pix/history', $payload);
    }

    public function getPixKeyInfo(array $payload = [])
    {
        return $this->get('/pay-out/pix-info', $payload);
    }

    public function getPayOutReceipt($id, array $payload = [])
    {
        return $this->get("/pay-out/receipt/{$id}", $payload);
    }

    public function getPayOutHistory(array $payload = [])
    {
        return $this->get('/pay-out/history', $payload);
    }

    public function closePixToUSDDeal($data)
    {
        return $this->post('/pay-in/pix-to-usd', $data);
    }

    public function createPayOutOrder($data)
    {
        return $this->post('/pay-out', $data);
    }

    public function createUsdToPixOrder($data)
    {
        return $this->post('/pay-out/usd-to-pix', $data);
    }

    public function convertCurrencies($data)
    {
        return $this->post('/swap', $data);
    }

    private function get($endpoint, array $payload = [])
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($payload)) {
            $url .= '?' . http_build_query($payload);
        }
        
        $response = $this->client->request('GET', $url, [
            'headers' => [
                'accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->login(),
            ],
        ]);
        return json_decode($response->getBody(), true);
    }

    private function post($endpoint, $data)
    {
        $response = $this->client->request('POST', $this->baseUrl . $endpoint, [
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->login(),
            ],
            'body' => json_encode($data),
        ]);
        return json_decode($response->getBody(), true);
    }

    public function login()
    {
        $response = $this->client->request('POST', $this->baseUrl . '/login', [
            'headers' => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ],
            'body' => json_encode([
                'email' => $this->brlaEmail,
                'password' => $this->brlaPassword,
            ]),
        ]);
        $login = json_decode($response->getBody(), true);
        return $login['accessToken'];
    }
}
