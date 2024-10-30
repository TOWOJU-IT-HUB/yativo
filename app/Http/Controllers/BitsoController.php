<?php

namespace App\Http\Controllers;

use App\Services\BitsoWithdrawal;
use Illuminate\Http\Request;

class BitsoController extends Controller
{
    public function initiateWithdrawal(Request $request)
    {
        $apiKey = 'WOQzVVhdTD';
        $apiSecret = '65b52bca95c5be4ef84d3e0e3f615552';
        $baseUrl = 'https://bitso.com';
        $requestPath = '/api/v3/withdrawals';

        $withdrawalData = [
            "currency" => "mxn",
            "protocol" => "clabe",
            "amount" => $request->input('amount', '50'),
            "numeric_ref" => $request->input('numeric_ref', '1234567'),
            "notes_ref" => $request->input('notes_ref', 'Pago servicios'),
            "rfc" => $request->input('rfc', 'PESJ590317IG4'),
            "clabe" => $request->input('clabe', '646180110400000007'),
            "beneficiary" => $request->input('beneficiary', 'Juan Pérez'),
            "origin_id" => $request->input('origin_id', 'bitso_c661dcdbf9e2')
        ];

        $bitso = new BitsoWithdrawal($apiKey, $apiSecret, $baseUrl, $requestPath);
        $response = $bitso->initiateWithdrawal($withdrawalData);

        return get_success_response($response);
    }
}
