<?php

namespace App\Http\Controllers;

use App\Models\Business\VirtualAccount;
use App\Models\Country;
use App\Models\Withdraw;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Modules\Customer\app\Models\Customer;

class BridgeController extends Controller
{
    public $customer, $customerId;

    public function __construct()
    {
        // $customer = Customer::whereCustomerId(request()->customer_id)->first();
        $this->customer = DB::table('customers')->where('customer_id', request()->customer_id)->where('user_id', auth()->id())->first();
        // Log::info("Customer Info: ", (array) $this->customer);
        $this->customerId = $this->customer->customer_id ?? null;
    }

    /**
     * GEt KYC link to add a customer
     * @return 
     */
    public function addCustomerV1(array|object $customer = [])
    {
        $customer = Customer::where('customer_id', request()->customer_id)->first();
        $bridgeData = $this->sendRequest("/v0/customers", 'POST', $customer);
        if(isset($bridgeData['id'])) {
            // update the customer with the bridge customer ID
            $customer->update([
                "bridge_customer_id" => $bridgeData['id'],
            ]);
        }

        return $bridgeData;
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

    public function addCustomer(array $customer = [])
    {
        $endpoint = 'v0/kyc_links';
        $payload = [
            'full_name' => $customer['customer_name'],
            'email' => $customer['customer_email'],
            'type' => $customer['customer_type'] ?? 'individual',
            'endorsements' => ['sepa'],
            'redirect_uri' => $customer['redirect_uri'] ?? env('WEB_URL'),
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
                'customer_email' => $customer['customer_email'],
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

    public function getCustomer($customerId = null)
    {

        $request = request();
        $endpoint = "customers/{$this->customer->bridge_customer_id}";
        $data = $this->sendRequest($endpoint);
        if(isset($data['status'])) {
            return [
                "first_name" => $data['first_name'],
                "last_name" => $data['last_name'],
                "status" => $data['status'],
                "rejection_reasons" => $data['rejection_reasons'],
                "requirements_due" => $data['requirements_due'],
                "future_requirements_due" => $data['future_requirements_due']
            ];
        }
        return $data;
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
        if(!auth()->user()->bridge_customer_id) {
            return ['error' => 'Customer not enrolled for service'];
        }
        $endpoint = "v0/customers/{$this->customer->bridge_customer_id}/virtual_accounts";
        $destinationAddress = "qFZjGVNS1Tvfs28TS9YumBKTvc44bh6Yt3V83rRUvvD"; //$this->createWallet($this->customer->bridge_customer_id);

        if($destinationAddress == false) {
            return ["error"=> "Unable to generate virtual account"];
        }

        $payload = [
            "developer_fee_percent" => env('BRIDGE_DEVELOPER_FEE_PERCENT', "0.6"),
            "source" => [
                "currency" => "usd"
            ],
            "destination" => [
                "currency" => env('BRIDGE_DESTINATION_CURRENCY', "usdb"),
                "payment_rail" => "solana", //env('BRIDGE_PAYMENT_RAIL', "polygon"),
                "address" => $destinationAddress
            ]
        ];

        $data = $this->sendRequest($endpoint, "POST", $payload);

        if (isset($data['error']) || $data['status'] < 200) {
            return ["error" => $data['error'] ?? $data];
        }
        if (isset($data['source_deposit_instructions']['bank_account_number'])) {
            return VirtualAccount::create([
                "account_id" => $data['id'],
                "user_id" => active_user(),
                "currency" => "USD",
                "request_object" => $request->all(),
                "customer_id" => $this->customer->customer_id ?? null,
                "account_number" => $data['source_deposit_instructions']['bank_account_number'] ?? null,
                "account_info" => [
                    "country" => $request->country,
                    "currency" => "USD",
                    "account_number" => $data['source_deposit_instructions']['bank_account_number'] ?? null,
                    "bank_name" => $data['source_deposit_instructions']['bank_name'] ?? null,
                    "routing_number" => $data['source_deposit_instructions']['bank_routing_number'] ?? null,
                    "account_name" => auth()->user()->name,
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
        $payout = Withdraw::with('user', 'transactions', 'payoutGateway', 'beneficiary')->findOrFail($quoteId);
        if (isset($payout->raw_data['customer_id']) && !empty($payout->raw_data['customer_id'])) {
            $payout['customer'] = Customer::whereCustomerId($payout->raw_data['customer_id'])->first();
        }

        $to_payment_rail = strtolower($payout->payoutGateway->payment_mode);
        $to_currency = strtolower($payout->payoutGateway->currency);
        if ($to_currency == "eur") {
            $to_payment_rail = "sepa";
        }

        $payload = [
            "client_reference_id" => $quoteId,
            "amount" => $payout->amount,
            "on_behalf_of" => $payout->beneficiary->bridge_customer_id,
            "source" => [
                "currency" => "usdc",
                "payment_rail" => "bridge_wallet",
                "bridge_wallet_id" => "xoxo" // Zee Technology SPA wallet ID
            ],
            "destination" => [
                "currency" => $to_currency, // eur and usd
                "payment_rail" => $to_payment_rail,
                "external_account_id" => $payout->beneficiary->bridge_id
            ]
        ];

        $endpoint = "v0/transfers";
        $curl = $this->sendRequest($endpoint, 'POST', $payload);

        if (isset($curl['code'])) {
            return ["error" => $curl["message"]];
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
        $endpoint = "v0/customers/{$this->customer->bridge_customer_id}/external_accounts";
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

        if ($method !== 'get') {
            $headers["Idempotency-Key"] = generate_uuid();
        }

        $response = Http::withHeaders($headers)->$method($url, $payload);

        $data = $response->json();

        Log::info("Bridge Api Response: ", ["payload" => $payload, "response" => $data]);

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

    public function createWallet($customerId)
    {
        return "qFZjGVNS1Tvfs28TS9YumBKTvc44bh6Yt3V83rRUvvD"; // fixed wallet belonging to 
        $endpoint = "v0/customers/{$customerId}/wallets";
        $curl = $this->sendRequest($endpoint, "POST", $payload = [
            'chain' => 'solana'
        ]);

        if(isset($curl['address'])) {
            return $curl['address'];
        }

        return false;
    }

    public function BridgeWebhook(Request $request)
    {
        // Get the request body
        $incoming = $request()->all();
        if(isset($request->event_object)) {
            $data = (array)$request->event_object;
            if($request->event_type == "customer.created" OR $request->event_type == "customer.updated.status_transitioned") {
                $customer = Customer::where('customer_email', $data['email'])->first();
                $userId = $customer->user_id;
                if($customer && $data['status'] == "approved" or $data['status'] == "active"  or $data['status'] == "completed") {
                    $customer->customer_status = 'active';
                    $customer->customer_kyc_status = 'approved';
                    $customer->save();
                    // Queue webhook notification
                    dispatch(function () use ($userId, $customer) {
                        $webhook = Webhook::whereUserId($userId)->first();
                        if ($webhook) {
                            WebhookCall::create()
                                ->meta(['_uid' => $webhook->user_id])
                                ->url($webhook->url)
                                ->useSecret($webhook->secret)
                                ->payload([
                                    "event.type" => "customer.created",
                                    "payload" => $customer,
                                ])
                                ->dispatchSync();
                        }
                    })->afterResponse();
                }
            }
            if($request->event_type == "virtual_account.activity.created" OR $request->event_type == "virtual_account.activity.updated") {
                return $this->processVirtualAccountWebhook($data);
            }
        }


          
        return get_success_response($bridgeData);
    }

    private function processVirtualAccountWebhook($incomingData)
    {
        $customer = Customer::where("bridge_customer_id", $bridgeCustomerId)->first();
        $vc = VirtualAccount::where("customer_id", $customer->id)->first();

        $userId = $vc->user_id;

        $txn = add_usd_virtual_card_deposit($incomingData);
        
        $webhookData = [
            "event.type" => "virtual_account.deposit",
            "payload" => [
                "amount" => $incomingData['amount'],
                "currency" => $incomingData['currency'],
                "status" => "completed",
                "credited_amount" => $incomingData['subtotal_amount'],
                "transaction_type" => "virtual_account_topup",
                "transaction_id" => "TXN123456789",
                "customer" => $customer,
                "raw_data" => $incomingData['source']
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
    }

    private function processPayinWebhook()
    {
        //
    }


    public function processEvent(Request $request)
    {
        $signatureHeader = $request->header('X-Webhook-Signature');
        
        if (!$signatureHeader || !preg_match('/^t=(\d+),v0=(.*)$/', $signatureHeader, $matches)) {
            return $this->render400('Malformed signature header');
        }

        [, $timestamp, $signature] = $matches;

        if (!$timestamp || !$signature) {
            return $this->render400('Malformed signature header');
        }

        // Validate timestamp within 10 minutes
        $timestampSeconds = (int) $timestamp / 1000;
        if ($timestampSeconds < Carbon::now()->subMinutes(10)->timestamp) {
            return $this->render400('Invalid signature!');
        }

        // Read request body
        $bodyData = $request->getContent();

        // Prepare data for verification
        $dataToVerify = "{$timestamp}.{$bodyData}";
        $computedHash = hash('sha256', $dataToVerify, true);

        // Decode signature
        $decodedSignature = base64_decode($signature, true);
        if ($decodedSignature === false) {
            return $this->render400('Invalid signature!');
        }

        // Get public key
        $publicKey = $this->getPublicKey();
        if (!$publicKey) {
            return $this->render400('Server configuration error');
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
            return $this->render400('Invalid signature!');
        }

        // Process JSON body
        $bodyJson = json_decode($bodyData, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->render400('Invalid JSON payload');
        }

        // TODO: Store event for asynchronous processing

        return response()->json(['message' => 'Event processing OK!'], 200);
    }

    private function render400(string $message)
    {
        return response()->json(['message' => $message], 400);
    }

    private function getPublicKey()
    {
        // Retrieve PEM-formatted public key from config (example)
        $publicKeyPem = storage_path('app/keys/bridge.pem');
        
        return openssl_pkey_get_public($publicKeyPem);
    }
}
