<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use App\Services\DepositService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Customer\app\Models\Customer;

class TransFiController extends Controller
{
    public $apiKey, $apiSecret, $apiUrl, $supported_countries;

    public function __construct()
    {
        $this->middleware('auth');
        $this->apiKey = env("TRANSFI_USERNAME");
        $this->apiSecret = env("TRANSFI_PASSWORD");
        $this->apiUrl = env("IS_TRANSFI_TEST") ? "https://sandbox-api.transfi.com/v2" : "https://api.transfi.com/v2";
        $this->supported_countries = ['*'];
    }

    public function payin($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $customer = $this->getCustomerInfo();

        // Ensure $gateway->country is valid and retrieve country information
        $get_country = Country::where('iso3', $gateway->country)->first();

        if (!$get_country) {
            return ['error' => 'Invalid country code provided in gateway data.'];
        }

        try {

            $data = [
                "firstName" => $customer->first_name,
                "lastName" => $customer->last_name,
                "email" => $customer->email ?? $customer->customer_email,
                "country" => $get_country['iso2'],
                "amount" => ceil($amount),
                "currency" => $currency,
                "paymentType" => "bank_transfer",
                "purposeCode" => request()->purposeCode ?? "other",
                "redirectUrl" => env("WEB_URL"),
                "type" => "individual",
                "partnerContext" => [
                    "deposit_id" => $deposit_id,
                    "deposit_amount" => ceil($amount),
                    "order_type" => "deposit"
                ],
                "partnerId" => $deposit_id,
                "withdrawDetails" => [
                    "cryptoTicker" => env('TRANSFI_WALLET_TICKER', "USDT"),
                    "walletAddress" => env('TRANSFI_WALLET_ADDRESS', "0x59a8f26552CaF6ea7F669872bf39443d8d0eFB96"),
                ]
            ];

            //    echo json_encode($data, JSON_PRETTY_PRINT); exit;

            // Make the API request
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/orders/deposit', $data);

            // Wait for the response and decode the result
            $result = $response->json();

            // var_dump($result); exit;

            // Ensure the response contains an orderId
            if (!isset($result['orderId'])) {
                return ['error' => 'Failed to process the transaction. Missing order ID in response.'];
            }

            // Update deposit gateway ID
            update_deposit_gateway_id($deposit_id, $result['orderId']);

            return $result;
        } catch (\Exception $e) {
            return ['error' => 'Transaction processing failed: ' . $e->getMessage()];
        }
    }


