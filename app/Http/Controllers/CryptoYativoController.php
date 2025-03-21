<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Schema\Blueprint;
use Modules\Customer\app\Models\Customer;

class CryptoYativoController extends Controller
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env("YATIVO_CRYPTO_API_URL");
    }

    private function getToken()
    {
        $payload = [
            "email" => env("YATIVO_CRYPTO_API_EMAIL"),
            "api_key" => env("YATIVO_CRYPTO_API_KEY")
        ];
        $curl = Http::post($this->baseUrl."authentication/generate-key", $payload)->json();

        if (isset($curl['success']) && $curl['success'] === true) {
            return $curl['result']['token'];
        }
        
        Log::error('Failed to get token', ['response' => $curl]);
        return null;
    }

    public function addCustomer()
    {
        $request = request();
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if (!$customer) {
            return ['error' => 'Customer not found'];
        }

        if (!empty($customer->yativo_customer_id)) {
            return $customer->yativo_customer_id;
        }

        $token = $this->getToken();
        if (!$token) {
            return ['error' => 'Failed to authenticate with Yativo API'];
        }

        $payload = [
            "username" => explode("@", $customer->customer_email)[0] . rand(0, 9999),
            "email" => $customer->customer_email
        ];

        $curl = Http::withToken($token)->post($this->baseUrl . "customers/create-customer", $payload)->json();

        if (isset($curl['status']) && $curl['status'] === true) {
            $customer->yativo_customer_id = $curl['data']['_id'];
            $customer->save();
            Log::debug("Customer added successfully", ["customer_id" => $curl['data']['_id']]);
            return $curl['data']['_id'];
        }

        Log::error("Failed to create customer", ["error" => $curl]);
        return ['error' => $curl['message'] ?? 'Unknown error'];
    }

    public function generateCustomerWallet()
    {
        $request = request();
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if (!$customer || empty($customer->yativo_customer_id)) {
            return ['error' => 'Customer not found or not registered with Yativo'];
        }

        $token = $this->getToken();
        if (!$token) {
            return ['error' => 'Failed to authenticate with Yativo API'];
        }

        $payload = [
            "customer_id" => $customer->yativo_customer_id,
        ];

        $response = Http::withToken($token)->post($this->baseUrl . "wallets/generate-wallet", $payload)->json();

        if (isset($response['status']) && $response['status'] === true) {
            return $response['data'];
        }

        Log::error("Failed to generate wallet", ["error" => $response]);
        return ['error' => $response['message'] ?? 'Unknown error'];
    }

    public function getAssetId(string $ticker)
    {
        $token = $this->getToken();
        if (!$token) {
            return null;
        }

        $response = Http::withToken($token)->get($this->baseUrl . 'assets/get-all-assets')->json();

        if (!isset($response['status']) || !$response['status'] || !isset($response['data'])) {
            return null;
        }

        foreach ($response['data'] as $asset) {
            if (strtoupper($asset['asset_short_name']) === strtoupper($ticker)) {
                return $asset['_id'];
            }
        }
        
        return null;
    }

    public function sendCrypto()
    {
        $request = request();
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if (!$customer || empty($customer->yativo_customer_id)) {
            return ['error' => 'Customer not found or not registered with Yativo'];
        }

        $token = $this->getToken();
        if (!$token) {
            return ['error' => 'Failed to authenticate with Yativo API'];
        }

        $assetId = $this->getAssetId($request->asset);
        if (!$assetId) {
            return ['error' => 'Invalid asset provided'];
        }

        $payload = [
            "customer_id" => $customer->yativo_customer_id,
            "asset_id" => $assetId,
            "amount" => $request->amount,
            "recipient_address" => $request->recipient_address,
        ];

        $response = Http::withToken($token)->post($this->baseUrl . "transactions/send-crypto", $payload)->json();

        if (isset($response['status']) && $response['status'] === true) {
            return $response['data'];
        }

        Log::error("Failed to send crypto", ["error" => $response]);
        return ['error' => $response['message'] ?? 'Unknown error'];
    }
}