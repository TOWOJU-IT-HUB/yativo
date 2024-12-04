<?php

namespace Modules\VitaWallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Services\Configuration;
use App\Services\VitaBusinessAPI;
use Config;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use Modules\VitaWallet\app\Services\VitaWalletService;

class VitaWalletController extends Controller
{
    protected $vitaWalletService, $url;

    public function __construct()
    {
        // $this->url = "https://api.stage.vitawallet.io/api/businesses/";
        $this->vitaWalletService = new VitaBusinessAPI();
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
        // $configuration->setCredentials([
        //     'X_Login' => '0e36f424264d2cbe10a8c37495d81f94f203cc10',
        //     'X_Trans_Key' => 'A3HircoktCaDunE1+/VZNcyB0G0=',
        //     'secret' => '6e818267bc5364660ec4de5defee0282c88e76be77750eba61879640d5c0e5d4e25f8956b71efbbd0ba1081af22ac1b6751d3a8d5f8adfdad453d2063d853db2f82ff971bd1026e719aebb0e6595a4b1eea094b20f16da01aed2f64be62f9405d5c64f309051cb5a01af2f40ccf7cee2cc74e9c9fa560f6864e663eafe40426131dd03591a3f7112d90f2e609b2e52a504a8c7573953835c5deaa36c8296f97bbc8c8b96acf538ac0be8d97b68af0fada093363bbde79af2b018a280bd31a8965a574374e054c77207404a538d980cf6439eb3247cf97a0fc9ef5f6f989babcb0e0fa8f3615a1c117a64646afea2498fe6c9e81be33f15956b38d735a7305cbd0f02f557bd0ed6b064896f79ca150ec29de35d576e32f7326b2a9630237fe796d2e236eb2307bfed9df9d7c4',
        //     'env' => Configuration::$STAGE,
        //     'isDevelopment' => true,
        // ]);

        if (strlen($currencyIso2) == 3) {
            $country = Country::where('currency_code', $currencyIso2)->first();
            if ($country->iso2)
                $currencyIso2 = $country->iso2;
        }

        $payload = [
            "amount" => $amount,
            "country_iso_code" => strtoupper($currencyIso2),
            "issue" => "Yativo wallet Topup",
            "success_redirect_url" => url('callback/webhook/deposit/vitawallet', ["depositId" => $quoteId]),
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
        // curl_setopt($ch, CURLOPT_VERBOSE, true);  // Enable verbose output for debugging

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
        // var_dump($result['data']['attributes']['public_code']); 
        if (isset($result['data']['attributes']['public_code'])) {
            update_deposit_gateway_id($quoteId, $result['data']['attributes']['public_code']);
            return $result['data']['attributes']['url'];
        }
        // var_dump($result['data']['attributes']['public_code']);
        // return response()->json($result);
        return $result;
    }

    public function withdrawal_rules($country = null)
    {
        $response = $this->vitaWalletService->makeSignedRequest('withdrawal_rules', [], 'get');
        return $response;
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

    /**
     * Summary of create_withdrawal
     * @param mixed $requestBody
     * @return array
     */
    public function create_withdrawal($requestBody)
    {
        $configuration = Configuration::getInstance();
        $erequestBody = [
            "url_notify" => "https://webhook.site/cf0640bf-ea30-4a50-acbb-b82061426f97",
            "beneficiary_first_name" => "John",
            "beneficiary_last_name" => "Doe",
            "beneficiary_email" => "john.doe@example.com",
            "beneficiary_address" => "123 Main St",
            "beneficiary_document_type" => "RUT",
            "beneficiary_document_number" => "111111",
            "account_type_bank" => "Cuenta de ahorros",
            "account_bank" => "1234567890123456",
            "bank_code" => "10",
            "purpose" => "ISGDDS",
            "purpose_comentary" => "For business purposes",
            "country" => "CL",
            "currency" => "CLP",
            "amount" => 50000,  // Assuming $amount is already defined
            "order" => rand(2314, 849584),  // Assuming $quoteId is a variable already defined
            "city" => "smaller territories of the uk",
            "phone" => "9203751431",
            "beneficiary_type" => "Individual",
            "company_name" => null,
            "bank_branch" => null,
            "swift_bic" => "TCCLGB",
            "zipcode" => null,
            "routing_number" => null,
            "transactions_type" => "withdrawal",
            "wallet" => "76f1d08e-9981-4d69-bfc5-edc0c1bc0574",  // Assuming $walletUUID is already defined
        ];

        // Prepare headers
        $headers = $configuration->prepareHeaders($requestBody);

        // Prepare HTTP request
        $response = Http::withHeaders([
            "X-Date" => $headers['headers']["X-Date"],
            "X-Login" => $headers['headers']["X-Login"],
            "X-Trans-Key" => $headers['headers']["X-Trans-Key"],
            "Content-Type" => $headers['headers']["Content-Type"],
            "Authorization" => $headers['headers']["Authorization"],
        ])->post(Configuration::createTransaction(), $requestBody);

        $result = $response->json();

        Log::error('Response: ' . json_encode($result));

        return ['response' => $result];
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

    public function deposit_callback(Request $request)
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
}
