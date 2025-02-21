<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Modules\Customer\app\Models\Customer;

class BrlaApiService
{
    protected $baseUrl, $port, $loginToken, $customer;

    public function __construct()
    {
        $this->port = env('IS_BRLA_TEST') ? 4567 : 5567;
        $this->baseUrl = "https://api.brla.digital:{$this->port}/v1/business";
        $this->login = self::login(env('BRLA_EMAIL'), env('BRLA_PASSWORD'));
        $this->customer = $this->getCustomerInfo();
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
                'subaccountId' => $this->customer->brla_subaccount_id
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
            'subaccountId' => $this->customer->brla_subaccount_id
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
            'subaccountId' => $this->customer->brla_subaccount_id
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
    
    private function getCustomerInfo()
    {
        if (request()->has('customer_id')) {
            $customer = Customer::where('customer_id', request()->customer_id)->first();
            $name = explode(' ', $customer->customer_name);
            $customer->first_name = $name[0];
            $customer->last_name = $name[1] ?? $name[0];
            return $customer;
        } else {
            $user = request()->user();
            $name = explode(' ', $user->name);
            $user->first_name = $name[0];
            $user->last_name = $name[1] ?? $name[0];
            return $user;
        }
    }
}
