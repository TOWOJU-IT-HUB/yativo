<?php

namespace App\Http\Controllers;

use Modules\Customer\app\Models\Customer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Log, Http;

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
        $curl = Http::post($this->baseUrl."authentication/generate-key", $payload)->json();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }
        // var_dump($curl); exit;
        return ['error' => $curl['message'] ?? $curl['result']];
    }

    public function addCustomer()
    {
        $request = request();
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }

        if(!empty($customer->yativo_customer_id)) {
            return $customer->yativo_customer_id;
        }
        
        $payload = [
            "username" => explode("@", $customer->customer_email)[0].rand(0, 9999),
            "email" => $customer->customer_email
        ];

        $curl = Http::withToken($this->getToken())->post($this->baseUrl."customers/create-customer", $payload)->json();
        // Log::debug("customer added", ["result" => $curl]);
        if($curl['status'] == true) {
            $customer->yativo_customer_id = $curl['data']['_id'];
            $customer->save();
            Log::debug("customer added", ["result_2" => true]);
            return $curl['data']['_id'];
        }

        return ['error' => $curl['message'] ?? $curl['result']['message']];
    }

    public function generateCustomerWallet()
    {
        $request = request();
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }

        $yativo_customer_id = $customer->yativo_customer_id ?? $this->addCustomer();
        // var
        if(is_array($yativo_customer_id) && isset($yativo_customer_id['error'])) {
            return get_error_response("Customer not enroll for service", ['error' => "Csutomer not enroll for service"]);
        }

        $payload = [
            "asset_id" => $this->getAssetId($request->currency), // USDC_SOL
            "customer_id" => $yativo_customer_id,
            "chain" => "solana"
        ];

        $curl = Http::withToken($this->getToken())->post($this->baseUrl."assets/add-customer-asset", $payload)->json();

        if($curl['status'] == true) {
            return $curl['data'];
        }

        return ['error' => $curl['message'] ?? $curl['result']['message']];
    }

    public function sendCrypto(Request $request)
    {
        $customer = Customer::where('customer_id', $request->customer_id)->first();
        if(!$customer) {
            return get_error_response("Customer not found", ['error' => 'Customer not found']);
        }
        $payload = [
            'account' => 'account_id_here',
            'assets' => $this->getAssetId($request->currency),
            'receiving_address' => $request->receiving_address,
            'amount' => $request->amount,
            'category' => 'Transfer',
            'description' => 'Payment for services',
            'type' => 'crypto',
            'chain' => 'ETH',
            'priority' => 'high',
            'approve_gas_funding' => true,
        ];

        $curl = Http::withToken($this->getToken())->post($this->baseUrl."assets/add-customer-asset", $payload)->json();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }

        return $curl['result']['message'];
    }

    
    public function getAssetId(string $ticker): ?string
    {
        try {
            $response = Http::withToken($this->getToken())
                ->acceptJson()
                ->get($this->baseUrl . 'assets/get-all-assets');
            
            $data = $response->json();
    
            if (!isset($data['success'], $data['data']) || !$data['success']) {
                return get_error_response(['error' => $data['message'] ?? 'Unknown error']);
            }
    
            foreach ($data['data'] as $asset) {
                if (strcasecmp($asset['asset_short_name'] ?? '', $ticker) === 0) {
                    return $asset['_id'] ?? null;
                }
            }
    
            return null;
        } catch (Exception $e) {
            // Handle exception appropriately (log, rethrow, etc.)
            logger()->error('Asset ID retrieval failed: ' . $e->getMessage());
            return null;
        }
    }
}