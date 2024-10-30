<?php

namespace Modules\Pomelo\app\Services;

use PomeloPayConnect\Client;

class PomeloPayServices
{

    public $pomelo;

    public function __construct()
    {
        $this->pomelo = new Client('apikey', 'appid');
    }
    public function init($amount, $currency)
    {
        $json = [
            "provider" => "card", // bcmc, card, card
            "currency" => $currency,
            "localId" => uuid(),
            "amount" => floatval($amount * 100),
            "webhook" => getenv('POMELO_HOST_URL'),
            "redirectUrl" => "https://foo.bar/order/123" // Optional redirect after payment completion
        ];

        $transaction = $this->pomelo->transactions->create($json);
        return $transaction->url;
    }

    public function verify_webhook($headers, $apiKey)
    {
        $nonce = $headers['X-Signature-Nonce'];
        $timestamp = $headers['X-Signature-Timestamp'];
        $signature = $headers['X-Signature'];

        $signString = $nonce . $timestamp . $apiKey;
        $generatedSignature = hash('sha256', $signString);

        return $signature === $generatedSignature;
    }
}
