<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Deposit;
use App\Models\TransactionRecord;
use App\Services\DepositService;
use Illuminate\Http\Request;
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
        $this->supported_countries = [
            ["country" => "Austria", "iso2" => "AT", "currency" => "EUR"],
            ["country" => "Belgium", "iso2" => "BE", "currency" => "EUR"],
            ["country" => "Cyprus", "iso2" => "CY", "currency" => "EUR"],
            ["country" => "Estonia", "iso2" => "EE", "currency" => "EUR"],
            ["country" => "Finland", "iso2" => "FI", "currency" => "EUR"],
            ["country" => "France", "iso2" => "FR", "currency" => "EUR"],
            ["country" => "Germany", "iso2" => "DE", "currency" => "EUR"],
            ["country" => "Greece", "iso2" => "GR", "currency" => "EUR"],
            ["country" => "Ireland", "iso2" => "IE", "currency" => "EUR"],
            ["country" => "Italy", "iso2" => "IT", "currency" => "EUR"],
            ["country" => "Latvia", "iso2" => "LV", "currency" => "EUR"],
            ["country" => "Lithuania", "iso2" => "LT", "currency" => "EUR"],
            ["country" => "Luxembourg", "iso2" => "LU", "currency" => "EUR"],
            ["country" => "Malta", "iso2" => "MT", "currency" => "EUR"],
            ["country" => "Netherlands", "iso2" => "NL", "currency" => "EUR"],
            ["country" => "Portugal", "iso2" => "PT", "currency" => "EUR"],
            ["country" => "Slovakia", "iso2" => "SK", "currency" => "EUR"],
            ["country" => "Slovenia", "iso2" => "SI", "currency" => "EUR"],
            ["country" => "Spain", "iso2" => "ES", "currency" => "EUR"]
        ];
    }

    public function payin($deposit_id, $amount, $currency, $txn_type, $gateway)
    {
        $customer = $this->getCustomerInfo();
        $get_country = Country::where('iso3', $gateway->country)->first();

        try {
            $country = collect($this->supported_countries)->first(function ($country) use ($currency, $get_country) {
                return $country['iso2'] === strtoupper($get_country->iso2);
            });

            if (!$country) {
                return ['error' => 'Currency not supported. Only EUR is currently accepted.'];
            }

            $response = Http::async()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode("$this->apiKey:$this->apiSecret"),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/orders/deposit', [
                        "firstName" => $customer->first_name,
                        "lastName" => $customer->last_name,
                        "email" => $customer->email ?? $customer->customer_email,
                        "country" => $country['iso2'],
                        "amount" => $amount,
                        "currency" => $currency,
                        "paymentType" => "bank_transfer", // available in the gateway data.
                        "purposeCode" => request()->purposeCode ?? "others",
                        "redirectUrl" => env("WEB_URL"),
                        "type" => "individual",
                        "partnerContext" => [
                            "deposit_id" => $deposit_id,
                            "deposit_amount" => $amount,
                            "order_type" => "deposit"
                        ],
                        "partnerId" => $deposit_id,
                        "withdrawDetails" => [
                            "cryptoTicker" => env('TRANSFI_WALLET_TICKER', "USDT"),
                            "walletAddress" => env('TRANSFI_WALLET_ADDRESS'),
                        ]
                    ]);
            $result = $response->json();
            update_deposit_gateway_id($deposit_id, $result['orderId']);
            return $response->wait();

        } catch (\Exception $e) {
            return ['error' => 'Transaction processing failed: ' . $e->getMessage()];
        }
    }

    public function payout($payoutId, $amount, $currency, $payoutObj)
    {
        $customer = $this->getCustomerInfo();

        try {
            $payload = [
                "email" => $customer->customer_email ?? $customer->email,
                "currency" => strtoupper($currency),
                "amount" => $amount,
                "paymentCode" => $payoutObj['paymentCode'], // available in the gateway info
                "paymentAccountNumber" => $payoutObj['paymentAccountNumber'],
                "purposeCode" => $payoutObj['purposeCode'] ?? "others",
                "partnerContext" => [
                    "payout_id" => $payoutId,
                    "payout_amount" => $amount,
                    "order_type" => "payout",
                    "payout_object" => $payoutObj
                ],
                "additionalDetails" => $payoutObj['additionalDetails'] ?? [], // available in the gateway info - extra_data
                "partnerId" => $payoutId,
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
}
