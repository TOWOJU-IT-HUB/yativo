<?php

namespace Modules\Flow\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\User;
use App\Models\payoutMethods;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Modules\Flow\app\Services\FlowServices;
use Modules\SendMoney\app\Models\SendMoney;
use Modules\Beneficiary\app\Models\BeneficiaryPaymentMethod;
use Illuminate\Support\Facades\Log;

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

            // var_dump($requestData); exit;

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
        Log::info("Floid request and response data", ['request' => $request->getContent()]);

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
        if (isset($request->id) && !empty($request->id)) {
            $deposit = Deposit::where('gateway_deposit_id', $request->id)->first();
            if ($deposit) {
                // get the deposit then process it.
                if (strtoupper($deposit->currency) == "PEN") {
                    return $this->getPenPaymentStatus($request->id);
                } else if (strtoupper($deposit->currency) == "CLP") {
                    return $this->getChlPaymentStatus($request->id);
                } else {
                    //
                }
            }

            return rediret()->away('https://app.yativo.com');
        }
    }
    /**
     * Handle payout to a beneficiary based on currency and gateway.
     *
     * @param  object $payload    Payload containing payout and customer info.
     * @param  float  $amount     Payout amount.
     * @param  string $currency   Currency code (e.g., CLP, PEN, USD).
     * @return array              Response from payout gateway or error info.
     */
    public function payout($payload, $amount, $currency)
    {
        try {
            // Step 1: Fetch beneficiary payment method from DB
            $beneficiaryId = $payload->beneficiary_id;
            $model = new BeneficiaryPaymentMethod();
            $data = $model->getBeneficiaryPaymentMethod($beneficiaryId);

            if (!$data || !$data->payment_data) {
                return ['error' => 'Beneficiary not found'];
            }

            $ben = $data->payment_data;

            // Step 2: Fetch gateway configuration
            $gateway = payoutMethods::whereId($data->gateway_id)->first();
            if (!$gateway) {
                return ['error' => 'Gateway not found'];
            }

            // Step 3: Determine request URL and data based on currency
            if ($currency === 'CLP') {
                $url = "https://api.floid.app/cl/payout/create";

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
            } elseif (in_array($currency, ['PEN', 'USD'])) {
                $url = "https://api.floid.app/pe/payout/create";

                // PEN = 1, USD = 2
                $paymentCurrency = $currency === 'PEN' ? 1 : 2;

                $requestData = [
                    "amount" => floatval($payload->customer_receive_amount),
                    "currency" => $paymentCurrency,
                    "beneficiary_account" => $ben['beneficiary_account']
                ];
            } else {
                return ['error' => "Unsupported currency selected"];
            }

            // Step 4: Make API request to Floid
            $authToken = env("FLOID_AUTH_TOKEN");

            $response = Http::withToken($authToken)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $requestData);

            $result = $response->json();

            // Step 5: Handle successful payout
            if (isset($result["status"]) && strtoupper($result["status"]) === "SUCCESSFUL") {
                mark_payout_completed($payload->id, $payload->payout_id);
            }

            // Step 6: Handle API-level errors
            if (
                (isset($result['status']) && strtolower($result['status']) === "error") ||
                (isset($result['code']) && $result['code'] == 400)
            ) {
                $error = $result['data']['error_message'] ?? $result['error_message'] ?? 'Unknown error';
                return ['error' => $error];
            }

            // Return the raw API response if no errors
            return $result;

        } catch (\Throwable $e) {
            // Catch any runtime or HTTP exceptions
            return ["error" => $e->getMessage()];
        }
    }


    use Illuminate\Support\Facades\Http;

    /**
     * Check payout status using case ID and currency.
     *
     * @param string $caseId   The payout case ID to check.
     * @param string $currency The currency code ('PEN' or 'CLP') to determine API path.
     * @return array           The API response as an associative array.
     */
    function checkPayoutStatus(string $caseId, string $currency): array
    {
        // Determine API region path based on currency
        $region = strtoupper($currency) === 'PEN' ? 'pe' : 'cl';

        // Construct the API URL dynamically
        $url = "https://api.floid.app/{$region}/payout/status";

        // Retrieve token from .env file
        $token = env('FLOID_AUTH_TOKEN');

        try {
            // Make the POST request with authorization and JSON payload
            $response = Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json'
                ])
                ->post($url, [
                    'payout_caseid' => $caseId
                ]);

            // Return decoded JSON response
            return $response->json();
        } catch (\Throwable $e) {
            // Return error if something goes wrong
            return ['error' => $e->getMessage()];
        }
    }

}
