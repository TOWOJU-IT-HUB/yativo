<?php

namespace Modules\VitaWallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Configuration;
use App\Services\VitaBusinessAPI;
use Config;
use Http;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use Modules\VitaWallet\app\Services\VitaWalletService;

class VitaWalletTestController extends Controller
{
    protected $vitaWalletService, $url;

    public function __construct()
    {
        $this->url = "https://api.vitawallet.io/api/businesses/";
        $this->vitaWalletService = new VitaBusinessAPI();
    }

    public function wallets()
    {
        $response = $this->vitaWalletService->makeSignedRequest('wallets', null, 'get');

        return $response;
    }

    public function withdrawal_rules($country = null)
    {
        $response = $this->vitaWalletService->makeSignedRequest('withdrawal_rules', [], 'get');
        return $response;
    }

    public function prices()
    {
        $configuration = Configuration::getInstance();
        $configuration->setCredentials([
            'env' => 'stage',
            'X_Login' => '0e36f424264d2cbe10a8c37495d81f94f203cc10',
            'X_Trans_Key' => 'A3HircoktCaDunE1+/VZNcyB0G0=',
            'secret' => '6e818267bc5364660ec4de5defee0282c88e76be77750eba61879640d5c0e5d4e25f8956b71efbbd0ba1081af22ac1b6751d3a8d5f8adfdad453d2063d853db2f82ff971bd1026e719aebb0e6595a4b1eea094b20f16da01aed2f64be62f9405d5c64f309051cb5a01af2f40ccf7cee2cc74e9c9fa560f6864e663eafe40426131dd03591a3f7112d90f2e609b2e52a504a8c7573953835c5deaa36c8296f97bbc8c8b96acf538ac0be8d97b68af0fada093363bbde79af2b018a280bd31a8965a574374e054c77207404a538d980cf6439eb3247cf97a0fc9ef5f6f989babcb0e0fa8f3615a1c117a64646afea2498fe6c9e81be33f15956b38d735a7305cbd0f02f557bd0ed6b064896f79ca150ec29de35d576e32f7326b2a9630237fe796d2e236eb2307bfed9df9d7c4',
            'env' => Configuration::$STAGE,
            'isDevelopment' => true,
        ]);

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
        // $this->prices();
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

        // Prepare cURL request
        $ch = curl_init();
        // curl_setopt($ch, CURLOPT_URL, Configuration::getWalletsUrl());
        curl_setopt($ch, CURLOPT_URL, Configuration::createTransaction());
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
            ;
        }

        curl_close($ch);

        Log::error('Response: ' . json_encode($result));

        return $result;
    }
}