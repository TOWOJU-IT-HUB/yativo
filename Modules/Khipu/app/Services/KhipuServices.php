<?php

namespace Modules\Khipu\app\Services;

use Khipu\Configuration;
use Khipu\ApiClient;
use Khipu\Client\PaymentsApi;


class KhipuServices
{
    public function __construct()
    {
    }

    public function get($key, $default = null)
    {
    }

    public function makePayment($txn_id, $amount, $currency = "CLP")
    {

        $receiverId = 'obtener - al - crear - una - cuenta - de - cobro';
        $secretKey = 'obtener-al-crear-una-cuenta-de-cobro';

        if(!in_array($currency, ['CLP', 'USD', 'ARS', 'BOB'])) {
            return ['error' => "Unsupported currency"];
        }


        $configuration = new Configuration();
        $configuration->setReceiverId($receiverId);
        $configuration->setSecret($secretKey);
        $configuration->setDebug(true);

        $client = new ApiClient($configuration);
        $payments = new PaymentsApi($client);

        try {
            $opts = array(
                "transaction_id" => $txn_id,
                "return_url" => request()->redirect_url ?? env("WEB_URL", "https://app.yativo.com"),
                "cancel_url" => request()->redirect_url ?? env("WEB_URL", "https://app.yativo.com"),
                "picture_url" => "https://www.khipu.com/wp-content/uploads/2022/01/Logo-color.png",
                "notify_url" => "http://mi-ecomerce.com/backend/notify",
            );
            $response = $payments->paymentsPost(
                "Wallet funding on Yativo.com", // Purchase reason
                $currency, // available currencies CLP, USD, ARS, BOB
                $amount, // amount
                $opts // optional parameters
            );

            if(!is_array($response)) {
                $response = (array)$response;
            }

            return $response;
        } catch (\Throwable $e) {
            return get_error_response($e->getMessage(), TRUE);
        }
    }
}