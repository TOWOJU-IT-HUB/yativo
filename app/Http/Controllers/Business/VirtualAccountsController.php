<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FincraVirtualAccountController;
use App\Models\BrlVirtualAccount;
use App\Models\Business\VirtualAccount;
use App\Models\Country;
use App\Models\localPaymentTransactions;
use App\Models\User;
use App\Models\BusinessConfig;
use App\Services\BrlaDigitalService;
use Http;
use Modules\Customer\app\Models\Customer;
use Spatie\WebhookServer\WebhookCall;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Modules\LocalPayments\app\Http\Controllers\LocalPaymentsController;
use Modules\Webhook\app\Models\Webhook;
use Str;
use Towoju5\Localpayments\Localpayments;

class VirtualAccountsController extends Controller
{
    public $baseUrl, $api_key, $businessConfig;

    public function __construct()
    {
        $this->baseUrl = env('FINCRA_BASE_URL', 'https://api.fincra.com/');
        $this->api_key = env('FINCRA_API_SECRET', '8G5hwaiw7oy9q8tCBJ6X1ltp5C20QDwJ');
    }

    public function index(Request $request)
    {
        try {
            // retrieve all users virtual accounts.
            $accounts = VirtualAccount::whereUserId(auth()->id());

            if ($request->has('currency')) {
                $accounts->where('currency', $request->currency);
            }

            if ($request->has('status')) {
                $accounts->where('status', $request->status);
            }

            if ($request->has('start_date') && $request->has('end_date')) {
                $accounts->whereBetween('created_at', [$request->start_date, $request->end_date]);
            }

            if ($request->has('search')) {
                $accounts->where(function ($query) use ($request) {
                    $query->where('account_name', 'LIKE', "%{$request->search}%")
                        ->orWhere('account_number', 'LIKE', "%{$request->search}%");
                });
            }

            $accounts = $accounts->paginate(per_page());
            return paginate_yativo($accounts);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
    public function customerVirtualAccounts($customerId)
    {
        try {
            // retrieve all users virtual accounts.
            $accounts = VirtualAccount::whereUserId(auth()->id())->where('customer_id', $customerId)->paginate(per_page());
            return paginate_yativo($accounts);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function show($account_id)
    {
        try {
            $where = [
                "user_id" => auth()->id(),
                "account_id" => $account_id
            ];

            $accounts = VirtualAccount::where($where)->first();

            if (!$accounts) {
                return get_error_response(['error' => "Invalid account ID supplied"]);
            }

            if (empty($account->account_number)) {
                $endpoint = "/api/virtual-account/$account_id";
                $local = new Localpayments;
                $request = $local->curl($endpoint, "GET");

                // sample account ID: 0a148340-4bec-4ccf-8cdc-49ab565159e7
                if (isset($request['error'])) {
                    return get_error_response(['error' => $request['message']]);
                }

                if (!isset($request['currency'])) {
                    return get_error_response(['error' => "Please try again in 5minutes"]);
                }

                $accounts = $this->handleVirtualAccountCreation($request, false);
            }

            return get_success_response($accounts);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "customer_id" => "sometimes|string|exists:customers,customer_id",
                'beneficiary.document.id' => 'required_if:currency,MXN,ARS',
                'beneficiary.document.type' => 'required_if:currency,MXN,ARS',
                'beneficiary.name' => 'required_if:currency,MXN,ARS',
                'beneficiary.lastname' => 'required_if:currency,MXN,ARS',
                'beneficiary.type' => 'required_if:currency,MXN,ARS',
                'address.city' => 'required|string',
                'address.state' => 'required|string',
                'address.zipcode' => 'required|string',
                'address.street' => 'required|string',
                'address.number' => 'required|string',
                'address.country' => 'required|string',
                "currency" => "required|string|in:MXN,BRL",
                "country" => "required|string|min:3|max:3",
                "utilityBill" => "required_if:currency,USD,EUR",
                "bankStatement" => "required_if:currency,USD,EUR",
                "sourceOfIncome" => "required_if:currency,USD,EUR",
                "occupation" => "required_if:currency,USD,EUR",
                "employmentStatus" => "required_if:currency,USD,EUR",
                "incomeBand" => "required_if:currency,USD,EUR",
                "birthDate" => "required_if:currency,USD,EUR",
                "nationality" => "required_if:currency,USD,EUR",
                "meansOfId" => "required_if:currency,USD,EUR",
            ]);

            if ($validator->fails()) {
                return get_error_response(['error' => $validator->errors()->toArray()], 400);
            }

            if ($request->currency === "BRL") {
                if (config_can_peform('can_issue_bra_virtual_account') != 'enabled') {
                    return get_error_response(['error' => 'Business not approved for this service']);
                }

                $brla = new BrlaDigitalService();
                $payload = [
                    "amount" => floatval(0)
                ];
                $user = auth()->user();
                if ($request->has('customer_id')) {
                    $customer = Customer::where('customer_id', $request->customer_id)->first();
                    $payload['subaccountId'] = $customer->brla_subaccount_id;
                }
                $payload['referenceLabel'] = Str::random(19);

                $checkout = $brla->generatePayInBRCode($payload);                
                $record = VirtualAccount::create([
                    "account_id" => $payload['referenceLabel'],
                    "user_id" => active_user(),
                    "currency" => $request->currency,
                    "request_object" => $validator->validated(),
                    "customer_id" => $request->customer_id ?? null,
                    "account_number" => $checkout["brCode"],
                    "account_info" => [
                        "country" => "BRA",
                        "currency" => "MXN",
                        "account_number" => $checkout["brCode"],
                        "bank_code" => "PIX",
                        "bank_name" => "Pix Qr Code",
                        "account_name" => $customer->first_name . " " . $customer->last_name ?? $user->name,
                    ]
                ]);

                if($record) {
                    return get_success_response($record, 201, "BRL Virtual account generated successfully");
                }
            }

            $customer = Customer::where('customer_id', $request->customer_id)->first();
            if (!$customer && ($request->currency === "USD" || $request->currency === "EUR")) {
                return get_error_response(['error' => 'Customer not found']);
            }

            $accountNumber = null;
            if (in_array($request->currency, ['MXN', 'ARS'])) {                
                if (config_can_peform('can_issue_mxn_virtual_account') != 'enabled') {
                    return get_error_response(['error' => 'Business not approved for this service']);
                }
                
                if (config_can_peform('can_issue_arg_virtual_account') != 'enabled') {
                    return get_error_response(['error' => 'Business not approved for this service']);
                }
                $accountNumber = LocalPaymentsController::getPayinAccountNumber($request->country, $request->currency, 'BankTransfer');
                if (is_array($accountNumber) && isset($accountNumber['error'])) {
                    return $accountNumber;
                }
            }

            $account_id = generate_uuid();
            $country = Country::where('name', $customer->customer_country)->first();
            $customer_name = explode(' ', $customer->customer_name);

            $data = [
                "currency" => $request->currency,
                "accountType" => "individual",
                "merchantReference" => $account_id,
            ];

            // Prepare data for specific currencies
            if ($request->currency === "MXN" || $request->currency === "ARS") {
                $data += [
                    "externalId" => $account_id,
                    "accountNumber" => $accountNumber,
                    "country" => $request->country,
                    "beneficiary" => [
                        "document" => [
                            "id" => $request->beneficiary['document']['id'],
                            "type" => $request->beneficiary['document']['type']
                        ],
                        "name" => $request->beneficiary['name'],
                        "lastname" => $request->beneficiary['lastname'],
                        "type" => strtoupper($request->beneficiary['type'])
                    ],
                    "address" => [
                        "city" => $request->address['city'],
                        "state" => $request->address['state'],
                        "zipcode" => $request->address['zipcode'],
                        "street" => $request->address['street'],
                        "number" => $request->address['number'],
                        "country" => $request->address['country']
                    ]
                ];

                $local = new Localpayments();
                $curl = $local->bank()->createVirtualAccount($data);

            } elseif (in_array($request->currency, ['USD', 'EUR'])) {
                $data += [
                    "utilityBill" => $request->utilityBill,
                    "bankStatement" => $request->bankStatement,
                    "KYCInformation" => [
                        "address" => [
                            "state" => $customer->json_data['address']['state'] ?? $request->address['state'],
                            "city" => $customer->json_data['address']['city'] ?? $request->address['city'],
                            "street" => $customer->customer_address ?? $request->address['street'],
                            "zip" => $customer->json_data['address']['zip'] ?? $request->address['zipcode'],
                            "countryOfResidence" => $country->iso2 ?? $request->address['country'],
                            "number" => $customer->json_data['address']['number'] ?? $request->address['number']
                        ],
                        "firstName" => $customer_name[0],
                        "lastName" => $customer_name[1] ?? $customer_name[0],
                        "email" => $customer->customer_email,
                        "phone" => $customer->customer_phone,
                        "sourceOfIncome" => $request->sourceOfIncome,
                        "occupation" => $request->occupation,
                        "employmentStatus" => $request->employmentStatus,
                        "incomeBand" => $request->incomeBand,
                        "birthDate" => $request->birthDate,
                        "nationality" => $country->iso2 ?? $request->nationality,
                        "document" => [
                            "type" => $customer->customer_idType,
                            "number" => $customer->customer_idNumber,
                            "issuedCountryCode" => $customer->customer_idCountry ?? $country->iso2,
                            "issuedBy" => "government",
                            "issuedDate" => $customer->json_data['document']['issuedDate'] ?? '2017-09-07',
                            "expirationDate" => $customer->customer_idExpiration
                        ],
                    ],
                    "meansOfId" => $request->meansOfId,
                ];

                $curl = Http::withHeaders([
                    'accept' => 'application/json',
                    'content-type' => 'application/json',
                    "api-key" => $this->api_key,
                ])->post("{$this->baseUrl}profile/virtual-accounts/requests", $data)->json();
            }

            if (!is_array($curl)) {
                $curl = json_decode($curl, true);
            }

            if (isset($curl['status']) && (int) $curl['status']['code'] != 100) {
                $errors = $curl['error'] ?? ['Unknown error occurred'];
                return get_error_response(["error" => $errors]);
            }

            $record = VirtualAccount::create([
                "account_id" => $account_id,
                "user_id" => active_user(),
                "currency" => $request->currency,
                "request_object" => $validator->validated(),
                "customer_id" => $request->customer_id
            ]);

            if (isset($curl['success']) && ($curl['success'] == false)) {
                return get_error_response(['error' => $curl['errors']]);
            }

            return get_success_response(['record' => $record, 'result' => $curl]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function get_account_details($account_id, $isApi = true)
    {
        try {
            $local = new Localpayments();
            $curl = $local->bank()->getVirtualAccount($account_id);
            if ($isApi === false) {
                return $curl;
            }
            if (isset($curl['status']) and (int) $curl['status']['code'] != 200) {
                foreach ($curl["error"] as $error) {
                    $errors[] = $error;
                }
                return get_error_response(["error" => $errors]);
            }
        } catch (\Throwable $th) {
            return ["error" => $th->getMessage()];
        }
    }

    public function bulk_account_creation(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                "customer_id" => "sometimes|string|exists:customers,customer_id",
                "currency" => "required|string|in:MXN,ARS,BRL",
                "country" => "required|string|min:3|max:3|in:MEX,ARG",
                "accounts" => "required|array|min:1",
                "accounts.*.beneficiary.document.id" => "required|string",
                "accounts.*.beneficiary.document.type" => "required|string",
                "accounts.*.beneficiary.name" => "required|string",
                "accounts.*.beneficiary.lastname" => "required|string",
                "accounts.*.beneficiary.type" => "required|string",
                "accounts.*.address.city" => "required|string",
                "accounts.*.address.state" => "required|string",
                "accounts.*.address.zipcode" => "required|string",
                "accounts.*.address.street" => "required|string",
                "accounts.*.address.number" => "required|string",
                "accounts.*.address.country" => "required|string",
            ]);

            if ($validator->fails()) {
                return get_error_response(['error' => $validator->errors()->toArray()], 400);
            }

            $results = [];

            if ($request->currency == "MXN" || $request->currency == "ARS") {
                $acc_number = $request->currency == "MXN" ? getenv("LOCALPAYMENT_MXN_ACC") : getenv("LOCALPAYMENT_ARS_ACC");
                $local = new Localpayments();

                foreach ($request->accounts as $account) {
                    $account_id = generate_uuid();
                    $data = [
                        "externalId" => $account_id,
                        "accountNumber" => $acc_number,
                        "country" => $request->country,
                        "beneficiary" => [
                            "document" => [
                                "id" => $account['beneficiary']['document']['id'],
                                "type" => $account['beneficiary']['document']['type']
                            ],
                            "name" => $account['beneficiary']['name'],
                            "lastname" => $account['beneficiary']['lastname'],
                            "type" => $account['beneficiary']['type']
                        ],
                        "address" => [
                            "city" => $account['address']['city'],
                            "state" => $account['address']['state'],
                            "zipcode" => $account['address']['zipcode'],
                            "street" => $account['address']['street'],
                            "number" => $account['address']['number'],
                            "country" => $account['address']['country']
                        ]
                    ];

                    $curl = $local->bank()->createVirtualAccount($data);
                    if (isset($curl['status']) and (int) $curl['status']['code'] != 100) {
                        $errors = [];
                        foreach ($curl["error"] as $error) {
                            $errors[] = $error;
                        }
                        $results[] = ["account_id" => $account_id, "status" => "error", "errors" => $errors];
                    } else {
                        VirtualAccount::create([
                            "account_id" => $account_id,
                            "user_id" => active_user(),
                            "currency" => $request->currency,
                            "account_info" => [],
                        ]);
                        $results[] = ["account_id" => $account_id, "status" => "success", "data" => $curl['status']];
                        if ($results && $results['success']) {
                            $userId = active_user();
                            $webhook_url = Webhook::whereUserId($userId)->first();
                            WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                                "event.type" => "virtual_account.created",
                                "payload" => $curl
                            ]);
                            return get_success_response($results);
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function delete_virtual_account($account_id)
    {
        try {
            $where = [
                "user_id" => auth()->id(),
                "account_id" => $account_id
            ];

            $accounts = VirtualAccount::where($where)->first();
            if ($accounts->delete()) {
                $userId = active_user();
                $webhook_url = Webhook::whereUserId($userId)->first();
                WebhookCall::create()->meta(['_uid' => $webhook_url->user_id])->url($webhook_url->url)->useSecret($webhook_url->secret)->payload([
                    "event.type" => "virtual_account.deleted",
                    "payload" => $accounts
                ]);
                return get_error_response($accounts);
            }
            return get_error_response(["error" => "Account with the provided ID does not exists"]);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }

    public function enable_disable_virtual_account($action, $account_id)
    {
        try {
            if ($action != "enable" && $action != "disable") {
                return get_error_response(['error' => "Unknow action requested"]);
            }
            $local = new Localpayments;
            $endpoint = "/api/virtual-account/{$account_id}/activation/{$action}";
            $request = $local->curl($endpoint, "PUT");
            if ($request['error']) {
                return get_error_response(['error' => $request['error']]);
            }

            if (isset($request['status']['code']) && $request['status']['code'] == 300 || $request['status']['code'] == 200) {
                return get_success_response(['message' => "virtual account {$action} successfully"]);
            }

            return get_error_response(['error' => 'Please contact support']);
        } catch (\Throwable $th) {
            return ["error" => $th->getMessage()];
        }
    }

    public function refundPayin($externalId)
    {
        try {
            $local = new Localpayments;
            $endpoint = "/api/payin/{$externalId}/refund";
            $request = $local->curl($endpoint, "PATCH");

            if ($request['error']) {
                return get_error_response(['error' => $request['error']]);
            }

            if ($request['status']['code'] == 902) {
                return get_success_response(['message' => $request['status']['detail']]);
            }
        } catch (\Throwable $th) {
            return ["error" => $th->getMessage()];
        }
    }

    public function virtualAccountWebhook(Request $request)
    {
        // Get the raw payload
        $payload = $request->all();
        Log::channel('virtual_account')->error("Wehbook Notification: ", $payload);

        // Extract relevant information
        $transactionType = $request->transactionType;
        $transactionData = (array) $request->data;

        // Process the transaction
        switch (strtolower($transactionType)) {
            case 'payin':
                return $this->processPayin($transactionData);
            case 'virtualaccount':
                return $this->handleVirtualAccountCreation($transactionData);
            default:
                Log::channel('virtual_account')->error("Unsupported transaction type", [strtolower($transactionType)]);
        }

        return http_response_code(200);
    }

    private function processPayin($transactionData)
    {
        // Extract key information
        $externalId = $transactionData['externalId'];
        $currency = $transactionData['currency'];
        $amount = $transactionData['amount'];

        // Check if externalId exists in VirtualAccount Model
        $virtualAccount = VirtualAccount::where('external_id', $externalId)->first();

        if (!$virtualAccount) {
            Log::channel('virtual_account')->error('Virtual account not found' . [$transactionData]);
        }

        // Retrieve the user associated with the virtual account
        $user = User::whereUserId($virtualAccount->user_id)->first();

        if (!$user) {
            Log::channel('virtual_account')->error('User not found', [$virtualAccount]);
        }

        // Get the wallet for the specified currency
        $wallet = $user->getWallet($currency);

        if (!$wallet) {
            // If the user doesn't have a wallet for this currency, create one
            $wallet = $user->createWallet([
                'name' => $currency,
                'slug' => strtolower($currency),
            ]);
        }

        // Credit the wallet
        $wallet->deposit($amount);

        // Log the transaction
        Log::info("Processed PayIn transaction:", [
            'external_id' => $externalId,
            'user_id' => $user->id,
            'currency' => $currency,
            'amount' => $amount,
            'status' => $transactionData['status']['description'],
        ]);

        Log::channel('virtual_account')->error('PayIn processed successfully', [$user, $transactionData]);
    }

    public function handleVirtualAccountCreation($accountData, $isApi = true)
    {
        if ($accountData['currency'] == "USD" or $accountData['currency'] == "EUR" or $accountData['currency'] == "GBP") {
            return $this->handleVirtualAccountCreationForUSD($accountData, $isApi);
        } else if ($accountData['currency'] == "MXN") {
            return $this->handleVirtualAccountCreationForMXN($accountData, $isApi);
        }

        return http_response_code(200);
    }

    private function handleVirtualAccountCreationForUSD($accountData, $isApi = true)
    {
        // Define the endpoint and build the URL
        $endpoint = 'profile/virtual-accounts/'; // Set your endpoint here
        $url = $this->baseUrl . $endpoint . $accountData['external_id'];

        // Make the API request using Laravel's Http facade
        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $this->api_key,
        ])->get($url);

        // Check if the API request was successful
        if ($response->successful()) {
            // Get the response data
            $responseData = $response->json();

            // Check if the API's response indicates success
            if ($responseData['success']) {
                // Extract the relevant data
                $extractedData = $responseData['data'];

                // Retrieve the virtual account record
                $virtualAccount = VirtualAccount::where('account_id', $accountData['_id'])->first();
                $country = Country::where('currency_code', $accountData['currency'])->first();
                // Prepare account information
                $accountInfo = [
                    'country' => $country->iso3, // Set country as Nigeria
                    'currency' => $extractedData['currency'],
                    'account_number' => $extractedData['accountInformation']['accountNumber'],
                    'bank_code' => null, // Set bank code if available
                    'bank_name' => $extractedData['accountInformation']['bankName'],
                    'account_name' => $extractedData['accountInformation']['accountName'],
                ];

                if ($virtualAccount) {
                    $virtualAccount->account_number = $extractedData['accountInformation']['accountNumber'];
                    $virtualAccount->account_info = $accountInfo;
                    $virtualAccount->extra_data = $accountData; // Optionally store original account data
                    $virtualAccount->save();
                } else {
                    return ['error' => "Virtual account not found"];
                }
            } else {
                return ['error' => "Error encountered while retrieving Virtual account"];
            }
        } else {
            return ['error' => "Try again in 5 minutes or contact support if error persists"];
        }
    }

    private function handleVirtualAccountCreationForMXN($accountData, $isApi = true)
    {
        // var_dump($accountData); exit;
        $country = Country::where('currency_code', $accountData['currency'])->first();
        // Extract the required information
        $extractedData = [
            'external_id' => $accountData['externalId'],
            'country' => $country->iso3,
            'currency' => $accountData['currency'],
            'account_number' => $accountData['beneficiary']['bank']['account']['number'],
        ];

        // Extract the first 3 numbers of the account number as bank code
        $extractedData['bank_code'] = Str::substr($extractedData['account_number'], 0, 3);
        if ($extractedData['bank_code'] == "646") {
            $bank_name = "Sistema de Transferencias y Pagos STP";
        } else {
            $bank_name = $this->get_bank_name($extractedData['country'], $extractedData['bank_code']);
        }

        // Log the extracted data
        Log::info('Virtual Account Creation:', $extractedData);
        $virtualAccount = VirtualAccount::where('account_id', $extractedData['external_id'])->first();

        if (!$virtualAccount) {
            Log::channel('virtual_account')->error("Virtual account record not found", $accountData);
            return http_response_code(200);
        } else {
            $accountInfo = [
                'country' => $extractedData['country'],
                'currency' => $extractedData['currency'],
                'account_number' => $extractedData['account_number'],
                'bank_code' => $extractedData['bank_code'],
                'bank_name' => $bank_name,
                'account_name' => $accountData['beneficiary']['fullName'],
            ];
            $virtualAccount->account_number = $extractedData['account_number'];
            $virtualAccount->account_info = $accountInfo;
            $virtualAccount->extra_data = $accountData;
            $virtualAccount->save();
            if ($isApi == false) {
                return $virtualAccount;
            }
            Log::channel('virtual_account')->error("Virtual account creation completed: ", $virtualAccount->toArray());
        }

    }


    /**
     * Summary of get_bank_name
     * @param mixed $bank_country
     * @param mixed $bank_code
     * @return string
     */
    public function get_bank_name($bank_country, $bank_code): string
    {
        try {
            $local = new LocalPaymentsController();
            $results = $local->get_banks__call($bank_country);

            if (!is_array($results)) {
                $results = (array) $results;
            }

            $bank = collect($results)->firstWhere('code', $bank_code);

            return $bank['name'] ?? 'Unknown Bank';
        } catch (\Exception $e) {
            Log::error('Error in get_bank_name: ' . $e->getMessage());
            return $bank['name'] ?? 'Unknown Bank';
        }
    }

    /**
     * Get transaction for an account number
     * @param int $account_number
     * 
     * @return 
     */
    public function get_history($accountNumber)
    {
        try {
            $transactions = localPaymentTransactions::where('account_number', $accountNumber)->paginate(per_page());

            return paginate_yativo($transactions);
        } catch (\Throwable $th) {
            return get_error_response(['error' => $th->getMessage()]);
        }
    }
}
