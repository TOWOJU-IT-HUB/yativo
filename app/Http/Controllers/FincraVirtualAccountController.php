<?php

namespace App\Http\Controllers;

use App\Models\Business\VirtualAccount;
use App\Models\Country;
use Http;
use Illuminate\Http\Request;
use Log;
use Modules\Customer\app\Models\Customer;
use Str;
use Validator;

class FincraVirtualAccountController extends Controller
{
    public $baseUrl, $api_key;

    public function __construct()
    {
        $this->baseUrl = env('FINCRA_BASE_URL', 'https://sandboxapi.fincra.com/');
        $this->api_key = env('FINCRA_API_KEY', '8G5hwaiw7oy9q8tCBJ6X1ltp5C20QDwJ');
    }


    // Create a new virtual account
    public function createVirtualAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currency' => 'required|string|in:USD,EUR',
            'utilityBill' => 'required|string',
            'bankStatement' => 'required|string',
            'sourceOfIncome' => 'required|string',
            'occupation' => 'required|string',
            'employmentStatus' => 'required|string',
            'incomeBand' => 'required|string',
            'birthDate' => 'required|string',
            'nationality' => 'required|string',
            'meansOfId' => 'required|string',
            'customer_id' => 'required|string',
        ]);
        $customer = Customer::whereCustomerId($request->customer_id)->first();
        $customer_name = explode(' ', $customer->customer_name);
        $account_id = generate_uuid();

        $country = Country::where('name', $customer->customer_country)->first();

        $response = Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            "api-key: {$this->api_key}",
        ])->post("{$this->baseUrl}profile/virtual-accounts/requests", [
                    "currency" => $request->currency,
                    "accountType" => "individual",
                    "utilityBill" => $request->utilityBill,
                    "bankStatement" => $request->bankStatement,
                    "KYCInformation" => [
                        "address" => [
                            "state" => "State",
                            "city" => "City",
                            "street" => "Full Street Address",
                            "zip" => "100020",
                            "countryOfResidence" => "NG",
                            "number" => "25"
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
                        "nationality" => $country->iso2,
                        "document" => [
                            "type" => $customer->customer_idType,
                            "number" => $customer->customer_idNumber,
                            "issuedCountryCode" => $country->iso2,
                            "issuedBy" => "government",
                            "issuedDate" => "2017-09-07",
                            "expirationDate" => $customer->customer_idExpiration
                        ],
                    ],
                    "meansOfId" => $request->meansOfId,
                    "merchantReference" => $account_id
                ])->json();

        if (!is_array($response)) {
            $response = json_decode($response, true);
        }

        if (isset($response['status']) and (int) $response['status']['code'] != 100) {  
            foreach ($response["error"] as $error) {
                $errors[] = $error;
            }
            return get_error_response(["error" => $errors]);
        }

        $record = VirtualAccount::create([
            "account_id" => $account_id,
            "user_id" => active_user(),
            "currency" => $request->currency,
            "request_object" => $validator->validated(),
            "customer_id" => $request->customer_id
        ]);

        return get_success_response($record);
    }


    // Fetch a specific multicurrency account information
    public function getVirtualAccount($virtualAccountId)
    {
        $response = Http::get("{$this->baseUrl}profile/virtual-accounts/{$virtualAccountId}")->json();

        return get_success_response($response);
    }

    // Get account deposit history
    public function getAccountDepositHistory($businessId, $virtualAccountId)
    {
        $response = Http::get("{$this->baseUrl}/collections", [
            'business' => $businessId,
            'virtualAccount' => $virtualAccountId
        ])->json();

        return get_success_response($response);
    }

    // List all multicurrency accounts
    public function listMulticurrencyAccounts(Request $request)
    {
        $currency = $request->query('currency', '');
        $response = Http::get("{$this->baseUrl}profile/virtual-accounts", [
            'currency' => $currency
        ])->json();

        return get_success_response($response);
    }

    public function handleVirtualAccountApproved(Request $request)
    {
        $payload = $request->all();
        
        // Ensure the event is 'virtualaccount.approved'
        if ($payload['event'] !== 'virtualaccount.approved') {
            return get_error_response(['message' => 'Event not supported'], 400);
        }

        // Extract account data from the payload
        $accountData = $payload['data'];

        // Retrieve country information based on the currency code
        $country = Country::where('currency_code', $accountData['currency'])->first();
        
        if (!$country) {
            Log::error("Country not found for currency: " . $accountData['currency']);
            return get_error_response(['message' => 'Invalid currency code'], 400);
        }

        // Extract the required information
        $extractedData = [
            'external_id' => $accountData['id'],
            'country' => $country->iso3,
            'currency' => $accountData['currency'],
            'account_number' => $accountData['accountInformation']['accountNumber'],
        ];

        // Extract the first 3 numbers of the account number as bank code
        $extractedData['bank_code'] = Str::substr($extractedData['account_number'], 0, 3);
        
        // Determine the bank name based on bank code
        if ($extractedData['bank_code'] == "646") {
            $bank_name = "Sistema de Transferencias y Pagos STP";
        } else {
            $bank_name = $this->getBankName($extractedData['country'], $extractedData['bank_code']);
        }

        // Log the extracted data
        Log::info('Virtual Account Creation:', $extractedData);

        // Find the virtual account using the external ID
        $virtualAccount = VirtualAccount::where('account_id', $extractedData['external_id'])->first();

        if (!$virtualAccount) {
            Log::channel('virtual_account')->error("Virtual account record not found", $accountData);
            return response()->json(['message' => 'Virtual account not found'], 404);
        }

        // Prepare account information
        $accountInfo = [
            'country' => $extractedData['country'],
            'currency' => $extractedData['currency'],
            'account_number' => $extractedData['account_number'],
            'bank_code' => $extractedData['bank_code'],
            'bank_name' => $bank_name,
        ];

        // Update virtual account with new information
        $virtualAccount->account_number = $extractedData['account_number'];
        $virtualAccount->account_info = $accountInfo;
        $virtualAccount->save();

        // Log the completion of virtual account creation
        Log::channel('virtual_account')->info("Virtual account creation completed: ", $virtualAccount->toArray());

        return response()->json(['message' => 'Virtual account updated successfully'], 200);
    }

    // A helper function to get the bank name based on the country and bank code
    private function getBankName($countryCode, $bankCode)
    {
        // Implement the logic to retrieve the bank name based on the country and bank code
        // This can be a lookup table or an API call to a banking service
        // For now, we'll return a generic name for demonstration purposes

        return 'Generic Bank Name';
    }
}