<?php

namespace App\Http\Controllers;

use Modules\Customer\app\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Log;

class CryptoYativoController 
{
    public function __construct()
    {
        if (!Schema::hasColumn('customers', 'yativo_customer_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('yativo_customer_id')->nullable();
            });
        }
    }

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

        return $curl['message'] ?? $curl['result']['message'];
    }

    public function addCustomer(Request $request)
    {
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }
        $payload = [
            "username" => explode("@", $customer->customer_email),
            "email" => $customer->customer_email
        ];

        $curl = Http::post($this->baseUrl."customers/create-customer", $payload)->json();

        if($curl['status'] == true) {
            $customer->yativo_customer_id = $curl['data']['_id'];
            $customer->save();
            return $curl['data']['_id'];
        }

        return ['error' => $curl['message'] ?? $curl['result']['message']];
    }

    public function generateCustomerWallet(Request $request)
    {
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }

        $yativo_customer_id = $customer->yativo_customer_id ?? $this->addCustomer($request);

        if(is_array($yativo_customer_id) && isset($yativo_customer_id)) {
            return get_error_response("Customer not enroll for service", ['error' => "Csutomer not enroll for service"]);
        }

        $payload = [
            "asset_id" => "67d819bfd5925438d7846aa1", // USDC_SOL
            "customer_id" => $yativo_customer_id,
            "chain" => "SOL"
        ];

        $curl = Http::post($this->baseUrl."assets/add-customer-asset", $payload)->json();

        if($curl['success'] == true) {
            return $curl['result'];
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

        $curl = Http::post($this->baseUrl."assets/add-customer-asset", $payload)->json();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }

        return $curl['result']['message'];
    }
}