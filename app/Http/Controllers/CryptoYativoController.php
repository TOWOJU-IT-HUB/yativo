<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Modules\Customer\App\Models\Customer;

class CryptoYativoController 
{
    private $baseUrl = "https://crypto-api.yativo.com/api/";
    private $yativoEmail;
    private $yativoApiKey;

    public function __construct()
    {
        $this->yativoEmail = env("YATIVO_CRYPTO_API_EMAIL");
        $this->yativoApiKey = env("YATIVO_CRYPTO_API_KEY");
    }

    private function getToken()
    {
        try {
            $response = Http::post($this->baseUrl."authentication/generate-key", [
                "email" => $this->yativoEmail,
                "api_key" => $this->yativoApiKey
            ]);

            if (!$response->successful()) {
                Log::error("Failed to get Yativo token: " . $response->body());
                throw new \Exception("Failed to get token from Yativo API");
            }

            $data = $response->json();
            
            if (!isset($data['result']['token'])) {
                throw new \Exception("Token not found in response: " . json_encode($data));
            }

            return $data['result']['token'];
        } catch (\Exception $e) {
            Log::error("Yativo API Token Error: " . $e->getMessage());
            throw $e;
        }
    }

    public function addCustomer(Request $request)
    {
        try {
            $customer = Customer::where('customer_id', $request->customer_id)->first();
            
            if (!$customer) {
                return (["error" => "Customer not found"], 404);
            }
            
            $usernameParts = explode("@", $customer->customer_email);
            $username = $usernameParts[0] . rand(0, 9999);

            $payload = [
                "username" => $username,
                "email" => $customer->customer_email
            ];

            $token = $this->getToken();

            $response = Http::withToken($token)->post($this->baseUrl."customers/create-customer", $payload);

            if (!$response->successful()) {
                Log::error("Failed to create Yativo customer: " . $response->body());
                return (["error" => "Failed to create customer on Yativo"], 500);
            }

            $data = $response->json();
            
            if (!isset($data['data']['_id'])) {
                return (["error" => "Invalid response from Yativo API"], 500);
            }

            $customer->yativo_customer_id = $data['data']['_id'];
            $customer->save();

            return ($data['data']['_id']);
        } catch (\Exception $e) {
            Log::error("Yativo Customer Creation Error: " . $e->getMessage());
            return (["error" => "Internal server error"], 500);
        }
    }

    public function generateCustomerWallet(Request $request)
    {
        try {
            $customer = Customer::where('customer_id', $request->customer_id)->first();
            
            if (!$customer) {
                return (["error" => "Customer not found"], 404);
            }

            if (!$customer->yativo_customer_id) {
                $customerResponse = $this->addCustomer($request);
                
                if ($customerResponse['error']) {
                    return (["error" => "Customer not enrolled for service"], 400);
                }
            }

            $payload = [
                "asset_id" => "67d819bfd5925438d7846aa1", // USDC_SOL
                "customer_id" => $customer->yativo_customer_id ?? $customerResponse->original,
                "chain" => "solana"
            ];

            $token = $this->getToken();

            $response = Http::withToken($token)->post($this->baseUrl."assets/add-customer-asset", $payload);

            if (!$response->successful()) {
                Log::error("Failed to generate customer wallet: " . $response->body());
                return (["error" => "Failed to generate wallet"], 500);
            }

            return ($response->json('result'));
        } catch (\Exception $e) {
            Log::error("Yativo Wallet Generation Error: " . $e->getMessage());
            return (["error" => "Internal server error"], 500);
        }
    }

    public function sendCrypto(Request $request)
    {
        try {
            $customer = Customer::where('customer_id', $request->customer_id)->first();
            
            if (!$customer) {
                return (["error" => "Customer not found"], 404);
            }

            $payload = [
                'account' => env('YATIVO_CRYPTO_ACCOUNT_ID'), // Use environment variable
                'assets' => $request->asset_id,
                'receiving_address' => $request->receiving_address,
                'amount' => $request->amount,
                'category' => 'Transfer',
                'description' => 'Payment for services',
                'type' => 'crypto',
                'chain' => 'ETH',
                'priority' => 'high',
                'approve_gas_funding' => true,
            ];

            $token = $this->getToken();

            $response = Http::withToken($token)->post($this->baseUrl."transfers/create-transfer", $payload);

            if (!$response->successful()) {
                Log::error("Failed to send crypto: " . $response->body());
                return (["error" => "Failed to send crypto"], 500);
            }

            return ($response->json('result.token'));
        } catch (\Exception $e) {
            Log::error("Yativo Send Crypto Error: " . $e->getMessage());
            return (["error" => "Internal server error"], 500);
        }
    }
}