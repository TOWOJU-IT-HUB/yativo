<?php

namespace App\Http\Controllers;

use App\Models\Business\VirtualAccount;
use App\Models\Country;
use Modules\Webhook\app\Models\Webhook;
use App\Models\Withdraw;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Customer\app\Models\Customer;
use Spatie\WebhookServer\WebhookCall;
use App\Models\WebhookLog;
use App\Models\User;
use App\Models\Deposit;
use App\Models\VirtualAccountDeposit;
use App\Models\TransactionRecord;
use Modules\SendMoney\app\Http\Controllers\SendMoneyController;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Validator;
use Carbon\Carbon;

class BridgeController extends Controller
{
    public $customer, $customerId;
    private $yativoBaseUrl = "https://crypto-api.yativo.com/api/";

    public function __construct()
    {
        $this->customer = DB::table('customers')->where('customer_id', request()->customer_id)->where('user_id', auth()->id())->first();
        // Log::info("Customer Info: ", (array) $this->customer); 
        $this->customerId = $this->customer->customer_id ?? null;
    }

    private function getYativoToken()
    {
        $payload = [
            "email" => env("YATIVO_CRYPTO_API_EMAIL"),
            "api_key" => env("YATIVO_CRYPTO_API_KEY")
        ];
        $curl = Http::post($this->yativoBaseUrl."authentication/generate-key", $payload)->json();

        if($curl['success'] == true) {
            return $curl['result']['token'];
        }
        // var_dump($curl); exit;
        return ['error' => $curl['message'] ?? $curl['result']];
    }


    /**
     * GEt KYC link to add a customer
     * @return 
     */
    public function addCustomerV1(array|object $payload = [])
    {
        $customer = Customer::where('customer_id', request()->input('customer_id'))->first();

        if(request()->has('debug')) {
            dd($payload); exit;
        }

        $bridgeData = $this->sendRequest("/v0/customers", 'POST', array_filter($payload));

        if (isset($bridgeData['id']) && $customer) {
            // Update customer with bridge customer ID
            $customer->update(["bridge_customer_id" => $bridgeData['id']]);
        }

        if ($this->containsTechnicalDifficulties($bridgeData)) {
            // since the KYC failed we don't have the customer ID. we have to loop through and find the customer's ID via email

            $endpoint = "v0/customers?limit=100";
            $data = $this->sendRequest($endpoint);
    
            if(is_array($data) && isset($data['count'])) {
                // customer bridge ID is empty so check if it exists
                foreach ($data['data'] as $k => $v) {
                    if($payload['email'] == $v['email']) {
                        $update = $customer->update([
                            'bridge_customer_id' => $v['id']
                        ]);    

                        $endpoint = 'v0/customers/'.$v['id'].'/kyc_link?endorsement=sepa';
                        $bridgeData = $this->sendRequest($endpoint, 'POST', $payload);
                        return $bridgeData;
                    }
                }
            }  
        }

        if(isset($bridgeData['id']) && isset($bridgeData['status'])) {
            $bridgeData = [
                "first_name" => $bridgeData['first_name'],
                "last_name" => $bridgeData['last_name'],
                "status" => $bridgeData['status'],
                "rejection_reasons" => $bridgeData['rejection_reasons'],
                "requirements_due" => $bridgeData['requirements_due'],
                "future_requirements_due" => $bridgeData['future_requirements_due'],
            ];
        }

        return $bridgeData;
    }
    
