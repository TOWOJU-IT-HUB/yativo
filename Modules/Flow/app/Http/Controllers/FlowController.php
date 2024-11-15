<?php

namespace Modules\Flow\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use Http;
use Illuminate\Http\Request;
use Log;
use Modules\Flow\app\Services\FlowServices;
use Modules\SendMoney\app\Models\SendMoney;


class FlowController extends Controller
{
    public function makePayment($quoteId, $amount, $currency)
    {
        try {
            if ($currency == 'CLP') {
                $url = "https://api.floid.app/cl/payments/create";
            } else if ($currency == "PEN" || $currency == "USD") {
                $url = "https://api.floid.app/pe/payments/create";
            } else {
                return ['error' => "Unsupported currency selected"];
            }

            $authToken = env("FLOID_AUTH_TOKEN");

            $requestData = [
                'quote_id' => $quoteId,
                'custom' => $quoteId,
                'amount' => $amount,
                'redirect_url' => route('floid.callback.redirect'),
                'webhook_url' => route('floid.callback.success'),
                'sandbox' => env("FLOID_SANDBOX", false),
            ];

            $response = Http::withToken($authToken)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $requestData);

            $result = $response->json();

            // Log::info("Floid request and response data", ['request' => $requestData, "response" => $result, "url" => $url, "currency" => $currency]);

            if (isset($result['payment_url']) && isset($result['payment_token'])) {
                update_deposit_gateway_id($quoteId, $result['payment_token']);
                return $result;
            }
            return ["error" => $result];
        } catch (\Throwable $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function getChlPaymentStatus(Request $request)
    {
        $url = "https://api.floid.app/cl/payments/check";

        $authToken = env("FLOID_AUTH_TOKEN");

        $response = Http::withToken($authToken)->withHeaders([
            'Content-Type' => 'application/json',
        ])->get($url, [
                    'payment_token' => "d99512d1-9187-44c7-b2e6-e2ff75e5cc60"
                ]);

        $result = $response->json();

        if (isset($result['payment_url'])) {
            return $result;
        }
        return ["error" => $result];
    }

    public function getPenPaymentStatus(Request $request)
    {
        $url = "https://api.floid.app/cl/payments/check";

        $result = $this->getPaymentStatus($url, $request->payment_token ?? "d99512d1-9187-44c7-b2e6-e2ff75e5cc60");

        if (isset($result['payment_url'])) {
            return $result;
        }
        return ["error" => $result];
    }

    private function getPaymentStatus($url, $payment_token)
    {
        $authToken = env("FLOID_AUTH_TOKEN");

        $response = Http::withToken($authToken)->withHeaders([
            'Content-Type' => 'application/json',
        ])->get($url, [
                    'payment_token' => $payment_token
                ]);

        $result = $response->json();
        return $result;
    }

    public function callback(Request $request)
    {
        //
    }
}