    public function payout($payoutId, $amount, $currency, $payoutObj)
    {
        $customer = $this->getCustomerInfo();
        try {
            $additionalDetails = [];
            foreach ($payoutObj['additionalDetails'] as $key => $value) {
                $additionalDetails[$key] = $value;
            }

            $payload = [
                "email" => $customer->customer_email ?? $customer->email,
                "currency" => strtoupper($currency),
                "amount" => $amount,
                "paymentCode" => $payoutObj['paymentCode'],
                "paymentAccountNumber" => $payoutObj['paymentAccountNumber'],
                "purposeCode" => $payoutObj['purposeCode'] ?? "other",
                "partnerContext" => [
                    "payout_id" => $payoutId,
                    "payout_amount" => $amount,
                    "order_type" => "payout",
                    "payout_object" => [
                        "additionalDetails" => $additionalDetails
                    ]
                ],
                "additionalDetails" => $additionalDetails,
                "partnerId" => $payoutId,
                "depositDetails" => [
                    "cryptoTicker" => "USDTBSC"
                ]
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/payout/orders', $payload);

            if ($response->successful()) {
                return $response->json();
            } else {
                return [
                    'error' => $response->json(),
                ];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function getOrderDetails($orderId)
    {
        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
                'Content-Type' => 'application/json',
            ])->get($this->apiUrl . '/orders/' . $orderId);

            if ($response->successful()) {
                return $response->json();
            } else {
                return [
                    'error' => $response->json(),
                ];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function payinRedirectPage(Request $request, $depositId)
    {
        // patch code to verify the payment and update the deposit status
        $deposit = Deposit::whereId($depositId)->orWhere('deposit_id', $depositId)->first();
        if ($deposit) {
            $where = [
                "transaction_memo" => "payin",
                "transaction_id" => $depositId
            ];
            $order = TransactionRecord::where($where)->first();
            if ($order) {
                $deposit_services = new DepositService();
                $deposit_services->process_deposit($order->transaction_id);
            }
        }
        return redirect()->to(env('WEB_URL'));
    }

    private function getCustomerInfo()
    {
        if (request()->has('customer_id')) {
            $customer = Customer::where('customer_id', request()->customer_id)->first();
            $name = explode(' ', $customer->customer_name);
            $customer->first_name = $name[0];
            $customer->last_name = $name[1] ?? $name[0];
            return $customer;
        } else {
            $user = request()->user();
            $name = explode(' ', $user->name);
            $user->first_name = $name[0];
            $user->last_name = $name[1] ?? $name[0];
            return $user;
        }
    }

    public function kycForm($request)
    {
        try {
            $user = auth()->user();
            $transfi_user_id = $user->transfi_user_id ?? $this->processUserType($request);

            if (isset($transfi_user_id['error'])) {
                return $transfi_user_id;
            }

            $response = Http::asMultipart()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
            ])->post(
                $this->apiUrl . '/kyc/share/third-vendor',
                [
                    'email' => $request->email,
                    'idDocExpiryDate' => $request->identifying_information[0]['number'] ?? null,
                    'idDocUserName' => "{$request->first_name} {$request->last_name}",
                    'idDocType' => $request->identifying_information[0]['type'] ?? 'id_card',
                    'idDocFrontSide' => MiscController::uploadBase64ImageToCloudflare($request->documents[0]['file'] ?? null),
                    'idDocBackSide' => MiscController::uploadBase64ImageToCloudflare($request->documents[1]['file'] ?? null),
                    'selfie' => MiscController::uploadBase64ImageToCloudflare($request->selfieimage),
                    'gender' => $request->gender ?? null,
                    'phoneNo' => $request->phone ?? null,
                    'idDocIssuerCountry' => $request->identifying_information[0]['issuing_country'] ?? null,
                    'street' => $request->address['street_line_1'],
                    'city' => $request->address['city'],
                    'state' => $request->address['subdivision'] ?? null,
                    'country' => $request->address['country'],
                    'dob' => $request->birth_date,
                    'postalCode' => $request->address['postal_code'],
                    'firstName' => $request->first_name,
                    'lastName' => $request->last_name,
                    'userId' => $transfi_user_id,
                    'nationality' => $request->address['country'],
                ]
            );

            return $response->successful() ? $response->json() : [
                'error' => $response->status(),
                'message' => $response->body(),
            ];
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(), 'message' => $th->getMessage()];
        }
    }

    private function addCustomer($request)
    {
        try {
            $user = auth()->user();
            $userData = [
                'firstName' => $request->first_name,
                'lastName' => $request->last_name,
                'date' => $request->birth_date,
                'email' => $request->email,
                'country' => $request->address['country'],
                'gender' => $request->gender ?? null,
                'phone' => $request->phone ?? null,
                'address' => [
                    'street' => $request->address['street_line_1'],
                    'city' => $request->address['city'],
                    'state' => $request->address['subdivision'] ?? null,
                    'postalCode' => $request->address['postal_code'],
                ],
            ];

            $response = Http::asJson()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
            ])->post("{$this->apiUrl}/users/individual", $userData);

            if ($response->successful()) {
                $result = $response->json();
                $user->update(['transfi_user_id' => $result['userId']]);
                return $result['userId'];
            } else {
                return [
                    'error' => $response->status(),
                    'message' => $response->body(),
                ];
            }
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(), 'message' => $th->getMessage()];
        }
    }

    public function addBusiness($request)
    {
        try {
            $user = auth()->user();
            $userData = [
                'businessName' => $request->business_legal_name,
                'email' => $request->email,
                'country' => $request->registered_address['country'],
                'phone' => $request->business_mobile,
                'regNo' => $request->business_type,
                'address' => [
                    'street' => $request->registered_address['street_line_1'],
                    'city' => $request->registered_address['city'],
                    'state' => $request->registered_address['subdivision'] ?? null,
                    'postalCode' => $request->registered_address['postal_code'],
                ],
            ];

            $response = Http::asJson()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
            ])->post("{$this->apiUrl}/users/business", $userData);

            if ($response->successful()) {
                $result = $response->json();
                $user->update(['transfi_user_id' => $result['userId']]);
                return $result;
            } else {
                return [
                    'error' => $response->status(),
                    'message' => $response->body(),
                ];
            }
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(), 'message' => $th->getMessage()];
        }
    }


    private function processUserType($request)
    {
        if ($request->type == "business") {
            return $this->addBusiness($request);
        }

        return $this->addCustomer($request);
    }

    public function processWebhook(Request $request)
    {
        // Extract the incoming webhook data from the request
        $data = $request->json()->all(); // This will be a single event object

        // Extract order and user details from the data
        $order = $data['order'];
        $user = $data['user'];
        $status = $data['status']; // "initiated", "fund_settled", or "fund_failed"

        // Prepare the response data with relevant details
        $responseData = [
            'type' => $order['type'],
            'status' => $status,
            'amount' => $order['fiatAmount'],
            'orderId' => $order['orderId'],
            'paymentType' => $order['paymentType'],
            'user' => [
                'userId' => $user['userId'],
                'firstName' => $user['firstName'],
                'lastName' => $user['lastName'],
                'country' => $user['country']
            ],
            'orderDetails' => $order // Full order information
        ];

        // if event type is deposit then call the right class to complete the deposit
        if (isset($responseData['status']) && $responseData['status'] === "fund_settled") {
            $where = [
                "transaction_memo" => "payin",
                "transaction_id" => $deposit->deposit_id
            ];
            $order = TransactionRecord::where($where)->first();
            if ($order) {
                $deposit_services = new DepositService();
                $deposit_services->process_deposit($order->transaction_id);
                $this->updateTracking($deposit->id, $verify['data']['status'], $verify);
            }
        }


        // if event type is payout call the right class to complete the payout

        // Return the response as JSON
        return response()->json($responseData);
    }
}