    /**
     * Check if any API response field contains "technical difficulties".
     */
    private function containsTechnicalDifficulties(array $data): bool
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && str_contains($value, 'technical difficulties')) {
                return true;
            } elseif (is_array($value)) {
                if ($this->containsTechnicalDifficulties($value)) {
                    return true;
                }
            }
        }
        return false;
    }

    // if KYC returns a technical error auto initiat customer update process
    public function autoUpdateCustomer($payload)
    {
        $customer = Customer::where('customer_id', request()->customer_id)->first();
        if(!$customer) {
            echo json_encode([
                "status" => false,
                "status_code" => 404,
                "message" => "Customer not found!",
                "error" => [
                    "error" => "Csutomer not found"
                ]
            ]); exit;
        }

        $endpoint = "v0/customers?limit=100";
        $data = (array)$this->sendRequest($endpoint);

        if(is_array($data) && isset($data['count'])) {
            // customer bridge ID is empty so check if it exists
            foreach ($data['data'] as $k => $v) {
                if($customer->customer_kyc_email == $v['email']) {
                    $update = $customer->update([
                        'bridge_customer_id' => $v['id']
                    ]);  
                    
                    if($update) {
                        $endpoint = "v0/customers/".$v['id'];
                        // update customer on bridge
                        $data = $this->sendRequest($endpoint, "PUT", $payload);

                        if(isset($data['status'])) {
                            return get_success_response([
                                "first_name" => $data['first_name'],
                                "last_name" => $data['last_name'],
                                "status" => $data['status'],
                                "rejection_reasons" => $data['rejection_reasons'],
                                "requirements_due" => $data['requirements_due'],
                                "future_requirements_due" => $data['future_requirements_due'],
                                // "raw" => $data
                            ]);
                        }
                    }
                } 
            }
            return get_error_response(['error' => 'Unmatched customer data provided']);
        }
        return get_error_response(['error' => "Please contact support for manual verification"]);
    }

    public function selfUpdateCustomer(Request $request) 
    {
        // Send request to external API
        $curl = Http::post("https://monorail.onrender.com/dashboard/generate_signed_agreement_id", [
            'customer_id' => null,
            'email' => null,
            'token' => null,
            'type' => 'tos',
            'version' => 'v5',
        ]);
    
        // Check if the request failed
        if ($curl->failed()) {
            return response()->json(['error' => 'Failed to generate signed agreement ID'], 500);
        }
    
        // Get response safely
        $response = $curl->json();
        $signedAgreementId = $response['signed_agreement_id'] ?? null;
    
        // Convert request to array and merge new data
        $payload = array_merge($request->all(), [
            'residential_address' => $request->address ?? null,
            "signed_agreement_id" => $signedAgreementId
        ]);
    
        // Call autoUpdateCustomer with the array
        return $this->autoUpdateCustomer($payload);
    }   

    public function getCustomerRegistrationCountries(Request $request)
    {
        try {
            $curl = $this->sendRequest("/v0/lists/countries", 'GET', []);
            return get_success_response($curl);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()], 500);
        }
    }

    public function updateCustomer(array $customer = [])
    {
        $endpoint = 'v0/kyc_links';
        $payload = [
            'full_name' => $customer['customer_name'],
            'email' => $customer['customer_kyc_email'],
            'type' => $customer['customer_type'] ?? 'individual',
            'endorsements' => ['sepa'],
            'redirect_uri' => request()->redirect_url ?? $customer['redirect_uri'] ?? env('WEB_URL', request()->redirect_url),
        ];

        $kycResponse = $this->sendRequest($endpoint, 'POST', $payload);

        if (empty($kycResponse) || isset($kycResponse['error'])) {
            return ['error' => $kycResponse['error']];
        }

        if (isset($kycResponse['existing_kyc_link'])) {
            $kycResponse = $kycResponse['existing_kyc_link'];
        }

        $customer = Customer::updateOrCreate(
            [
                'customer_kyc_email' => $customer['customer_kyc_email'],
                'user_id' => active_user(),
            ],
            [
                'customer_name' => $customer['customer_name'],
                'customer_phone' => $customer['customer_phone'],
                'bridge_customer_id' => $kycResponse['customer_id'],
                'customer_kyc_status' => $kycResponse['kyc_status'],
                'customer_kyc_link' => $kycResponse['kyc_link'],
                'customer_kyc_link_id' => $kycResponse['id']
            ]
        );

        if (!$customer) {
            return ['error' => 'Failed to save customer'];
        }
        
        return $customer;
    }

    public function getKycStatus()
    {

        $request = request();
        $endpoint = "v0/kyc_links/{$this->customer->customer_kyc_link_id}";

        $data = $this->sendRequest($endpoint);

        return $data;
    }

    public function getCustomer($customerId)
    {
        $customer = Customer::where('customer_id', $customerId)->first();
        if (!$customer) {
            return get_error_response(['error' => 'Customer ID is invalid']);
        }
    
        // Fetch customer data from API using bridge_customer_id if available
        if (!empty($customer->bridge_customer_id)) {
            return $this->fetchAndReturnCustomerData($customer->bridge_customer_id, $customer);
        }
    
        // Fetch potential matches from API
        $endpoint = "v0/customers/{$customer->bridge_customer_id}?limit=100";
        $data = $this->sendRequest($endpoint);
    
        // dd($data); exit;
        // Validate API response
        if (!is_array($data) || empty($data['data'])) {
            return get_error_response(['error' => 'Error on our end, Please contact support']);
        }
    
        // Find a match in API response
        foreach ($data['data'] as $entry) {
            if ($customer->customer_kyc_email === $entry['email']) {
                $customer->update(['bridge_customer_id' => $entry['id']]);
    
                if($customer->kyc_verified_date == null) {
                    $customer->update([
                        'kyc_verified_date' => now()
                    ]);
                }

                // Update customer status if active
                if ($entry['status'] === 'active') {
                    $customer->update([
                        'customer_status' => 'active',
                        'customer_kyc_status' => 'approved',
                    ]);
                }
    
                return $this->fetchAndReturnCustomerData($entry['id'], $customer);
            }
        }
    
        return get_error_response(['error' => 'Customer not found in response', 'data' => $data]);
    }
    
    /**
     * Fetch customer details from API and return a structured response.
     */
    private function fetchAndReturnCustomerData($bridgeCustomerId, $customer)
    {
        $endpoint = "v0/customers/{$bridgeCustomerId}";
        $data = $this->sendRequest($endpoint);

        // dd($data); exit;
        if (!is_array($data) || !isset($data['status'])) {
            return get_error_response(['error' => 'Failed to retrieve customer details', 'data' => $data]);
        }
        $resp = [
            "first_name" => $data['first_name'] ?? '',
            "last_name" => $data['last_name'] ?? '',
            "status" => $data['status'],
            "kyc_rejection_reasons" => $data['rejection_reasons'] ?? [],
            "kyc_requirements_due" => $data['requirements_due'] ?? [],
            "kyc_future_requirements_due" => $data['future_requirements_due'] ?? [],
            'bio_data' => $customer
        ];
        $is_va_approved = false;
        if($customer->kyc_verified_date == null) {
            $customer->update([
                'kyc_verified_date' => now()
            ]);
        }
        $customer->update([
            'bridge_customer_id' => $data['id'],
            'customer_status' => 'active',
            'customer_kyc_status' => 'approved',
        ]);
        $resp["kyc_link"] = route('checkout.kyc', $customer->customer_id);
        if($data['endorsements']) {
            foreach ($data['endorsements'] as $v) {
                if($v["name"] == "base" && $v['status'] == "approved") {
                    $is_va_approved = true;
                    $customer->can_create_va = true;
                    $customer->save();
                    unset($resp["kyc_link"]);
                } 
            }
        }
        $resp['is_va_approved'] = $is_va_approved;
        return get_success_response($resp);
    }

    public function createCustomerBridgeWallet($customerId)
    {
        $endpoint = "v0/customers/{$customerId}/wallets";
        $payload = [
            "chain" => "solana"
        ];
        $curl = $this->sendRequest($endpoint, "POST", $payload);
        if (isset($curl['code'])) {
            return ['error' => $curl['message']];
        }

        return $curl;
    }

    public function getCustomerBridgeWallet($customerId = null, $walletId = null)
    {
        $endpoint = "v0/customers/{$customerId}/wallets/{$walletId}";
        $curl = $this->sendRequest($endpoint);
        if (isset($curl['code'])) {
            return ['error' => $curl['message']];
        }

        return $curl;
    }

    public function createVirtualAccount($customerId)
    {
        $request = request();
        
        $customer = Customer::where('customer_id', $customerId)->first();
        // $customer->bridge_customer_id = "0ce55745-48d8-441d-90d8-6405293356e2";
        // $customer->save();

        // var_dump($customer); exit;
        if(!$customer) {
            return ['error' => "Customer with ID: {$customerId} not found!."];
        }

        if(!$customer->bridge_customer_id) {
            return ['error' => 'Customer not enrolled for service or KYC not complete '.$customer->bridge_customer_id];
        }

        $endpoint = "v0/customers/{$customer->bridge_customer_id}/virtual_accounts";
        $destinationAddress = $this->createWallet();

        Log::debug("wallet address for virtual account: ", ['addressie' => $destinationAddress]);

        if($destinationAddress == false) {
            return ["error"=> "Unable to generate virtual account"];
        }

        $currency = $request_currency = request()->currency;
        if(!in_array($currency, ['USD', 'MXN_USD', 'EUR'])) {
            return['error' => 'Invalid currency provided'];
        }
        if ($currency == "MXN_USD") {
            $currency = "MXN";
        }

        $payload = [
            "developer_fee_percent" => "0",
            "source" => [
                "currency" => strtolower($currency)
            ],
            "destination" => [
                "currency" => "usdc",
                "payment_rail" => "solana",
                "address" => $destinationAddress
            ]
        ];

        $data = $this->sendRequest($endpoint, "POST", $payload);

        if(request()->has('debug')) {
            var_dump(['result' => $data, 'payload' => $payload]); exit;
        }

        if(isset($data['message']) && isset($data['code'])) {
            return ['error' => $data['message']];
        }

        if (isset($data['error']) || (isset($data['status']) && $data['status'] < 200)) {
            return ["error" => $data['error'] ?? $data];
        }
        
        $deposit = $data['source_deposit_instructions'] ?? [];

        if ($request_currency === 'usd' && isset($deposit['bank_account_number'])) {
            // Handle USD
            return VirtualAccount::create([
                "account_id" => $data['id'],
                "user_id" => active_user(),
                "currency" => $request_currency,
                "request_object" => $request->all(),
                "customer_id" => $this->customer->customer_id ?? null,
                "account_number" => $deposit['bank_account_number'],
                "account_info" => [
                    "country" => $request->country,
                    "currency" => $request_currency,
                    "account_type" => "checking",
                    "bank_address" => "1801 Main Street, Kansas City, MO 64108",
                    "account_number" => $deposit['bank_account_number'],
                    "bank_name" => $deposit['bank_name'] ?? null,
                    "routing_number" => $deposit['bank_routing_number'] ?? null,
                    "account_name" => $customer->customer_name,
                ],
                "extra_data" => $data
            ]);

        } elseif ($request_currency === 'mxn' && isset($deposit['clabe'])) {
            // Handle MXN
            return VirtualAccount::create([
                "account_id" => $data['id'],
                "user_id" => active_user(),
                "currency" => $request_currency,
                "request_object" => $request->all(),
                "customer_id" => $this->customer->customer_id ?? null,
                "account_number" => $deposit['clabe'],
                "account_info" => [
                    "country" => $request->country,
                    "currency" => $request_currency,
                    "account_type" => "clabe",
                    "bank_address" => null,
                    "account_number" => $deposit['clabe'],
                    "bank_name" => null,
                    "routing_number" => null,
                    "account_name" => $deposit['account_holder_name'] ?? null,
                ],
                "extra_data" => $data
            ]);

        } elseif ($request_currency === 'eur' && isset($deposit['iban'])) {
            // Handle EUR
            return VirtualAccount::create([
                "account_id" => $data['id'],
                "user_id" => active_user(),
                "currency" => $request_currency,
                "request_object" => $request->all(),
                "customer_id" => $this->customer->customer_id ?? null,
                "account_number" => $deposit['iban'],
                "account_info" => [
                    "country" => $request->country,
                    "currency" => $request_currency,
                    "account_type" => "iban",
                    "bank_address" => $deposit['bank_address'] ?? null,
                    "account_number" => $deposit['iban'],
                    "bank_name" => $deposit['bank_name'] ?? null,
                    "routing_number" => $deposit['bic'] ?? null,
                    "account_name" => $deposit['account_holder_name'] ?? null,
                ],
                "extra_data" => $data
            ]);
        }

    }

    public function getVirtualAccount($virtual_account_id)
    {
        $endpoint = "customers/{$this->customer->bridge_customer_id}/virtual_accounts/{$virtual_account_id}";
        $data = $this->sendRequest($endpoint, "POST");
        return $data;
    }

    public function enableVirtualAccount($virtual_account_id)
    {
        $endpoint = "customers/{$this->customer->bridge_customer_id}/virtual_accounts/{$virtual_account_id}/reactivate";
        $data = $this->sendRequest($endpoint, "POST");
        return $data;
    }

    public function disableVirtualAccount($virtual_account_id)
    {
        $endpoint = "customers/{$this->customer->bridge_customer_id}/virtual_accounts/{$virtual_account_id}/deactivate";
        $data = $this->sendRequest($endpoint, "POST");
        return $data;
    }

    public function virtualAccountActivity($virtual_account_id)
    {
        $endpoint = "customers/{$this->customer->bridge_customer_id}/virtual_accounts/{$virtual_account_id}/history";
        $data = $this->sendRequest($endpoint, "POST");
        return $data;
    }

    public function makePayout($quoteId)
    {
        try {
            $payout = Withdraw::with('user', 'transactions', 'payoutGateway', 'beneficiary')->findOrFail($quoteId);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return ['error' => 'Payout not found'];
        }

        if (isset($payout->raw_data) && isset($payout->raw_data['customer_id']) && !empty($payout->raw_data['customer_id'])) {
            $payout['customer'] = Customer::whereCustomerId($payout->raw_data['customer_id'])->first();
        }

        $to_currency = strtolower($payout->payoutGateway->currency);

        $to_payment_rail = "ach";
        if ($to_currency == "usd") {
            if ($payout->payoutGateway->method_name == 'Fedwire') {
                $to_payment_rail = 'wire';
            }
            if ($payout->payoutGateway->method_name == 'ach') {
                $to_payment_rail = "ach";
            }
        } elseif ($to_currency == "eur") {
            $to_payment_rail = "sepa";
        }

        if (!$payout->beneficiary || !$payout->beneficiary->bridge_customer_id || !$payout->beneficiary->bridge_id) {
            return ['error' => 'Beneficiary details are incomplete'];
        }

        $destinationAddress = "cf413b55-4fc4-4cc0-ae64-148974aaa4a5"; 

        $payload = [
            "client_reference_id" => $quoteId,
            "amount" => $payout->amount,
            "on_behalf_of" => "e994df00-c544-424d-a373-3f4db702211d", //$payout->beneficiary->bridge_customer_id,
            "source" => [
                "currency" => "usdb", //  pending the time
                "payment_rail" => "bridge_wallet",
                "bridge_wallet_id" => $destinationAddress
            ],
            "destination" => [
                "currency" => $to_currency,
                "payment_rail" => $to_payment_rail,
                "external_account_id" => $payout->beneficiary->bridge_id
            ]
        ];

        $endpoint = "v0/transfers";
        $curl = $this->sendRequest($endpoint, 'POST', $payload);
        // var_dump($curl); exit;
        if (isset($curl['code']) && $curl['code'] !== 200) {
            return ["error" => $curl['message'] ?? 'Unknown error'];
        }

        return $curl;
    }

    public function getPayout($payoutId)
    {
        $endpoint = "v0/transfers/{$payoutId}";
        $curl = $this->sendRequest($endpoint);
        return $curl;
    }

    public function externalAccounts(array $req, $gateway)
    {
        $endpoint = "v0/customers/e994df00-c544-424d-a373-3f4db702211d/external_accounts";
        $data = $req['payment_data'];

        // Normalize country code to ISO3
        $data['address']['country'] = $this->getIso3CountryCode($data['address']['country']);

        $payload = [
            'currency' => strtolower($gateway->currency),
            'account_owner_name' => $data['account_name'],
            // 'routing_number' => $data['routing_number'],
            // 'account_number' => $data['account_number'],
            'account_type' => $data['account_type'],
            'address' => [
                'street_line_1' => $data['address']['line1'],
                'street_line_2' => $data['address']['line2'],
                'city' => $data['address']['city'],
                'state' => $data['address']['state'],
                'postal_code' => $data['address']['postal_code'],
                'country' => $data['address']['country'],
            ],
        ];

        // Add account-specific details
        $payload = $this->addAccountDetails($payload, $data);

        // Send request to the endpoint
        $response = $this->sendRequest($endpoint, 'POST', $payload);

        // Save beneficiary payment method if successful
        if (isset($response['created_at'])) {
            return $this->storeBeneficiaryPaymentMethod($req, $gateway, $response);
        }

        return ['error' => $response];
    }

    /**
     * Convert ISO2 country code to ISO3 if applicable.
     */
    private function getIso3CountryCode(string $countryCode): string
    {
        if (strlen($countryCode) === 2) {
            $country = Country::where('iso2', $countryCode)->first();
            return $country ? $country->iso3 : $countryCode;
        }
        return $countryCode;
    }

    /**
     * Add account-specific details to the payload.
     */
    private function addAccountDetails(array $payload, array $data): array
    {
        switch ($data['account_type']) {
            case 'us':
                $payload['account'] = [
                    'account_number' => $data['bank_account_number'],
                    'routing_number' => $data['bank_routing_number'],
                    'checking_or_savings' => $data['checking_or_savings'],
                ];
                break;

            case 'iban':
                $data['iban_country'] = $this->getIso3CountryCode($data['iban_country']);

                $payload['iban'] = [
                    'account_number' => $data['iban_account_number'],
                    'bic' => $data['iban_bic'],
                    'country' => $data['iban_country'],
                ];
                $payload['account_owner_type'] = $data['account_owner_type'] ?? 'individual';

                if ($payload['account_owner_type'] === 'individual') {
                    $payload['first_name'] = $data['first_name'];
                    $payload['last_name'] = $data['last_name'];
                } elseif ($payload['account_owner_type'] === 'business') {
                    $payload['business_name'] = $data['business_name'];
                }
                break;
        }

        return $payload;
    }

    /**
     * Store beneficiary payment method.
     */
    private function storeBeneficiaryPaymentMethod(array $req, $gateway, array $response)
    {
        $request = request();

        $model = new BeneficiaryPaymentMethod();
        $model->user_id = active_user();
        $model->currency = $gateway->currency;
        $model->gateway_id = $request->gateway_id;
        $model->nickname = $request->nickname ?? null;
        $model->address = $request->address ?? null;
        $model->payment_data = $request->payment_data;
        $model->beneficiary_id = $request->beneficiary_id;
        $model->bridge_id = $response['id'];
        $model->bridge_customer_id = $response['customer_id'];
        $model->bridge_response = $response;

        return $model->save() ? $model : ['error' => 'Failed to save payment method.'];
    }

    public function sendRequest($endpoint, $method = "GET", $payload = [])
    {
        $method = strtolower($method);
        $url = env('BRIDGE_BASE_URL');
        $apiKey = env('BRIDGE_API_KEY');

        if ($method === 'get' && !empty($payload)) {
            $url .= $endpoint . '?' . http_build_query($payload);
        } else {
            $url .= $endpoint;
        }

        $headers = [
            "Api-Key" => $apiKey,
            "Accept" => "application/json",
        ];

        if ($method === 'post') {
            $headers["Idempotency-Key"] = generate_uuid();
        }

        $response = Http::withHeaders($headers)->$method($url, $payload);

        $data = $response->json();

        // Log::info("Bridge Api Response: ", ["payload" => $payload, "response" => $data]);

        if (!is_array($data)) {
            $data = json_decode($data, true);
        }

        return $data;
    }

    private function formatBase64Image($imageUrl, $format = 'jpeg')
    {
        // Ensure the URL is valid
        if (empty($imageUrl)) {
            return null;
        }

        // Extract the base64 content (assumes the input URL is already base64 encoded)
        $base64Data = file_get_contents($imageUrl); // Fetch the image content from the URL
        $encodedData = base64_encode($base64Data); // Encode the binary data into base64

        // Format as a proper data URI
        return "data:image/{$format};base64,{$encodedData}";
    }

    public function createWallet($customerId = null)
    {
        // Generate wallet address
        $yativo = new CryptoYativoController();
        $curl = $yativo->generateCustomerWallet($customerId);

        if (!isset($curl['address'])) {
            Log::error('Failed to generate wallet address', ['response' => $curl]);
            return ['error' => $curl['error'] ?? 'Unable to generate wallet address at the moment, please contact support'];
        }

        return $curl['address'];
    }

    public function BridgeWebhook(Request $request)
    {  
        // Validate signature first
        if (!$this->processEvent($request)) {
            return response()->json(['message' => 'Invalid webhook signature.'], 403);
        }      
        $payload = $request->all();
        Log::info("Incoming data: ", ['bridge_webhook' => $payload]);
        if(isset($payload['data'])) {
            foreach ($payload['data'] as $event) {
                $this->_processWebhook($event);
            }
        } else if(isset($payload['bridge_webhook'])) { 
            $this->_processWebhook($payload['bridge_webhook']);
        } else {
            $this->_processWebhook($payload);
        }

        return response()->json(['status' => 'success']);
    }

    private function _processWebhook($event){
        $eventType = $event['event_type'];
        $eventData = $event['event_object'];

        // if event created_at date is greater than today then ignore the event
        if(!$this->processEvent(request())) {
            return response()->json(["message" => "received and processed"]);
        }
        // calculate and check event signature
        $privateKeyPath = storage_path("app/keys/bridge.pem");

        switch ($eventType) {
            case 'virtual_account.activity.updated':
            case 'virtual_account.activity.created':
                $this->processVirtualAccountWebhook($eventData);
                break;

            case 'kyc_link.updated.status_transitioned':
                $this->handleKycStatusUpdate($eventData);
                break;

            case 'customer.updated.status_transitioned':
                $this->handleKycStatusUpdate($eventData);
                break;

            default:
                Log::info("Unhandled webhook event: $eventType");
                break;
        }
    }

    private function processVirtualAccountWebhook($eventData)
    {
        $accountId = $eventData['virtual_account_id'];
        $customer = Customer::where('bridge_customer_id', $eventData['customer_id'])->first();
        if ($customer) {
            $customer = [];
        }
    
        $vc = VirtualAccount::where("account_id", $accountId)->first();
        
        if (!$vc) {
            Log::error("Virtual account not found for ID: $accountId");
            return;
        }
    
        $payload = $eventData;

        if($payload['type'] != "payment_processed") {
            return false;
        }

        // Retrieve environment variables
        $botToken = env("TELEGRAM_TOKEN");
        $chatId = env('TELEGRAM_CHAT_ID');

        // Log the notification call
        Log::debug("Telegram notification called");

        // Construct the Telegram API URL
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        // Send the HTTP request using Laravel's HTTP client
        try {
            $response = Http::post($url, [
                'text' => json_encode($payload),
                'chat_id' => $chatId,
                'protect_content' => true,
                'parse_mode' => 'html'
            ]);
            
            if ($response->successful()) {
                Log::debug("Telegram notification sent successfully");
            } else {
                Log::error("Telegram notification failed: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Telegram notification error: " . $e->getMessage());
        }

        $user = User::whereId($vc->user_id)->first();
        if (!$user) {
            Log::error("User not found for virtual account ID: $accountId");
            return;
        }
    
        if (strtolower($payload['type']) === "payment_processed") {
            $vc_status = SendMoneyController::SUCCESS;
        }                                                        
            
        $vc_status = strtolower($payload['type']) === "payment_processed" ? SendMoneyController::SUCCESS : "pending";
        
        if (Schema::hasColumn('deposits', 'deposit_currency')) {
            Schema::table('deposits', function (Blueprint $table) {
                $table->string('deposit_currency')->nullable()->change();
                $table->string('currency')->nullable()->change();
                $table->string('receive_amount')->nullable()->change();
            });
        }

        $deposit_amount = 0;

        // Log::info($payload); exit;
        $sent_amount = $payload['receipt']['initial_amount'] ?? $payload['amount'];
        $payment_rail = $payload['source']['payment_rail'];
        $percentage = floatval(0.60 / 100);
        $float_fee = floatval($sent_amount * $percentage);

        if($payment_rail == "ach_push") {
            // fee for ach is 0.60% + $0.60
            $fixed_fee = 0.60;
            $total_fee = floatval($float_fee + $fixed_fee);
            $deposit_amount = floatval($sent_amount - $total_fee);
        } else {
            $fixed_fee = 25;
            $total_fee = floatval($float_fee + $fixed_fee);
            $deposit_amount = floatval($sent_amount - $total_fee);
        } 

        
        // Update or create the deposit record
        $deposit = Deposit::updateOrCreate(
            [
                'gateway_deposit_id' => $payload["id"]
            ],
            [
                'user_id' => $user->id,
                'amount' => $deposit_amount,
                'currency' => strtoupper($payload['currency']),
                'deposit_currency' => strtoupper($payload['currency']),
                'gateway' => 99999999,
                'status' => $vc_status,
                'receive_amount' => floatval($deposit_amount),
                'meta' => $payload,
            ]
        );
                
        // Update or create VirtualAccountDeposit
        VirtualAccountDeposit::updateOrCreate(
            [
                "user_id" => $deposit->user_id,
                "deposit_id" => $deposit->id,
            ],
            [
                "currency" => strtoupper($payload['currency']),
                "amount" => $deposit->amount,
                "account_number" => $vc->account_number,
                "status" => $vc_status,
            ]
        );
        
        // Create Transaction Record
        TransactionRecord::create([
            "user_id" => $user->id,
            "transaction_beneficiary_id" => $user->id,
            "transaction_id" => $payload['deposit_id'],
            "transaction_amount" => $deposit_amount,
            "gateway_id" => 99999999,
            "transaction_status" => $vc_status,
            "transaction_type" => 'virtual_account',
            "transaction_memo" => "payin",
            "transaction_currency" => strtoupper($payload['currency']),
            "base_currency" => strtoupper($payload['currency']),
            "secondary_currency" => strtoupper($payload['currency']),
            "transaction_purpose" => "VIRTUAL_ACCOUNT_DEPOSIT",
            "transaction_payin_details" => [
                'sender_name' => $payload['source']['sender_name'] ?? null,
                'trace_number' => $payload['source']['trace_number'] ?? null,
                'bank_routing_number' => $payload['source']['sender_bank_routing_number'] ?? null,
                'description' => $payload['source']['description'] ?? null,
                "transaction_fees" => $total_fee
            ],
            "transaction_payout_details" => null,
        ]);
        
        
        if (strtolower($payload['type']) === "payment_processed") {
            $wallet = $user->getWallet('usd');
            if ($wallet) {
                // Check if transaction already exists for this deposit
                $existingTransaction = $wallet->transactions()
                    ->where('meta->deposit_id', $deposit->id)
                    ->first();
    
                if (!$existingTransaction) {
                    $wallet->deposit(floatval($deposit_amount * 100), [
                        'deposit_id' => $deposit->id,
                        'gateway_deposit_id' => $payload['id'],
                        'sender' => $payload['source']['description'] ?? null
                    ]);
                }
            }
        }

        $incomingData = $eventData;
        $customer = Customer::where("bridge_customer_id", $eventData['customer_id'])->first();
        $vc = VirtualAccount::where("customer_id", $customer->id)->first();
        Log::debug("User for user info is: ", ['user' => $user]);
        $userId = $user->id;

        $webhookData = [
            "event.type" => "virtual_account.deposit",
            "payload" => [
                "amount" => $incomingData['amount'],
                "currency" => "USD",
                "status" => "completed",
                "credited_amount" => $deposit_amount,
                "transaction_type" => "virtual_account_topup",
                "transaction_id" => "TXN123456789",
                "customer" => $customer,
                "source" => $incomingData['source']
            ]
        ];

        dispatch(function () use ($userId, $webhookData) {
            $webhook = Webhook::whereUserId($userId)->first();
            if ($webhook) {
                WebhookCall::create()
                    ->meta(['_uid' => $webhook->user_id])
                    ->url($webhook->url)
                    ->useSecret($webhook->secret)
                    ->payload($webhookData)
                    ->dispatchSync();
            }
        })->afterResponse();
        // Log::info("Bridge virtual account deposit completed: ", ['wallet' => $wallet, 'payload' => $eventData]);
    }

    private function handleKycStatusUpdate($data)
    {
        Log::info("Customer KYC details: ", ['details' => $data]);
        $customer = Customer::where('bridge_customer_id', $data['id'])->orWhere('customer_kyc_email', $data['email'])->first();
        // Update customer status if active
        $status = $data['event_object_status'] ?? $data['status'] ?? $data['kyc_status'] ?? "pending";
        if ($customer && $status === 'active') {
            $customer->update([
                'bridge_customer_id' => $data['id'],
                'customer_status' => 'active',
                'customer_kyc_status' => 'approved'
            ]);
        }

        return $this->fetchAndReturnCustomerData($data['id'], $customer);
    }

    private function processPayinWebhook($data)
    {
        try {
            $order = TransactionRecord::where([
                "transaction_id" => $data['client_reference_id'],
                "transaction_memo" => "payin",
            ])->first();

            if ($order) {
                $deposit_services = new DepositService();
                $deposit_services->process_deposit($order->transaction_id);
                return response()->json(['message' => 'Order processed successfully'], 200);
            }

            return http_respnose_code(200);
        } catch (\Throwable $th) {
            return http_respnose_code($th->getCode());
        }
    }

    public function processEvent(Request $request)
    {
        $signatureHeader = $request->header('X-Webhook-Signature');
        
        $matches = [];
        if (!$signatureHeader || !preg_match('/^t=(\d+),v0=(.*)$/', $signatureHeader, $matches)) {
            return false; // $this->render400('Malformed signature header');
        }
        [$timestamp, $signature] = $matches;

        if (!$timestamp || !$signature) {
            return false; // $this->render400('Malformed signature header');
        }

        // Validate timestamp within 10 minutes
        $timestampSeconds = (int) $timestamp / 1000;
        if ($timestampSeconds < Carbon::now()->subMinutes(10)->timestamp) {
            return false; // $this->render400('Invalid signature!');
        }

        // Read request body
        $bodyData = $request->getContent();

        // Prepare data for verification
        $dataToVerify = "{$timestamp}.{$bodyData}";
        $computedHash = hash('sha256', $dataToVerify, true);

        // Decode signature
        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return false; // $this->render400('Invalid signature!');
        }

        // Get public key
        $publicKey = $this->getPublicKey();
        if (!$publicKey) {
            return false; // $this->render400('Server configuration error');
        }

        // Verify signature
        $decryptedHash = '';
        $verification = openssl_public_decrypt(
            $decodedSignature,
            $decryptedHash,
            $publicKey,
            OPENSSL_PKCS1_PADDING
        );

        if (!$verification || $decryptedHash !== $computedHash) {
            return false; // $this->render400('Invalid signature!');
        }

        // Process JSON body
        $bodyJson = json_decode($bodyData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false; // $this->render400('Invalid JSON payload');
        }

        // TODO: Store event for asynchronous processing
        return true;
        // return response()->json(['message' => 'Event processing OK!'], 200);
    }

    private function getPublicKey()
    {
        // Retrieve PEM-formatted public key from config (example)
        $publicKeyPem = storage_path('app/keys/bridge.pem');
        
        return openssl_pkey_get_public($publicKeyPem);
    }

    private function render400(string $message)
    {
        return response()->json(['message' => $message], 400);
    }

    public function updateAllVirtualAccounts()
    {
        $url = "https://api.bridge.xyz/v0/virtual_accounts?status=activated&limit=100";

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'Api-Key' => env('BRIDGE_API_KEY'),
        ])->get($url);

        if ($response->failed()) {
            Log::error('Failed to fetch virtual accounts', ['response' => $response->body()]);
            return response()->json(['error' => 'Unable to fetch virtual accounts'], 500);
        }

        $accounts = $response->json('data') ?? [];

        $results = [];

        foreach ($accounts as $account) {
            $customerId = $account['customer_id'] ?? null;
            $virtualAccountId = $account['id'] ?? null;

            if (!$customerId || !$virtualAccountId) {
                Log::warning('Missing customer or account ID', ['account' => $account]);
                continue;
            }
            // get the yativo customer using the bridge customer ID
            $cust = Customer::where('bridge_customer_id', $customerId)->first();
            if(!$cust) {                
                $results[] = [
                    'customer_id' => $customerId,
                    'account_id' => $virtualAccountId,
                    'status' => 'customer record not found in DB',
                    'error' => $account,
                ];
                continue;
            }
            $walletAddress = $this->createWallet($cust->customer_id);

            if (is_array($walletAddress) && isset($walletAddress['error'])) {
                Log::error('Wallet creation failed', [
                    'customer_id' => $customerId,
                    'error' => $walletAddress['error']
                ]);
                $results[] = [
                    'customer_id' => $customerId,
                    'account_id' => $virtualAccountId,
                    'status' => 'failed',
                    'error' => $walletAddress,
                ];
                continue;
            }

            // Now update the destination
            $updateResponse = Http::withHeaders([
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'Api-Key' => env('BRIDGE_API_KEY'),
            ])->put("https://api.bridge.xyz/v0/customers/{$customerId}/virtual_accounts/{$virtualAccountId}", [
                'destination' => [
                    'currency' => 'usdc',
                    'payment_rail' => 'solana',
                    'address' => $walletAddress,
                ]
            ]);

            if ($updateResponse->successful()) {
                $results[] = [
                    'customer_id' => $customerId,
                    'account_id' => $virtualAccountId,
                    'status' => 'updated',
                    'address' => $walletAddress,
                ];
            } else {
                Log::error('Update failed', [
                    'customer_id' => $customerId,
                    'account_id' => $virtualAccountId,
                    'response' => $updateResponse->body()
                ]);
                $results[] = [
                    'customer_id' => $customerId,
                    'account_id' => $virtualAccountId,
                    'status' => 'failed',
                    'error' => 'Update failed',
                ];
            }
        }

        return response()->json([
            'status' => 'completed',
            'results' => $results,
        ]);
    }
}
