<?php

namespace Modules\VitaWallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Track;
use App\Models\TransactionRecord;
use App\Services\Configuration;
use App\Services\DepositService;
use App\Services\VitaBusinessAPI;
use App\Services\VitaWalletAPI;
use Config;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use App\Models\Deposit;
use Modules\VitaWallet\app\Services\VitaWalletService;

class VitaWalletController extends Controller
{
    protected $vitaWalletService, $url;
    protected $vitaBusinessAPI;

    public function __construct()
    {
        // $this->url = "https://api.stage.vitawallet.io/api/businesses/";
        $this->vitaWalletService = new VitaBusinessAPI();
        $this->vitaBusinessAPI = new VitaWalletAPI();
    }

    public function wallets()
    {
        $response = $this->vitaWalletService->makeSignedRequest('wallets', [], 'get');

        return $response;
    }

    /**
     * Summary of payin
     * 
     * @param mixed amount
     * @param string $currency - In iso2 format
     * 
     * @return array
     * $headers = $configuration->prepareHeaders(''); // for get requests
     * 
     */
    public function payin($quoteId, $amount = 1000, $currencyIso2 = 'ar')
    {
        $configuration = Configuration::getInstance();
        if (strlen($currencyIso2) == 3) {
            $country = Country::where('currency_code', $currencyIso2)->first();
            if ($country && $country->iso2)
                $currencyIso2 = $country->iso2;
        }

        $payload = [
            "amount" => round($amount),
            "country_iso_code" => strtoupper($currencyIso2),
            "issue" => "Yativo wallet Topup",
            "success_redirect_url" => route('vitawallet.deposit.callback.success', ["quoteId" => $quoteId]),
        ];

        // var_dump($payload); exit;

        $this->prices();


        // Initialize Configuration and set credentials
        $configuration = Configuration::getInstance();
        // Prepare headers
        $headers = $configuration->prepareHeaders($payload);
        $xheaders = [
            "X-Date: " . $headers['headers']["X-Date"],
            "X-Login: " . $headers['headers']["X-Login"],
            "X-Trans-Key: " . $headers['headers']["X-Trans-Key"],
            "Content-Type: " . $headers['headers']["Content-Type"],
            "Authorization: " . $headers['headers']["Authorization"],
        ];

        // Prepare cURL request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Configuration::payin());
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        
        // Execute request
        $response = curl_exec($ch);
        if ($response === false) {
            $result = ["error" => 'cURL Error: ' . curl_error($ch)];
        } else {
            if (!is_array($response)) {
                $result = json_decode($response, true);
            }
        }

        curl_close($ch);

        if (!is_array($result)) {
            $result = json_decode($result, true);
        }

        $deposit = Deposit::where('id', $quoteId)->first();
        if($deposit) {
            $deposit->update([
                'meta' => [
                    'payload' => $payload,
                    'response' => $result
                ]
            ]);
        }

        if (isset($result['data']['attributes']['public_code'])) {
            update_deposit_gateway_id($quoteId, $result['data']['attributes']['public_code']);
            return $result['data']['attributes']['url'];
        }
        
        return $result;
    }

    public function withdrawal_rules($country = null)
    {
        $configuration = Configuration::getInstance();
        // Prepare headers
        $headers = $configuration->prepareHeaders();

        // Prepare HTTP request
        $response = Http::withHeaders([
            "X-Date" => $headers['headers']["X-Date"],
            "X-Login" => $headers['headers']["X-Login"],
            "X-Trans-Key" => $headers['headers']["X-Trans-Key"],
            "Content-Type" => $headers['headers']["Content-Type"],
            "Authorization" => $headers['headers']["Authorization"],
        ])->get(Configuration::getWithdrawalRulesUrl());

        // $response = $this->vitaWalletService->sendRequest('get', 'prices');
        $result = $response->json();
        return $result;
    }


    public function prices()
    {
        $configuration = Configuration::getInstance();
        // Prepare headers
        $headers = $configuration->prepareHeaders();

        // Prepare HTTP request
        $response = Http::withHeaders([
            "X-Date" => $headers['headers']["X-Date"],
            "X-Login" => $headers['headers']["X-Login"],
            "X-Trans-Key" => $headers['headers']["X-Trans-Key"],
            "Content-Type" => $headers['headers']["Content-Type"],
            "Authorization" => $headers['headers']["Authorization"],
        ])->get(Configuration::getPricesUrl());

        // $response = $this->vitaWalletService->sendRequest('get', 'prices');
        $result = $response->json();
        return $result;
    }

