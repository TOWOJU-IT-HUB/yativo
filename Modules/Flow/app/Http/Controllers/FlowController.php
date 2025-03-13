<?php

namespace Modules\Flow\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\payoutMethods;
use Http;
use Illuminate\Http\Request;
use Log;
use Modules\Flow\app\Services\FlowServices;
use Modules\SendMoney\app\Models\SendMoney;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;


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

            var_dump($requestData); exit;

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


    /**
     * Payout code
     */

    public function payout($payload, $amount, $currency)
    {
        try{
            if ($currency == 'CLP') {
                $url = "https://api.floid.app/cl/payout/create";
            } else if ($currency == "PEN" || $currency == "USD") {
                $url = "https://api.floid.app/pe/payout/create";
            } else {
                return ['error' => "Unsupported currency selected"];
            }

            // var_dump($payload); exit;

            $beneficiaryId = $payload->beneficiary_id;
            $model = new BeneficiaryPaymentMethod();
            $ben = [];
            $data= $model->getBeneficiaryPaymentMethod($beneficiaryId);
            // var_dump($ben); exit;

            if($data) {
                $ben = $data->payment_data;
            }

            if (!$ben) {
                return ['error' => 'Beneficiary not found'];
            }
            $gateway = payoutMethods::whereId($data->gateway_id)->first();

            if (!$gateway) {
                return ['error' => 'Gateway not found'];
            }

            $authToken = env("FLOID_AUTH_TOKEN");
            
            // var_dump([$authToken]); exit;
            // $rate = getExchangeVal($gateway->currency, "CLP");
            // Log::debug("Processing payout requests", ['payload' => $payload]);
            $requestData = [
                "beneficiary_id" => $ben['beneficiary_id'],
                "beneficiary_name" => $ben['beneficiary_name'],
                "beneficiary_account" => $ben['beneficiary_account'],
                "amount" => floatval($payload->customer_receive_amount),
                "beneficiary_account_type" => $ben['beneficiary_account_type'],
                "beneficiary_bank_code" => $ben['beneficiary_bank_code'],            
                "beneficiary_email" => $ben['beneficiary_email'],
                "description" => $ben['description']
            ];

            // if(request()->has('debug')) {
                // dd(['incoming_payload' => $payload,'curl_payload' => $requestData]); exit;
            // }
            // var_dump($requestData); exit;
            $response = Http::withToken($authToken)->withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $requestData);

            $result = $response->json();
            if(isset($result["status"]) && strtoupper($result["status"]) === "SUCCESSFUL") {
                $payout = $payload;
                $payout->status = "complete";
                $payout->save();
                mark_payout_completed($payload->id, $payload->payout_id);
            }
            var_dump($result); exit;
            if(isset($result) && is_array($result) && strtolower($result['status']) == "error" || isset($result['code']) && $result['code'] == 400) {
                $error = $result['data']['error_message'] ?? $result['error_message'];
                return ['error' => $error];
            }
            return $result;
            
        } catch (\Throwable $e) {
            var_dump($e); exit;
            return ["error" => $e->getMessage()];
        }
    }
}
