<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BrlaApiService
{
    protected $baseUrl, $port, $loginToken;

    public function __construct()
    {
        $this->port = env('IS_BRLA_TEST') ? 4567 : 5567;
        $this->baseUrl = "https://api.brla.digital:{$this->port}/v1/business";
        $this->login = self::login(env('BRLA_EMAIL'), env('BRLA_PASSWORD'));
    }

    public function login($email, $password)
    {
        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/login", [
            'email' => $email,
            'password' => $password,
        ]);

        return $response->json();
    }

    public function generateBrCode($amount, $referenceLabel)
    {
        $response = Http::withToken($this->loginToken)->withHeaders(['accept' => 'application/json'])
            ->get("{$this->baseUrl}/pay-in/br-code", [
                'amount' => $amount,
                'referenceLabel' => $referenceLabel,
            ]);

        return $response->json();
    }

    public function closePixToUsd($token, $markupAddress, $receiverAddress, $externalId)
    {
        $response = Http::withToken($this->loginToken)->withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/pay-in/pix-to-usd", [
            'token' => $token,
            'markupAddress' => $markupAddress,
            'receiverAddress' => $receiverAddress,
            'externalId' => $externalId,
        ]);

        return $response->json();
    }

    public function closePixToToken($amount, $token, $markup, $receiverAddress, $markupAddress, $referenceLabel, $externalId)
    {
        $response = Http::withToken($this->loginToken)->withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/pay-in/pix-to-token", [
            'amount' => $amount,
            'token' => $token,
            'markup' => $markup,
            'receiverAddress' => $receiverAddress,
            'markupAddress' => $markupAddress,
            'referenceLabel' => $referenceLabel,
            'externalId' => $externalId,
        ]);

        return $response->json();
    }

    public function createPayOutOrder($data)
    {
        $response = Http::withToken($this->loginToken)->withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/pay-out", $data);

        return $response->json();
    }

    public function createUsdToPixOrder($data)
    {
        $response = Http::withToken($this->loginToken)->withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/pay-out/usd-to-pix", $data);

        return $response->json();
    }

    public function convertCurrencies($data)
    {
        $response = Http::withToken($this->loginToken)->withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post("{$this->baseUrl}/swap", $data);

        return $response->json();
    }
}