    public function getTransaction($txn_id)
    {
        if($txn_id && !empty($txn_id)) {
            $configuration = Configuration::getInstance();
            // Prepare headers
            $headers = $configuration->prepareHeaders();

            // Prepare HTTP request
            $response = Http::withHeaders([
                "X-Date" => $headers['headers']["X-Date"],
                "X-Login" => $headers['headers']["X-Login"],
                "X-Trans-Key" => $headers['headers']["X-Trans-Key"],
                "Content-Type" => $headers['headers']["Content-Type"],
                "Authorization" => $headers['headers']["Authorization"],
            ])->get(Configuration::getTransactionsUrI($txn_id));

            Log::info("response from vitawallet for {$txn_id} is ", ['response' => $response]);
            $result = $response->json();
            return $result;
        }
    }

    public function getPayout($txn_id)
    {
        if($txn_id && !empty($txn_id)) {
            $configuration = Configuration::getInstance();
            // Prepare headers
            $headers = $configuration->prepareHeaders();

            // Prepare HTTP request
            $response = Http::withHeaders([
                "X-Date" => $headers['headers']["X-Date"],
                "X-Login" => $headers['headers']["X-Login"],
                "X-Trans-Key" => $headers['headers']["X-Trans-Key"],
                "Content-Type" => $headers['headers']["Content-Type"],
                "Authorization" => $headers['headers']["Authorization"],
            ])->get(Configuration::getTransactionsUrl($txn_id));

            Log::info("response from vitawallet for {$txn_id} is ", ['response' => $response]);
            $result = $response->json();
            return $result;
        }
    }
    
    /**
     * Summary of create_withdrawal
     * @param mixed $requestBody
     * @return array
     */
    public function create_withdrawal($requestBody)
    {
        $xprices = cache()->remember('xprices', 300, function () {
            return $this->prices();
        });
        $array = $requestBody;

        // Initialize Configuration and set credentials
        $configuration = Configuration::getInstance();
        // Prepare headers
        // $headers = $configuration->prepareHeaders(''); // for get requests
        $headers = $configuration->prepareHeaders($array);
        $xheaders = [
            "X-Date: " . $headers['headers']["X-Date"],
            "X-Login: " . $headers['headers']["X-Login"],
            "X-Trans-Key: " . $headers['headers']["X-Trans-Key"],
            "Content-Type: " . $headers['headers']["Content-Type"],
            "Authorization: " . $headers['headers']["Authorization"],
        ];

        // return response()->json($xheaders);

        $endpoint = Configuration::createTransaction();
        // Prepare cURL request
        $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, Configuration::getWalletsUrl());
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($array));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $xheaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true);  // Enable verbose output for debugging

        // Execute request
        $response = curl_exec($ch);
        if ($response === false) {
            $result = ["error" => 'cURL Error: ' . curl_error($ch)];
        } else {
            if (!is_array($response)) {
                $result = json_decode($response, true);
            }
        }

        curl_close($ch);


        $vitawallet = new VitaWalletAPI();
        $secondary_request = $vitawallet->makeSignedRequest($endpoint, $requestBody, "POST");
        $resp = $this->vitaBusinessAPI->makeSignedRequest("", $array);
        // var_dump($resp); exit;

        var_dump([
            "initial_request" => $response,
            "xheaders" => $xheaders,
            "payload" => $requestBody,
            "secondary" => [
                "endpoint" => $endpoint,
                "payload" => $requestBody,
                "response" => $secondary_request
            ],
            "resp" => $resp,
            "prices" => $xprices
        ]); exit;
        
        return $result;
    }


    public function callback(Request $request)
    {
        // Log all incoming request information
        Log::info('Vitawallet Callback Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'payload' => $request->all(),
            'headers' => $request->header(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    public function deposit_callback(Request $request, $deposit_id)
    {
        $order = TransactionRecord::where("transaction_id", $deposit_id)->first();
        if (isset($request->status) && $request->status === true && isset($request->order)) {
            $where = [
                "transaction_memo" => "payin",
                "transaction_id" => $deposit_id
            ];
            $order = TransactionRecord::where($where)->first();
            if ($order) {
                $deposit_services = new DepositService();
                $deposit_services->process_deposit($order->transaction_id);
                $this->updateTracking($deposit_id, $request->status, $request->toArray());
            }
        }

        // Log all incoming request information
        Log::info("Vitawallet Callback for deposit ID: {$deposit_id}", [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'payload' => $request->all(),
            'headers' => $request->header(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return http_response_code(200);
    }

    private function updateTracking($quoteId, $trakingStatus, $response)
    {
        Track::create([
            "quote_id" => $quoteId,
            "transaction_type" => "deposit",
            "tracking_status" => $trakingStatus,
            "raw_data" => (array) $response,
            "tracking_updated_by" => "webhook"
        ]);
    }
}
