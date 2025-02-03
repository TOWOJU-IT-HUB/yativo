<?php

namespace Modules\Customer\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Modules\Customer\app\Models\CustomerVirtualAccount;
use Towoju5\Localpayments\Localpayments;

class CustomerVirtualAccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('scale');
    }

    public function initAccountCreation(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                "customer_id" => "sometimes|string",
                "document_id" => "required|string",
                "document_type" => "required|string",
                "currency" => "required|string|in:MEX,ARS,BRL"
            ]);

            if ($validate->fails()) {
                return get_error_response($validate->errors(), 400);
            }

            $business = Business::whereUserId(auth()->id())->first();

            if (!$business) {
                return get_error_response(["error" => "Please verify you're a business and your business has been verified"]);
            }

            if (strtolower(($request->currency) == "ARS") && !in_array($request->document_type, ["CUIT", "CUIL", "CDI"])) {
                return get_error_response(['error' => "Sorry your document type must be one of the following:  CUIT, CUIL, CDI"]);
            }

            $customer = getCustomerById($request->customer_id);

            if (!$customer) {
                return ['error' => "Invalid customer ID"];
            }

            switch ($request->currency) {
                case 'MEX':
                    $payload = self::createMxnAccount((array) $request, (array) $customer);
                    break;

                case 'ARS':
                    $payload = self::createArsAccount((array) $request, (array) $customer);
                    break;

                case 'BRL':
                    $payload = self::createBrlAccount((array) $request, (array) $customer);
                    return get_error_response(['error' => "Please contact support to create a brazilian account."]);
                    break;

                default:
                    return get_error_response(['error', "We're currently unable to process your request, please contact support"]);
                    break;
            }

            if ($payload) {
                // Local payment endpoint = /api/virtual-account
                $endpoint = "/api/virtual-account";
                $local = new Localpayments();
                $curl = $local->curl($endpoint, "POST", $payload);
                if (isset($curl['status']) && isset($curl['status']['description'])) {
                    $status = $curl['status']['description'];
                    if (strtoupper($status) == "INPROGRESS") {
                        $retriveRecord = $this->getVirtualAccounts($customer, $request->currency, $payload['externalId'], $business);
                        return get_success_response($retriveRecord);
                    }
                }

                if (isset($curl['error']))
                    return get_error_response(['error' => $curl['error']]);

                return get_error_response(['error' => "We're currently unable to process your request, please contact support"]);
            }
        } catch (\Throwable $th) {
            if(env('APP_ENV') == 'local') {
                return get_error_response(['error' => $th->getMessage()]);
            }
            return get_error_response(['error' => 'Something went wrong, please try again later']);
        }
    }

    /**
     * @param array $data
     */
    private function createArsAccount(array $data, array $customer)
    {
        $name = explode(" ", $customer['customer_name']);
        $arr = [
            "externalId" => generate_uuid(),
            "accountNumber" => getenv("LOCALPAYMENT_ARS_ACC"), // yativo localpayment account Number
            "country" => "ARG",
            "currency" => "ARS",
            "beneficiary" => [
                "document" => [
                    "id" => $data['document_id'],
                    "type" => $data['document_type']
                ],
                "name" => $name[0],
                "lastname" => $name[1] ?? $name[0],
                "type" => "INDIVIDUAL"
            ],
            "address" => [
                "city" => $customer["customer_address"]["city"],
                "state" => $customer["customer_address"]["state"],
                "zipcode" => $customer["customer_address"]["zipcode"],
                "street" => $customer["customer_address"]["street"],
                "number" => $customer["customer_address"]["number"],
                "country" => $customer["customer_address"]["country"]
            ]
        ];

        return $arr;
    }

    private function createMxnAccount(array $data, array $customer)
    {
        $name = explode(" ", $customer['customer_name']);

        $arr = [
            "externalId" => generate_uuid(),
            "accountNumber" => getenv("LOCALPAYMENT_MXN_ACC"), // yativo localpayment account Number
            "country" => "MXN",
            "currency" => "MEX",
            "beneficiary" => [
                "document" => [
                    "id" => $data['document_id'],
                    "type" => $data['document_type']
                ],
                "name" => $name[0],
                "lastname" => $name[1] ?? $name[0],
                "type" => "INDIVIDUAL"
            ],
            "address" => [
                "city" => $customer["customer_address"]["city"],
                "state" => $customer["customer_address"]["state"],
                "zipcode" => $customer["customer_address"]["zipcode"],
                "street" => $customer["customer_address"]["street"],
                "number" => $customer["customer_address"]["number"],
                "country" => $customer["customer_address"]["country"]
            ]
        ];

        return $arr;
    }

    private function createBrlAccount(array $data, array $customer)
    {
        $name = explode(" ", $customer['customer_name']);
        $arr = [
            "externalId" => generate_uuid(),
            "accountNumber" => "{{YourAccountNumber}}", // yativo localpayment account Number
            "country" => "ARG",
            "beneficiary" => [
                "document" => [
                    "id" => $data['document_id'],
                    "type" => $data['document_type']
                ],
                "name" => $name[0],
                "lastname" => $name[1] ?? $name[0],
                "type" => "INDIVIDUAL"
            ],
            "address" => [
                "city" => $customer["customer_address"]["city"],
                "state" => $customer["customer_address"]["state"],
                "zipcode" => $customer["customer_address"]["zipcode"],
                "street" => $customer["customer_address"]["street"],
                "number" => $customer["customer_address"]["number"],
                "country" => $customer["customer_address"]["country"]
            ]
        ];

        return $arr;
    }

    /**
     * This endpoint allows you to create multiple virtual accounts at the same time.
     */
    public function getVirtualAccounts($customer, $currency, $externalId, $business)
    {
        try {
            $endpoint = "/api/virtual-account/{$externalId}";
            $local = new Localpayments();
            $curl = $local->curl($endpoint, "GET");
            if (isset($curl['status']) && isset($curl['status']['description'])) {
                $status = $curl['status']['description'];
                if (strtoupper($status) == "COMPLETED") {
                    // store the virtual account in the database
                    $account = CustomerVirtualAccount::updateOrCreate(["externalId" => $externalId], [
                        "business_id" => $business->id,
                        "externalId" => $externalId,
                        "currency" => $currency,
                        "customer_id" => $customer->id,
                        "account_info" => $curl["beneficiary"],
                        "account_status" => $curl['status']['description'],
                        "meta_data" => $curl
                    ]);
                    return get_success_response([
                        "accounts" => $account
                    ]);
                } elseif (strtoupper($status) == "INPROGRESS" or strtoupper($status) == "IN_PROGRESS") {
                    $account = CustomerVirtualAccount::create([
                        "business_id" => $business->id,
                        "externalId" => $externalId,
                        "currency" => $currency,
                        "customer_id" => $customer->id,
                        "account_info" => [],
                        "account_status" => $curl['status']['description'],
                        "meta_data" => $curl
                    ]);
                    return get_success_response([
                        'message' => $account
                    ]);
                } else {
                    return get_error_response(['error' => $curl['status']['detail']]);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
