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
            foreach($payoutObj['additionalDetails'] as $key => $value) {
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

    public function kycForm($data)
    {
        try {
            $user = auth()->user();
            if (isset($user->transfi_user_id) && null != $user->transfi_user_id) {
                $transfiUser = $user->transfi_user_id;
            } else {
                $transfiUser = $this->addCustomer($data);
                if (isset($transfiUser['error'])) {
                    return $transfiUser;
                }
                $transfi_user_id = $transfiUser['userId'];
            }

            $response = Http::asMultipart()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
            ])->post(
                    $this->apiUrl . '/kyc/share/third-vendor',
                    [
                        'email' => $data['email'],
                        'idDocExpiryDate' => $data['idExpirationDate'],
                        'idDocUserName' => "{$data['first_name']} {$data['last_name']}",
                        'idDocType' => 'id_card',
                        'idDocFrontSide' => $data['gov_id_image_front'],
                        'idDocBackSide' => $data['gov_id_image_back'],
                        'selfie' => $data['selfieimage'],
                        'gender' => $data['gender'],
                        'phoneNo' => $data['phone'],
                        'idDocIssuerCountry' => $data['gov_id_country'],
                        'street' => $data['address']['street_line_1'],
                        'city' => $data['address']['city'],
                        'state' => $data['address']['state'],
                        'country' => $data['address']['country'],
                        'dob' => $data['dob'],
                        'postalCode' => $data['address']['postal_code'],
                        'firstName' => $data['first_name'],
                        'lastName' => $data['last_name'],
                        'userId' => $transfi_user_id,
                        'nationality' => $data['country']
                    ]
                );

            // Check the response
            if ($response->successful()) {
                return $response->json();
            } else {
                return [
                    'error' => $response->status(),
                    'message' => $response->body()
                ];
            }

        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    private function addCustomer($data)
    {
        try {
            $user = auth()->user();
            $userData = [
                'firstName' => $data['first_name'],
                'lastName' => $data['last_name'],
                'date' => $data['dob'],
                'email' => $data['email'],
                'country' => $data['address']['country'],
                'gender' => $data['gender'],
                'phone' => $data['phone'],
                'address' => [
                    'street' => $data['address']['street_line_1'],
                    'city' => $data['address']['city'],
                    'state' => $data['address']['state'],
                    'postalCode' => $data['address']['postal_code'],
                ],
            ];

            $response = Http::asJson()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
            ])->post("{$this->apiUrl}/users/individual", $userData);

            if ($response->successful()) {
                $result = $response->json();
                $user->update(['transfi_user_id', $result['userId']]);
                return $result;
            } else {
                return [
                    'error' => $response->status(),
                    'message' => $response->body()
                ];
            }
        } catch (\Throwable $th) {
            return [
                'error' => $th->getMessage(),
                'message' => $th->getMessage()
            ];
        }
    }

    public function addBusiness($data)
    {
        try {
            $request = request();
            $user = auth()->user();
            $userData = [
                'businessName' => $request->business_name,
                'email' => $user->email,
                'date' => $request->date_of_birth,
                'country' => $request->country_code,
                'phone' => $request->business_mobile,
                'regNo' => $request->international_number,
                'address' => [
                    'street' => $data['address']['street_line_1'],
                    'city' => $data['address']['city'],
                    'state' => $data['address']['state'],
                    'postalCode' => $data['address']['postal_code'],
                ],
            ];

            $response = Http::asJson()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
            ])->post("{$this->apiUrl}/users/business", $userData);

            if ($response->successful()) {
                $result = $response->json();
                $user->update(['transfi_user_id', $result['userId']]);
                return $result;
            } else {
                return [
                    'error' => $response->status(),
                    'message' => $response->body()
                ];
            }
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage(), 'message' => $th->getMessage()];
        }
    }
}
