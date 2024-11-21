<?php

namespace App\Http\Controllers;

use Http;
use Illuminate\Http\Request;
use Modules\Customer\app\Models\Customer;

class TransFiController extends Controller
{
    public $apiKey, $apiSecret, $apiUrl;
    public function __construct()
    {
        $this->middleware('auth');
        $this->apiKey = env("TRANSFI_USERNAME");
        $this->apiSecret = env("TRANSFI_PASSWORD");
        $this->apiUrl = env("IS_TRANSFI_TEST") ? "https://sandbox-api.transfi.com/v2" : "https://sandbox-api.transfi.com/v2";
    }

    public function payin($deposit_id, $amount, $currency)
    {
        if(request()->has('cutomer_id')){
            $customer = Customer::where('customer_id', request()->cutomer_id)->first();
            $name = explode(' ', $customer->customer_name);
            $customer->first_name = $name[0];
            $customer->last_name = $name[1] ?? $name[0];
        }else{
            $customer = request()->user();
        }

        $supported_countries = [
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

        try {
            $country = collect($supported_countries)->first(function ($country) use ($currency) {
                return $country['currency'] === strtoupper($currency);
            });

            if (!$country) {
                return response()->json(['error' => 'Currency not supported. Only EUR is currently accepted.']);
            }

            $response = Http::async()->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Basic '.base64_encode("$this->apiKey:$this->apiSecret"),
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl.'/orders/deposit', [
                        "firstName" => $customer->first_name,,
                        "lastName" => $customer->last_name,
                        "email" => $customer->email ?? $customer->customer_email,
                        "country" => $country['iso2'],
                        "amount" => $amount,
                        "currency" => $currency,
                        "paymentType" => "bank_transfer",
                        "purposeCode" => request()->purposeCode ?? "others",
                        "redirectUrl" => env("WEB_URL"),
                        "type" => "individual",
                        "partnerContext" => [
                            "deposit_id" => $deposit_id,
                            "deposit_amount" => $amount,
                        ],
                        "partnerId" => $deposit_id,
                        "withdrawDetails" => [
                            "cryptoTicker" => "USDT",
                            "walletAddress" => getenv('TRANSFI_WALLET_ADDRESS'),
                        ]
                    ]);

            return $response->wait();

        } catch (\Exception $e) {
            return response()->json(['error' => 'Transaction processing failed: ' . $e->getMessage()]);
        }
    }

    public function payout()
    {
        //
    }

}
