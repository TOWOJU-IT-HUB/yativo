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
                'redirect_url' => request()->redirect_url ?? route('floid.callback.redirect'),
                'webhook_url' => route('floid.callback.success'),
                // 'sandbox' => env("FLOID_SANDBOX", false),
            ];

            $response = Http::withToken($authToken)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $requestData);

            $result = $response->json();

            if (isset($result['payment_url']) && isset($result['payment_token'])) {
                update_deposit_gateway_id($quoteId, $result['payment_token']);
                return $result;
            }
            return ["error" => $result];
        } catch (\Throwable $e) {
            return ["error" => $e->getMessage()];
        }
    }

    public function getChlPaymentStatus($token = null)
    {
        $request = request();
        $url = "https://api.floid.app/cl/payments/check";
        $token = $token ?? $request->payment_token;
        $result = $this->getPaymentStatus($url, $token);
        // Log::info("Floid request and response data", ['request' => $request->getContent()]);
        if (isset($result['status'])) {
            return $result;
        }
        return ["error" => $result];
    }

    public function getPenPaymentStatus($token = null)
    {
        $request = request();
        // Log::info("Floid request and response data", ['request' => $request->getContent()]);

        $url = "https://api.floid.app/pe/payments/check";

        $token = $token ?? $request->payment_token;

        $result = $this->getPaymentStatus($url, $token);

        if (isset($result['status'])) {
            return $result;
        }
        return ["error" => $result];
    }

    public function getPaymentStatus($url, $payment_token)
    {
        $request = request();
        // Log::info("Floid request and response data", ['request' => $payment_token]);

        $authToken = env("FLOID_AUTH_TOKEN");

        $response = Http::withToken($authToken)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, [
                    'payment_token' => $payment_token
                ]);

        $result = $response->json();
        Log::info('Direct status from floid - getPaymentStatus: ', ['getPaymentStatus' => $result]);
        return $result;
    }

    public function callback(Request $request)
    {
        $rawInput = file_get_contents('php://input');
        $requestBody = $request->all();
        Log::info('Floid callback request body:', $requestBody);
        if(isset($request->id) && !empty($request->id)) {
            $deposit = Deposit::where('gateway_deposit_id', $request->id)->first();
            if($deposit) {
                // get the deposit then process it.
                if(strtoupper($deposit->currency) == "PEN") {
                    return $this->getPenPaymentStatus($request->id);
                } else if(strtoupper($deposit->currency) == "CLP") {
                    return $this->getChlPaymentStatus($request->id);
                } else {
                    //
                }
            }

            return rediret()->away('https://app.yativo.com');
        }
    }
}
