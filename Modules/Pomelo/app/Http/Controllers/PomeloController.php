<?php

namespace Modules\Pomelo\app\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Pomelo\app\Services\PomeloPayServices;

class PomeloController extends Controller
{
    public $pomelo_services;

    public function __construct()
    {
        $this->pomelo_services = new PomeloPayServices();
    }

    public function makePayment($quoteId, $amount, $currency)
    {
        try {
            $checkout = $this->pomelo_services->init($amount, $currency);
            updateSendMoneyRawData($quoteId, $checkout);
            return result($checkout);
        } catch (\Throwable $th) {
            return ['error' => $th->getMessage()];
        }
    }

    public function webhook(Request $request, $orderId)
    {
        // Usage example
        $headers = [
            'X-Signature-Nonce' => 'your_nonce',
            'X-Signature-Timestamp' => 'your_timestamp',
            'X-Signature' => 'received_signature'
        ];

        $apiKey = 'your_api_key';
        $isValidSignature = $this->pomelo_services->verify_webhook($headers, $apiKey);
        var_dump($isValidSignature);
    }
}
