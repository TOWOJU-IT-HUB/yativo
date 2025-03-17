<?php

namespace App\Http\Controllers;

use Modules\Customer\app\Models\Customer;

class CryptoYativoController 
{
    private $baseUrl = "https://crypto-api.yativo.com/api/";
    private function getToken()
    {
        $payload = [
            "email" => env("YATIVO_CRYPTO_API_EMAIL"),
            "api_key" => env("YATIVO_CRYPTO_API_KEY")
        ];
        $curl = Http::post($this->baseUrl."authentication/generate-key", $payload)->jso();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }

        return $curl['result']['message'];
    }

    public function addCustomer(Request $request)
    {
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }
        $payload = [
            "username" => "customer1",
            "email" => "customer1@example.com"
        ];

        $curl = Http::post($this->baseUrl."customers/create-customer", $payload)->jso();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }

        return $curl['result']['message'];
    }

    public function generateCustomerWallet(Request $request)
    {
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }
        $payload = [
            "asset_id" => "customer1",
            "customer_id" => "customer_id",
            "chain" => "xoxo"
        ];

        $curl = Http::post($this->baseUrl."assets/add-customer-asset", $payload)->jso();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }

        return $curl['result']['message'];
    }

    public function sendCrypto(Request $request)
    {
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }
        $payload = [
            'account' => 'account_id_here',
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

        $curl = Http::post($this->baseUrl."assets/add-customer-asset", $payload)->jso();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }

        return $curl['result']['message'];
    }
}