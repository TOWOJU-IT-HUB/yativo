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
        $this->baseUrl = env('FINCRA_BASE_URL');
        $this->api_key = env('FINCRA_API_KEY');
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

        // Retrieve country information based on the currency code
        $extractedData = $payload['data'];

        // Retrieve the virtual account record
        $virtualAccount = new VirtualAccount();
        $country = Country::where('currency_code', $extractedData['currency'])->first();
        // Prepare account information
        $accountInfo = [
            'country' => $country->iso3, // Set country as Nigeria
            'currency' => $extractedData['currency'],
            'account_number' => $extractedData['accountInformation']['accountNumber'],
            'bank_code' => $extractedData['accountInformation']['bankCode'],
            'bank_name' => $extractedData['accountInformation']['bankName'],
            'account_name' => $extractedData['accountInformation']['accountName'],
        ];

        $virtualAccount->account_number = $extractedData['accountInformation']['accountNumber'];
        $virtualAccount->account_info = $accountInfo;
        $virtualAccount->extra_data = $extractedData; // Optionally store original account data from provider
        $virtualAccount->save();
            
        return response()->json(['message' => 'Virtual account updated successfully'], 200);
    }
}