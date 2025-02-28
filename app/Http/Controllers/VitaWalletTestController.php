<?php

namespace Modules\VitaWallet\app\Http\Controllers;

use App\Http\Controllers\Controller;
// use Modules\VitaWallet\app\Services\VitaWalletAPI;
use App\Services\VitaWalletAPI;
use Illuminate\Http\Request;

class VitaWalletTestController extends Controller
{
    protected $vitaBusinessAPI;

    public function __construct()
    {
        $this->vitaBusinessAPI = new VitaWalletAPI();
    }

    // public function getWalletByUUID($uuid)
    // {
    //     return response()->json($this->vitaBusinessAPI->getWalletByUUID($uuid));
    // }

    public function listWallets(Request $request)
    {
        $response = $this->vitaBusinessAPI->makeSignedRequest('/wallets', [], 'get');
        return response()->json($response);
    }

    public function createTransaction(Request $request)
    {
        $payload = [
            'amount' => 100000,
            'country_iso_code' => 'AR',
            'issue' => 'This is a test',
            'success_redirect_url' => 'https://www.google.com/',
        ];
        $response = $this->vitaBusinessAPI->makeSignedRequest('/payment_orders', $payload);
        return response()->json($response);
    }

    public function createWithdrawal(Request $request)
    {
        $payload = [
            'url_notify' => 'https://my_business.com/',
            'beneficiary_first_name' => 'Gabriela',
            'beneficiary_last_name' => 'Pazmiño',
            'beneficiary_email' => 'my_email@vitawallet.io',
            'beneficiary_address' => 'My address, esquina, 21-64',
            'beneficiary_document_type' => 'RUT',
            'beneficiary_document_number' => '111111',
            'account_type_bank' => 'Cuenta de ahorros',
            'account_bank' => '999999999',
            'bank_code' => 10,
            'purpose' => 'ISSAVG',
            'purpose_comentary' => 'This is a test',
            'country' => 'CL',
            'currency' => 'clp',
            'amount' => 10,
            'order' => '0002',
            'transactions_type' => 'withdrawal',
            'wallet' => 'a6c44ea8-cba1-40e9-839a-1348a5701108',
        ];
        return response()->json($this->vitaBusinessAPI->makeSignedRequest("", $payload));
    }

    public function createVitaSend(Request $request)
    {
        $payload = [
            'email' => 'vita_user@vitawallet.io',
            'currency' => 'clp',
            'order' => '0001',
            'amount' => 100000,
            'transactions_type' => 'vita_sent',
            'wallet' => 'f0953f43-2e68-4a1a-acdf-f5227fe0095c',
        ];
        return response()->json($this->vitaBusinessAPI->makeSignedRequest("transactions", $payload));
    }

    public function listTransactions(Request $request)
    {
        $page = $request->get('page', 1);
        $count = $request->get('count', 10);
        return response()->json($this->vitaBusinessAPI->makeSignedRequest("transactions", [], "GET"));
    }

    public function listWalletTransactions(Request $request, $uuid)
    {
        $page = $request->get('page', 1);
        $count = $request->get('count', 10);
        return response()->json($this->vitaBusinessAPI->makeSignedRequest("", $uuid, $page, $count));
    }

    public function call()
    {
        $request = request();
        return [
            "list_wallets" => $this->listWallets($request),
            "create_transaction" => $this->createTransaction($request),
            "create_withdrawal" => $this->createWithdrawal($request),
            "create_vita_send" => $this->createVitaSend($request),
            "list_transactions" => $this->listTransactions($request),
        ];
    }
}
